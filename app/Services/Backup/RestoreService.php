<?php

namespace App\Services\Backup;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Enums\RestoreStatus;
use App\Models\Backup;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;
use ZipArchive;

class RestoreService
{
    public function __construct(
        private readonly BackupService $backupService,
        private readonly DatabaseDumpService $databaseDumpService,
        private readonly AuditService $auditService,
    ) {}

    /**
     * Restore database and persistent files from a designated backup.
     * Always takes a safety backup before applying any destructive changes.
     *
     * @param  array<string, mixed>  $options
     * @return array{safety_backup: Backup|null, restored_backup: Backup, message: string}
     */
    public function restore(
        Backup $backup,
        ?User $actor = null,
        array $options = [],
    ): array {
        // Concurrency protection: exclusive restore lock
        $lock = Cache::lock('backup_operation_lock', 900);

        if (! $lock->get()) {
            throw new RuntimeException('Another backup or restore operation is in progress. Concurrency lock acquired.');
        }

        $skipSafetyBackup = $options['skip_safety_backup'] ?? false;
        $safetyBackup = null;
        $tempRestoreDir = storage_path('app/temp/restore_'.uniqid($backup->backup_name.'_', true));

        try {
            // Step 1: Pre-restore verification of target backup
            $verification = $this->backupService->verify($backup);
            if (! $verification['valid']) {
                throw new RuntimeException("Selected backup failed integrity check: {$verification['message']}");
            }

            // Step 2: Create safety backup of current production state before touching data
            if (! $skipSafetyBackup) {
                Log::info("Creating safety backup before restoring [{$backup->backup_name}]...");
                $safetyBackup = $this->backupService->create(
                    type: BackupType::Safety,
                    creator: $actor,
                    options: [
                        'skip_lock' => true,
                        'include_files' => true,
                    ]
                );

                // Verify safety backup
                $safetyVerification = $this->backupService->verify($safetyBackup);
                if (! $safetyVerification['valid']) {
                    throw new RuntimeException("Safety backup integrity verification failed! Aborting restore to prevent data loss. Error: {$safetyVerification['message']}");
                }
            }

            // Mark target backup as restoring
            $backup->update([
                'status' => BackupStatus::Restoring,
                'restore_status' => RestoreStatus::Running,
            ]);

            File::ensureDirectoryExists($tempRestoreDir);

            // Step 3: Extract target backup to temporary staging
            $extractedDir = $this->extractBackupArchive($backup, $tempRestoreDir);

            // Verify extracted manifest and SQL
            $sqlFile = "{$extractedDir}/database.sql";
            if (! file_exists($sqlFile)) {
                throw new RuntimeException('Corrupted backup archive: database.sql is missing.');
            }

            // Step 4: Execute Database Restore
            Log::info("Restoring database from [{$backup->backup_name}]...");
            $this->databaseDumpService->restore($sqlFile);

            // Step 5: Restore Persistent Files
            $filesDir = "{$extractedDir}/files";
            if (is_dir($filesDir)) {
                Log::info("Restoring application persistent files from [{$backup->backup_name}]...");
                $this->restorePersistentFiles($filesDir);
            }

            // Step 6: Validate Restored System
            $this->validateRestoredSystem();

            // Step 7: Clear application caches
            $this->clearSystemCaches();

            // Step 8: Update metadata and status
            $backup->update([
                'status' => BackupStatus::Completed,
                'restore_status' => RestoreStatus::Completed,
                'restored_at' => now(),
            ]);

            // Step 9: Audit log
            $this->auditService->record(
                action: 'BACKUP_RESTORED',
                module: 'backup',
                entity: $backup,
                description: "Successfully restored system to backup [{$backup->backup_name}]. Safety backup: [{$safetyBackup?->backup_name}].",
                payload: [
                    'backup_id' => $backup->id,
                    'backup_name' => $backup->backup_name,
                    'safety_backup_id' => $safetyBackup?->id,
                    'safety_backup_name' => $safetyBackup?->backup_name,
                    'restored_at' => now()->toIso8601String(),
                ],
                userId: $actor?->id,
            );

            return [
                'safety_backup' => $safetyBackup,
                'restored_backup' => $backup,
                'message' => "System successfully restored to backup [{$backup->backup_name}].",
            ];
        } catch (Throwable $e) {
            $error = $e->getMessage();
            Log::critical("Restore failed for backup [{$backup->backup_name}]: {$error}", [
                'exception' => $e,
            ]);

            $backup->update([
                'status' => BackupStatus::Completed, // keep valid archive status
                'restore_status' => RestoreStatus::Failed,
                'error_message' => "Restore failed: {$error}",
            ]);

            // Auto-recovery: If safety backup exists, rollback to safety backup state!
            if ($safetyBackup && ! $skipSafetyBackup) {
                Log::critical("Initiating emergency recovery from safety backup [{$safetyBackup->backup_name}]...");
                try {
                    $this->emergencyRollback($safetyBackup);
                    $backup->update(['restore_status' => RestoreStatus::Recovered]);
                    Log::info("Emergency recovery from safety backup [{$safetyBackup->backup_name}] succeeded.");
                } catch (Throwable $rollbackError) {
                    Log::emergency("CRITICAL DISASTER: Emergency rollback from safety backup failed: {$rollbackError->getMessage()}");
                }
            }

            $this->auditService->record(
                action: 'BACKUP_RESTORE_FAILED',
                module: 'backup',
                entity: $backup,
                description: "CRITICAL: Failed to restore backup [{$backup->backup_name}]. Error: {$error}",
                payload: [
                    'backup_id' => $backup->id,
                    'backup_name' => $backup->backup_name,
                    'error' => $error,
                    'safety_backup_id' => $safetyBackup?->id,
                ],
                userId: $actor?->id,
            );

            throw new RuntimeException("Restore failed: {$error}. Safety recovery executed.", 0, $e);
        } finally {
            if (File::isDirectory($tempRestoreDir)) {
                File::deleteDirectory($tempRestoreDir);
            }

            $lock->release();
        }
    }

