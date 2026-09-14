<?php

namespace App\Console\Commands;

use App\Models\Backup;
use Illuminate\Console\Command;

class BackupListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all recorded database and data backups';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $backups = Backup::orderBy('created_at', 'desc')->take(20)->get();

        if ($backups->isEmpty()) {
            $this->info('No backups found in the system.');

            return Command::SUCCESS;
        }

        $rows = $backups->map(fn (Backup $b) => [
            $b->id,
            $b->backup_name,
            $b->backup_type->label(),
            $b->formattedSize(),
            $b->status->label(),
            $b->is_protected ? 'Yes' : 'No',
            $b->formattedCreatedAt(),
        ]);

        $this->table(
            ['ID', 'Name', 'Type', 'Size', 'Status', 'Protected', 'Created At'],
            $rows
        );

        return Command::SUCCESS;
    }
}
