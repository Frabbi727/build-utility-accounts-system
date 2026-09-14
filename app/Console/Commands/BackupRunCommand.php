<?php

namespace App\Console\Commands;

use App\Enums\BackupType;
use App\Services\Backup\BackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:run 
                            {--type=automatic : The type of backup (automatic, manual, safety)}
                            {--manual : Run as manual backup}
                            {--no-files : Skip including persistent storage files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a complete database and persistent data backup';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        $this->info('Starting backup process...');

        $typeString = $this->option('manual') ? 'manual' : (string) $this->option('type');
        $backupType = match (strtolower($typeString)) {
            'manual' => BackupType::Manual,
            'safety' => BackupType::Safety,
            default => BackupType::Automatic,
        };

        $includeFiles = ! $this->option('no-files');

        try {
            $backup = $backupService->create(
                type: $backupType,
                creator: null,
                options: ['include_files' => $includeFiles]
            );

            $this->info("✓ Backup completed successfully: {$backup->backup_name}");
            $this->table(
                ['Attribute', 'Value'],
                [
                    ['ID', $backup->id],
                    ['Name', $backup->backup_name],
                    ['Type', $backup->backup_type->label()],
                    ['Size', $backup->formattedSize()],
                    ['Location', $backup->file_path],
                    ['Checksum (SHA-256)', $backup->checksum],
                    ['Created At', $backup->formattedCreatedAt()],
                ]
            );

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error('✗ Backup failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
