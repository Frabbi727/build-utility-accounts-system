<?php

namespace Tests\Feature\Backup;

use App\Enums\BackupStatus;
use App\Livewire\Admin\BackupList;
use App\Models\Backup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class BackupAdminLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'accountant']);
    }

    public function test_admin_can_view_backup_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $backup = Backup::factory()->create(['backup_name' => 'uas_prod_test_01']);

        $this->actingAs($admin)
            ->get(route('admin.backups'))
            ->assertOk()
            ->assertSee('uas_prod_test_01');
    }

    public function test_non_admin_cannot_view_backup_dashboard(): void
    {
        $accountant = User::factory()->create();
        $accountant->assignRole('accountant');

        $this->actingAs($accountant)
            ->get(route('admin.backups'))
            ->assertForbidden();
    }

    public function test_admin_can_create_manual_backup_from_ui(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(BackupList::class)
            ->call('createManualBackup')
            ->assertSet('statusMessageType', 'success');

        $this->assertDatabaseHas('backups', [
            'status' => BackupStatus::Completed->value,
            'created_by' => $admin->id,
        ]);
    }

    public function test_admin_can_toggle_protection_and_delete_backup(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $backup = Backup::factory()->create([
            'is_protected' => false,
            'disk' => 'local',
        ]);
        Storage::disk('local')->put($backup->file_path, 'sample archive');

        Livewire::actingAs($admin)
            ->test(BackupList::class)
            ->call('toggleProtection', $backup->id)
            ->assertSet('statusMessageType', 'success');

        $this->assertTrue($backup->fresh()->is_protected);

        // Unprotect
        Livewire::actingAs($admin)
            ->test(BackupList::class)
            ->call('toggleProtection', $backup->id);

        $this->assertFalse($backup->fresh()->is_protected);

        // Delete
        Livewire::actingAs($admin)
            ->test(BackupList::class)
            ->call('openDeleteModal', $backup->id)
            ->call('confirmDelete')
            ->assertSet('statusMessageType', 'success');

        $this->assertDatabaseMissing('backups', ['id' => $backup->id]);
    }

    public function test_admin_can_upload_and_register_backup_archive(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Create a genuine sample backup zip archive for upload
        $tempZipPath = tempnam(sys_get_temp_dir(), 'test_upload_').'.zip';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tempZipPath, ZipArchive::CREATE));
        $zip->addFromString('database.sql', '-- sample uploaded sql dump');
        $zip->addFromString('manifest.json', json_encode([
            'backup_name' => 'custom_uploaded_backup_2026',
            'environment' => 'production',
            'tables_count' => 10,
        ]));
        $zip->close();

        $uploadedFile = UploadedFile::fake()->createWithContent(
            'custom_uploaded_backup_2026.zip',
            file_get_contents($tempZipPath)
        );

        Livewire::actingAs($admin)
            ->test(BackupList::class)
            ->set('uploadedFile', $uploadedFile)
            ->call('processUpload')
            ->assertSet('statusMessageType', 'success');

        $this->assertDatabaseHas('backups', [
            'backup_name' => 'custom_uploaded_backup_2026',
            'status' => BackupStatus::Completed->value,
        ]);

        @unlink($tempZipPath);
    }

    public function test_restore_modal_requires_both_checkbox_and_typed_confirmation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $backup = Backup::factory()->create();

        // Without confirmation phrase or checkbox
        Livewire::actingAs($admin)
            ->test(BackupList::class)
            ->call('openRestoreModal', $backup->id)
            ->call('confirmRestore')
            ->assertSet('statusMessageType', 'error');

        // With checkbox but wrong confirmation phrase
        Livewire::actingAs($admin)
            ->test(BackupList::class)
            ->call('openRestoreModal', $backup->id)
            ->set('restoreConfirmed', true)
            ->set('restoreConfirmationPhrase', 'WRONG')
            ->call('confirmRestore')
            ->assertSet('statusMessageType', 'error');
    }
}
