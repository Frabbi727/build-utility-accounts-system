<?php

namespace App\Services\Backup;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;
use ZipArchive;

class BackupService
{
    public function __construct(
        private readonly DatabaseDumpService $databaseDumpService,
        private readonly RetentionManager $retentionManager,
        private readonly BackupNotifier $notifier,
        private readonly AuditService $auditService,
    ) {}

    /**
     * Create a full backup (Database + Persistent Storage Files).
     *
     * @param  array<string, mixed>  $options
     */
    public function create(
        BackupType $type = BackupType::Automatic,
        ?User $creator = null,
        array $options = [],
    ): Backup {
        if (! config('backup.enabled', true)) {
            throw new RuntimeException('Backup system is currently disabled via configuration.');
        }

        // Concurrency protection: prevent concurrent backups or backups while restoring
        $skipLock = $options['skip_lock'] ?? false;
        $lock = $skipLock ? null : Cache::lock('backup_operation_lock', 600);

        if ($lock && ! $lock->get()) {
            throw new RuntimeException('Another backup or restore operation is currently in progress. Please wait.');
        }

        $startTime = microtime(true);
        $environment = (string) app()->environment();
        $dateSlug = Carbon::now('Asia/Dhaka')->format('Ymd_His');
        $randomSuffix = strtolower(Str::random(6));
        $backupName = "uas_{$environment}_{$dateSlug}_{$randomSuffix}";
        $archiveFilename = "{$backupName}.zip";
        $targetDisk = (string) config('backup.disk', 'local');
        $backupPath = trim(config('backup.path', 'backups'), '/');
        $destinationPath = "{$backupPath}/{$archiveFilename}";

        $dbConnection = config('database.default', 'pgsql');
        $dbName = (string) config("database.connections.{$dbConnection}.database", 'utility_accounts');

        $retentionDays = (int) config('backup.retention.daily', 30);
        $retentionDate = now()->addDays($retentionDays);

        // Record initial pending backup entry
        $backup = Backup::create([
            'backup_name' => $backupName,
            'backup_type' => $type,
            'environment' => $environment,
            'file_path' => $destinationPath,
            'disk' => $targetDisk,
            'file_size' => 0,
            'database_name' => $dbName,
            'checksum' => null,
            'status' => BackupStatus::Running,
            'restore_status' => null,
            'error_message' => null,
            'is_protected' => $type === BackupType::Safety,
            'metadata' => [
                'type' => $type->value,
                'created_by_name' => $creator !== null ? $creator->name : ($type === BackupType::Automatic ? 'System Scheduler' : 'System'),
            ],
            'retention_date' => $retentionDate,
            'created_by' => $creator?->id,
            'completed_at' => null,
        ]);

        $tempDir = storage_path('app/temp/backup_'.uniqid($backupName.'_', true));

        try {
            File::ensureDirectoryExists($tempDir);

            // Step 1: Database dump
            $sqlFile = "{$tempDir}/database.sql";
            $dumpStats = $this->databaseDumpService->dump($sqlFile, $dbConnection);

            // Step 2: Collect persistent files
            $filesDir = "{$tempDir}/files";
            $fileCount = 0;
            $filesBytes = 0;
            $includeFiles = $options['include_files'] ?? config('backup.include_files', true);

            if ($includeFiles) {
                File::ensureDirectoryExists($filesDir);
                $directoriesToInclude = config('backup.files_directories', ['public']);

                foreach ($directoriesToInclude as $dir) {
                    $sourcePath = storage_path("app/{$dir}");
                    if (is_dir($sourcePath)) {
                        $targetDir = "{$filesDir}/{$dir}";
                        File::ensureDirectoryExists($targetDir);
                        $fileStats = $this->copyDirectory($sourcePath, $targetDir);
                        $fileCount += $fileStats['count'];
                        $filesBytes += $fileStats['bytes'];
                    }
                }
            }

            // Step 3: Generate manifest.json
            $manifest = [
                'backup_name' => $backupName,
                'backup_type' => $type->value,
                'created_at' => now()->toIso8601String(),
                'environment' => $environment,
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
                'database_driver' => $dbConnection,
                'database_name' => $dbName,
                'database_stats' => $dumpStats,
                'files_count' => $fileCount,
                'files_bytes' => $filesBytes,
                'is_encrypted' => (bool) config('backup.encryption_enabled', false),
            ];

            file_put_contents(
                "{$tempDir}/manifest.json",
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            // Step 4: Package into ZIP archive
            $zipPath = "{$tempDir}/{$archiveFilename}";
            $this->createZipArchive($tempDir, $zipPath, [$archiveFilename]);

            // Step 5: Optional Encryption at rest
            $finalArchivePath = $zipPath;
            if (config('backup.encryption_enabled', false)) {
                $encryptedPath = "{$tempDir}/{$backupName}.enc";
                $this->encryptFile($zipPath, $encryptedPath);
                $finalArchivePath = $encryptedPath;
            }

            // Step 6: Verify backup integrity
            $checksum = hash_file('sha256', $finalArchivePath);
            if (! $checksum) {
                throw new RuntimeException('Failed to calculate SHA-256 checksum for backup archive.');
            }

            $finalSize = filesize($finalArchivePath);
            if ($finalSize === false || $finalSize === 0) {
                throw new RuntimeException('Generated backup archive is empty (0 bytes).');
            }

            // Verify Zip validity if unencrypted
            if (! config('backup.encryption_enabled', false)) {
                $this->verifyZipArchive($finalArchivePath);
            }

            // Step 7: Store archive to target disk
            $stream = fopen($finalArchivePath, 'r');
            if (! $stream) {
                throw new RuntimeException("Failed to read generated archive from {$finalArchivePath}");
            }

            Storage::disk($targetDisk)->put($destinationPath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            // Step 8: Replicate to Remote Storage if configured
            $remoteDisk = config('backup.remote_disk');
            $remoteSynced = false;

            if ($remoteDisk && $remoteDisk !== $targetDisk) {
                try {
                    $remoteStream = fopen($finalArchivePath, 'r');
                    if ($remoteStream) {
                        Storage::disk($remoteDisk)->put($destinationPath, $remoteStream);
                        if (is_resource($remoteStream)) {
                            fclose($remoteStream);
                        }
                        $remoteSynced = true;
                    }
                } catch (Throwable $e) {
                    Log::error("Failed to replicate backup to remote disk [{$remoteDisk}]: {$e->getMessage()}");
                }
            }

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            // Step 9: Finalize Backup Record
            $backup->update([
                'file_size' => $finalSize,
                'checksum' => $checksum,
                'status' => BackupStatus::Completed,
                'completed_at' => now(),
                'metadata' => array_merge($backup->metadata ?? [], [
                    'tables_count' => $dumpStats['tables_count'],
                    'rows_count' => $dumpStats['rows_count'],
                    'files_count' => $fileCount,
                    'duration_ms' => $durationMs,
                    'remote_synced' => $remoteSynced,
                    'encrypted' => (bool) config('backup.encryption_enabled', false),
                ]),
            ]);

            // Step 10: Audit Log
            $this->auditService->record(
                action: 'BACKUP_CREATED',
                module: 'backup',
                entity: $backup,
                description: "Created {$type->label()} backup [{$backupName}] ({$backup->formattedSize()}).",
                payload: [
                    'backup_name' => $backupName,
                    'type' => $type->value,
                    'size_bytes' => $finalSize,
                    'checksum' => $checksum,
                    'duration_ms' => $durationMs,
                ],
                userId: $creator?->id,
            );

            // Step 11: Run Retention cleanup
            try {
                $this->retentionManager->clean();
            } catch (Throwable $e) {
                Log::warning('Automatic backup retention cleaning encountered an error: '.$e->getMessage());
            }

            // Step 12: Notification
            $this->notifier->notifySuccess($backup);

            return $backup;
        } catch (Throwable $e) {
            $errorMsg = $e->getMessage();
            Log::error("Backup creation failed for [{$backupName}]: {$errorMsg}", [
                'exception' => $e,
            ]);

            $backup->update([
                'status' => BackupStatus::Failed,
                'error_message' => $errorMsg,
            ]);

            $this->notifier->notifyFailure($errorMsg, $backup);

            throw $e;
        } finally {
            // Clean up temporary workspace
            if (File::isDirectory($tempDir)) {
                File::deleteDirectory($tempDir);
            }

            $lock?->release();
        }
    }

    /**
     * Upload and import an external backup archive into the system.
     */
    public function upload(string $temporaryFilePath, string $originalFilename, ?User $uploader = null): Backup
    {
        if (! file_exists($temporaryFilePath) || filesize($temporaryFilePath) === 0) {
            throw new RuntimeException('Uploaded file is missing or empty.');
        }

        $fileSize = (int) filesize($temporaryFilePath);
        $checksum = hash_file('sha256', $temporaryFilePath);

        if (! $checksum) {
            throw new RuntimeException('Failed to calculate SHA-256 checksum for uploaded backup.');
        }

        $tempDir = storage_path('app/temp/upload_verify_'.uniqid());
        File::ensureDirectoryExists($tempDir);

        $targetDisk = (string) config('backup.disk', 'local');
        $backupPath = trim(config('backup.path', 'backups'), '/');

        try {
            // Verify and extract manifest if unencrypted zip
            $zip = new ZipArchive;
            $res = $zip->open($temporaryFilePath);

            $manifestData = [];
            $databaseName = (string) config('database.connections.'.config('database.default').'.database', 'utility_accounts');
            $environment = (string) app()->environment();

            if ($res === true) {
                if ($zip->locateName('database.sql') === false && $zip->locateName('manifest.json') === false) {
                    $zip->close();
                    throw new RuntimeException('Uploaded archive is not a valid system backup: database.sql or manifest.json is missing.');
                }

                $manifestJson = $zip->getFromName('manifest.json');
                if ($manifestJson !== false) {
                    $parsed = json_decode($manifestJson, true);
                    if (is_array($parsed)) {
                        $manifestData = $parsed;
                        $environment = $parsed['environment'] ?? $environment;
                        $databaseName = $parsed['database_name'] ?? $databaseName;
                    }
                }
                $zip->close();
            }

            // Build unique backup name
            $dateSlug = Carbon::now('Asia/Dhaka')->format('Ymd_His');
            $randomSuffix = strtolower(Str::random(6));
            $baseName = pathinfo($originalFilename, PATHINFO_FILENAME);
            $cleanBaseName = Str::slug($baseName, '_');
            $backupName = $cleanBaseName ?: "uas_{$environment}_{$dateSlug}_{$randomSuffix}";

            // Ensure name uniqueness
            if (Backup::where('backup_name', $backupName)->exists()) {
                $backupName = "uas_{$environment}_{$dateSlug}_{$randomSuffix}";
            }

            $archiveFilename = "{$backupName}.zip";
            $destinationPath = "{$backupPath}/{$archiveFilename}";

            // Store to target backup disk
            $stream = fopen($temporaryFilePath, 'r');
            if (! $stream) {
                throw new RuntimeException('Failed to read uploaded archive.');
            }

            Storage::disk($targetDisk)->put($destinationPath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $backup = Backup::create([
                'backup_name' => $backupName,
                'backup_type' => BackupType::Manual,
                'environment' => $environment,
                'file_path' => $destinationPath,
                'disk' => $targetDisk,
                'file_size' => $fileSize,
                'database_name' => $databaseName,
                'checksum' => $checksum,
                'status' => BackupStatus::Completed,
                'restore_status' => null,
                'error_message' => null,
                'is_protected' => false,
                'metadata' => array_merge($manifestData, [
                    'uploaded' => true,
                    'original_filename' => $originalFilename,
                    'uploaded_by_id' => $uploader?->id,
                    'uploaded_by_name' => $uploader !== null ? $uploader->name : 'Administrator',
                    'uploaded_at' => now()->toIso8601String(),
                ]),
                'retention_date' => now()->addDays(config('backup.retention.daily', 30)),
                'created_by' => $uploader?->id,
                'completed_at' => now(),
            ]);

            $this->auditService->record(
                action: 'BACKUP_UPLOADED',
                module: 'backup',
                entity: $backup,
                description: "Uploaded external backup archive [{$backupName}] ({$backup->formattedSize()}).",
                payload: [
                    'backup_name' => $backupName,
                    'original_filename' => $originalFilename,
                    'file_size' => $fileSize,
                    'checksum' => $checksum,
                ],
                userId: $uploader?->id,
            );

            return $backup;
        } finally {
            if (File::isDirectory($tempDir)) {
                File::deleteDirectory($tempDir);
            }
        }
    }

    /**
     * Verify an existing backup record and file integrity.
     *
     * @return array{valid: bool, checksum_matches: bool, file_exists: bool, size_bytes: int, message: string}
     */
    public function verify(Backup $backup): array
    {
        $disk = Storage::disk($backup->disk);

        if (! $disk->exists($backup->file_path)) {
            return [
                'valid' => false,
                'checksum_matches' => false,
                'file_exists' => false,
                'size_bytes' => 0,
                'message' => 'Backup archive file is missing from storage.',
            ];
        }

        $size = $disk->size($backup->file_path);
        if ($size === 0) {
            return [
                'valid' => false,
                'checksum_matches' => false,
                'file_exists' => true,
                'size_bytes' => 0,
                'message' => 'Backup archive file is empty (0 bytes).',
            ];
        }

        // Checksum verification
        $actualChecksum = null;
        $tempFile = tempnam(sys_get_temp_dir(), 'uas_verify_');

        if ($tempFile) {
            file_put_contents($tempFile, $disk->get($backup->file_path));
            $actualChecksum = hash_file('sha256', $tempFile);
            @unlink($tempFile);
        }

        $checksumMatches = ($backup->checksum !== null && $backup->checksum === $actualChecksum);

        if (! $checksumMatches) {
            return [
                'valid' => false,
                'checksum_matches' => false,
                'file_exists' => true,
                'size_bytes' => $size,
                'message' => "Checksum mismatch. Expected [{$backup->checksum}], got [{$actualChecksum}].",
            ];
        }

        return [
            'valid' => true,
            'checksum_matches' => true,
            'file_exists' => true,
            'size_bytes' => $size,
            'message' => 'Backup verified successfully. File integrity intact.',
        ];
    }

    /**
     * Delete a backup safely.
     */
    public function delete(Backup $backup, ?User $user = null): void
    {
        if ($backup->is_protected) {
            throw new RuntimeException('Cannot delete a protected backup. Unprotect it first.');
        }

        if ($backup->status === BackupStatus::Restoring) {
            throw new RuntimeException('Cannot delete a backup currently being restored.');
        }

        // Delete from primary disk
        if (Storage::disk($backup->disk)->exists($backup->file_path)) {
            Storage::disk($backup->disk)->delete($backup->file_path);
        }

        // Delete from remote disk if configured
        $remoteDisk = config('backup.remote_disk');
        if ($remoteDisk && Storage::disk($remoteDisk)->exists($backup->file_path)) {
            Storage::disk($remoteDisk)->delete($backup->file_path);
        }

        $this->auditService->record(
            action: 'BACKUP_DELETED',
            module: 'backup',
            entity: $backup,
            description: "Deleted backup [{$backup->backup_name}].",
            payload: [
                'backup_name' => $backup->backup_name,
                'size_bytes' => $backup->file_size,
                'checksum' => $backup->checksum,
            ],
            userId: $user?->id,
        );

        $backup->delete();
    }

    /**
     * Toggle backup protection.
     */
    public function toggleProtection(Backup $backup, ?User $user = null): bool
    {
        $backup->is_protected = ! $backup->is_protected;
        $backup->save();

        $action = $backup->is_protected ? 'BACKUP_PROTECTED' : 'BACKUP_UNPROTECTED';
        $desc = $backup->is_protected
            ? "Protected backup [{$backup->backup_name}] from automatic deletion."
            : "Removed protection from backup [{$backup->backup_name}].";

        $this->auditService->record(
            action: $action,
            module: 'backup',
            entity: $backup,
            description: $desc,
            userId: $user?->id,
        );

        return $backup->is_protected;
    }

    /**
     * Recursively copy directory.
     *
     * @return array{count: int, bytes: int}
     */
    private function copyDirectory(string $source, string $destination): array
    {
        $count = 0;
        $bytes = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            $subPath = $iterator->getSubPathName();
            $target = $destination.DIRECTORY_SEPARATOR.$subPath;

            if ($item->isDir()) {
                File::ensureDirectoryExists($target);
            } else {
                File::ensureDirectoryExists(dirname($target));
                copy($item->getPathname(), $target);
                $count++;
                $bytes += (int) $item->getSize();
            }
        }

        return ['count' => $count, 'bytes' => $bytes];
    }

    /**
     * Create ZIP archive from directory contents.
     *
     * @param  list<string>  $excludeFilenames
     */
    private function createZipArchive(string $sourceDir, string $outZipPath, array $excludeFilenames = []): void
    {
        $zip = new ZipArchive;
        $res = $zip->open($outZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($res !== true) {
            throw new RuntimeException("Cannot open ZIP file for writing at {$outZipPath} (error code: {$res})");
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            $filePath = $file->getRealPath();
            $fileName = $file->getFilename();

            if (in_array($fileName, $excludeFilenames, true)) {
                continue;
            }

            $relativePath = substr($filePath, strlen($sourceDir) + 1);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } elseif ($file->isFile()) {
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
    }

    /**
     * Verify that ZIP archive is valid and contains essential backup contents.
     */
    private function verifyZipArchive(string $zipPath): void
    {
        $zip = new ZipArchive;
        $res = $zip->open($zipPath, ZipArchive::RDONLY);

        if ($res !== true) {
            throw new RuntimeException("Backup archive corruption detected: unable to open zip file (error: {$res})");
        }

        if ($zip->locateName('database.sql') === false) {
            $zip->close();
            throw new RuntimeException('Backup archive verification failed: database.sql is missing inside the archive.');
        }

        if ($zip->locateName('manifest.json') === false) {
            $zip->close();
            throw new RuntimeException('Backup archive verification failed: manifest.json is missing inside the archive.');
        }

        $zip->close();
    }

    /**
     * Encrypt file with AES-256-CBC.
     */
    private function encryptFile(string $sourcePath, string $targetPath): void
    {
        $key = (string) config('backup.encryption_key');
        if (empty($key)) {
            throw new RuntimeException('Backup encryption key is not configured.');
        }

        // Derive 32-byte key
        $keyBytes = hash('sha256', $key, true);
        $iv = random_bytes(16);

        $plaintext = file_get_contents($sourcePath);
        if ($plaintext === false) {
            throw new RuntimeException("Failed to read file for encryption: {$sourcePath}");
        }

        $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $keyBytes, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new RuntimeException('OpenSSL encryption failed for backup archive.');
        }

        // Store IV + ciphertext
        file_put_contents($targetPath, $iv.$ciphertext);
    }

    /**
     * Decrypt file with AES-256-CBC.
     */
    public function decryptFile(string $sourcePath, string $targetPath): void
    {
        $key = (string) config('backup.encryption_key');
        if (empty($key)) {
            throw new RuntimeException('Backup encryption key is not configured.');
        }

        $keyBytes = hash('sha256', $key, true);
        $content = file_get_contents($sourcePath);
        if ($content === false || strlen($content) < 17) {
            throw new RuntimeException("Failed to read encrypted file or content is invalid: {$sourcePath}");
        }

        $iv = substr($content, 0, 16);
        $ciphertext = substr($content, 16);

        $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $keyBytes, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new RuntimeException('Failed to decrypt backup archive: invalid key or corrupted data.');
        }

        file_put_contents($targetPath, $decrypted);
    }
}
