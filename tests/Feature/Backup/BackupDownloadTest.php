<?php

namespace Tests\Feature\Backup;

use App\Enums\BackupStatus;
use App\Models\Backup;
use App\Models\User;
use App\Services\Backup\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackupDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'accountant']);
        Role::firstOrCreate(['name' => 'owner']);
    }

    public function test_admin_can_download_valid_backup(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $backupService = app(BackupService::class);
        $backup = $backupService->create(creator: $admin);

        $response = $this->actingAs($admin)->get(route('admin.backups.download', $backup));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/zip');
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'BACKUP_DOWNLOADED',
            'user_id' => $admin->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_download_backup(): void
    {
        $backupService = app(BackupService::class);
        $backup = $backupService->create();

        $response = $this->get(route('admin.backups.download', $backup));

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_user_cannot_download_backup(): void
    {
        $accountant = User::factory()->create();
        $accountant->assignRole('accountant');

        $backupService = app(BackupService::class);
        $backup = $backupService->create();

        $response = $this->actingAs($accountant)->get(route('admin.backups.download', $backup));

        $response->assertForbidden();
    }

    public function test_download_fails_if_backup_corrupted_or_missing(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $backup = Backup::factory()->create([
            'status' => BackupStatus::Completed,
            'file_path' => 'backups/non_existent.zip',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.backups.download', $backup));

        $response->assertNotFound();
    }
}
