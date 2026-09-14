<?php

namespace App\Console\Commands;

use App\Services\Backup\RetentionManager;
use Illuminate\Console\Command;
use Throwable;

class BackupCleanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old backups according to the retention policy';

    /**
     * Execute the console command.
     */
    public function handle(RetentionManager $retentionManager): int
    {
        $this->info('Cleaning expired backups according to retention policy...');

        try {
            $stats = $retentionManager->clean();

            $this->info("✓ Clean completed. Pruned {$stats['deleted_count']} backups, preserved {$stats['preserved_count']} backups.");

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error('✗ Retention cleaning failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