    /**
     * Extract backup archive to temporary target directory.
     */
    protected function extractBackupArchive(Backup $backup, string $tempRestoreDir): string
    {
        $disk = Storage::disk($backup->disk);
        $archiveContent = $disk->get($backup->file_path);

        if ($archiveContent === null) {
            throw new RuntimeException("Failed to read backup archive from storage: {$backup->file_path}");
        }

        $localZipPath = "{$tempRestoreDir}/archive.zip";
        file_put_contents($localZipPath, $archiveContent);

        // If encrypted, decrypt first
        $isEncrypted = $backup->metadata['encrypted'] ?? false;
        if ($isEncrypted) {
            $decryptedZipPath = "{$tempRestoreDir}/decrypted.zip";
            $this->backupService->decryptFile($localZipPath, $decryptedZipPath);
            $localZipPath = $decryptedZipPath;
        }

        $extractTarget = "{$tempRestoreDir}/extracted";
        File::ensureDirectoryExists($extractTarget);

        $zip = new ZipArchive;
        $res = $zip->open($localZipPath);

        if ($res !== true) {
            throw new RuntimeException("Failed to open backup ZIP archive (error code: {$res})");
        }

        $zip->extractTo($extractTarget);
        $zip->close();

        return $extractTarget;
    }

    /**
     * Restore persistent files into storage/app directories.
     */
    protected function restorePersistentFiles(string $extractedFilesDir): void
    {
        $directories = config('backup.files_directories', ['public']);

        foreach ($directories as $dir) {
            $sourceDir = "{$extractedFilesDir}/{$dir}";
            if (is_dir($sourceDir)) {
                $targetDir = storage_path("app/{$dir}");
                File::ensureDirectoryExists($targetDir);
                $this->copyDirectory($sourceDir, $targetDir);
            }
        }
    }

    /**
     * Post-restore validation to ensure database and application integrity.
     */
    protected function validateRestoredSystem(): void
    {
        // 1. Verify database connection
        DB::connection()->getPdo();

        // 2. Verify critical tables exist
        $requiredTables = [
            'users',
            'accounts',
            'buildings',
            'flats',
            'floors',
            'journal_entries',
            'journal_lines',
            'service_charge_bills',
            'bill_items',
            'payments',
            'audit_logs',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Restore validation failed: required table [{$table}] is missing in database.");
            }
        }

        // 3. Verify at least one user exists
        $userCount = DB::table('users')->count();
        if ($userCount === 0) {
            throw new RuntimeException('Restore validation failed: no user records found in restored database.');
        }

        // 4. Verify admin user exists
        $adminUserCount = DB::table('roles')
            ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'admin')
            ->count();

        if ($adminUserCount === 0) {
            Log::warning('Restore validation notice: No user has explicit admin role in restored data.');
        }
    }

    /**
     * Clear caches post restore.
     */
    protected function clearSystemCaches(): void
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
        } catch (Throwable $e) {
            Log::warning('Non-fatal error clearing cache post restore: '.$e->getMessage());
        }
    }

    /**
     * Emergency rollback to safety backup.
     */
    protected function emergencyRollback(Backup $safetyBackup): void
    {
        $tempDir = storage_path('app/temp/safety_rollback_'.uniqid());
        File::ensureDirectoryExists($tempDir);

        try {
            $extractedDir = $this->extractBackupArchive($safetyBackup, $tempDir);
            $sqlFile = "{$extractedDir}/database.sql";

            if (file_exists($sqlFile)) {
                $this->databaseDumpService->restore($sqlFile);
            }

            $filesDir = "{$extractedDir}/files";
            if (is_dir($filesDir)) {
                $this->restorePersistentFiles($filesDir);
            }

            $this->validateRestoredSystem();
            $this->clearSystemCaches();
        } finally {
            if (File::isDirectory($tempDir)) {
                File::deleteDirectory($tempDir);
            }
        }
    }

    /**
     * Recursively copy directory.
     */
    private function copyDirectory(string $source, string $destination): void
    {
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
            }
        }
    }
}
