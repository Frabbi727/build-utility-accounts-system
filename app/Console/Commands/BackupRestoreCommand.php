<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Services\Backup\RestoreService;
use Illuminate\Console\Command;
use Throwable;

class BackupRestoreCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:restore 
                            {id : The ID or unique name of the backup to restore}
                            {--force : Bypass interactive confirmation}
                            {--skip-safety : Skip creating safety backup before restore (NOT RECOMMENDED)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore the system database and persistent files from a designated backup';

    /**
     * Execute the console command.
     */
    public function handle(RestoreService $restoreService): int
    {
        $identifier = $this->argument('id');

        $backup = is_numeric($identifier)
            ? Backup::find((int) $identifier)
            : Backup::where('backup_name', $identifier)->first();

        if (! $backup) {
            $this->error("Backup [{$identifier}] not found.");

            return Command::FAILURE;
        }

        if (! $backup->isCompleted()) {
            $this->error("Cannot restore backup [{$backup->backup_name}] with status [{$backup->status->value}].");

            return Command::FAILURE;
        }

        $this->warn('================================================================');
        $this->warn('                    CRITICAL WARNING                             ');
        $this->warn('================================================================');
        $this->warn("You are about to restore the system to backup [{$backup->backup_name}] ({$backup->formattedCreatedAt()}).");
        $this->warn('Current database tables and files will be replaced.');
        $this->warn('A safety backup of current production state will be created first.');
        $this->warn('================================================================');

        if (! $this->option('force')) {
            $input = $this->ask('To confirm this restoration, please type "RESTORE" (or Ctrl+C to cancel)');
            if (trim((string) $input) !== 'RESTORE') {
                $this->info('Restoration cancelled: Confirmation word did not match.');

                return Command::SUCCESS;
            }
        }

        $this->info('Initiating structured 4-step restoration sequence:');
        $this->line(' [1/4] Validating archive integrity and SHA-256 checksum...');
        $this->line(' [2/4] Creating pre-restore production safety snapshot...');
        $this->line(' [3/4] Restoring PostgreSQL database and synchronizing storage files...');
        $this->line(' [4/4] Executing schema validation and post-restore health checks...');

        try {
            $result = $restoreService->restore(
                backup: $backup,
                actor: null,
                options: [
                    'skip_safety_backup' => (bool) $this->option('skip-safety'),
                ]
            );

            $this->info('✓ '.$result['message']);
            if ($result['safety_backup']) {
                $this->info("✓ Safety backup saved as: {$result['safety_backup']->backup_name}");
            }

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error('✗ Restoration failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
