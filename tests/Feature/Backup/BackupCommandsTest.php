<?php

namespace Tests\Feature\Backup;

use App\Enums\BackupStatus;
use App\Models\Backup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_backup_run_command_executes_successfully(): void
    {
        $this->artisan('backup:run', ['--manual' => true])
            ->expectsOutputToContain('Backup completed successfully')
            ->assertExitCode(0);

        $this->assertDatabaseHas('backups', [
            'status' => BackupStatus::Completed->value,
        ]);
    }

    public function test_backup_list_command_displays_backups(): void
    {
        $backup = Backup::factory()->create(['backup_name' => 'test_backup_list_001']);

        $this->artisan('backup:list')
            ->expectsOutputToContain('test_backup_list_001')
            ->assertExitCode(0);
    }

    public function test_backup_clean_command_executes_cleanly(): void
    {
        $this->artisan('backup:clean')
            ->expectsOutputToContain('Clean completed')
            ->assertExitCode(0);
    }

    public function test_backup_restore_command_aborts_if_confirmation_phrase_does_not_match(): void
    {
        $backup = Backup::factory()->create(['status' => BackupStatus::Completed]);

        $this->artisan('backup:restore', ['id' => $backup->id])
            ->expectsQuestion('To confirm this restoration, please type "RESTORE" (or Ctrl+C to cancel)', 'NO')
            ->expectsOutputToContain('Restoration cancelled: Confirmation word did not match.')
            ->assertExitCode(0);
    }
}
