<?php

namespace Tests\Feature\Backup;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\User;
use App\Services\Backup\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class BackupCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_can_create_automatic_backup_successfully(): void
    {
        $backupService = app(BackupService::class);

        // Put dummy persistent file
        $dummyPublicFile = storage_path('app/public/test_receipt.txt');
        File::ensureDirectoryExists(dirname($dummyPublicFile));
        file_put_contents($dummyPublicFile, 'sample receipt data');

        $backup = $backupService->create(type: BackupType::Automatic);

        $this->assertDatabaseHas('backups', [
            'id' => $backup->id,
            'backup_type' => BackupType::Automatic->value,
            'status' => BackupStatus::Completed->value,
            'environment' => app()->environment(),
        ]);

        $this->assertNotNull($backup->checksum);
        $this->assertGreaterThan(0, $backup->file_size);
        $this->assertTrue(Storage::disk('local')->exists($backup->file_path));

        // Verify ZIP contents
        $archiveStream = Storage::disk('local')->get($backup->file_path);
        $tempZip = tempnam(sys_get_temp_dir(), 'test_zip_');
        file_put_contents($tempZip, $archiveStream);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tempZip));
        $this->assertNotFalse($zip->locateName('database.sql'));
        $this->assertNotFalse($zip->locateName('manifest.json'));
        $zip->close();
        @unlink($tempZip);

        // Clean up dummy
        if (file_exists($dummyPublicFile)) {
            @unlink($dummyPublicFile);
        }
    }

    public function test_can_create_manual_backup_with_creator_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $backupService = app(BackupService::class);
        $backup = $backupService->create(type: BackupType::Manual, creator: $admin);

        $this->assertEquals(BackupType::Manual, $backup->backup_type);
        $this->assertEquals($admin->id, $backup->created_by);
        $this->assertEquals(BackupStatus::Completed, $backup->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'BACKUP_CREATED',
            'user_id' => $admin->id,
        ]);
    }

    public function test_fails_when_backup_disabled_in_config(): void
    {
        config(['backup.enabled' => false]);

        $backupService = app(BackupService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Backup system is currently disabled');

        $backupService->create();
    }
}
