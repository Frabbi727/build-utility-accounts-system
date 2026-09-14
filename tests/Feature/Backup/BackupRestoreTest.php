<?php

namespace Tests\Feature\Backup;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Enums\RestoreStatus;
use App\Models\Backup;
use App\Models\Building;
use App\Models\User;
use App\Services\Backup\BackupService;
use App\Services\Backup\RestoreService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_can_restore_backup_with_safety_backup_creation(): void
    {
        $admin = User::factory()->create(['name' => 'Initial Admin']);
        $admin->assignRole('admin');

        $building = Building::factory()->create(['name' => 'Building Before Backup']);

        // Create a backup containing this initial state
        $backupService = app(BackupService::class);
        $backup = $backupService->create(creator: $admin);

        // Now mutate state in DB
        $building->update(['name' => 'Modified Building Name']);
        $admin->update(['name' => 'Modified Admin Name']);

        $this->assertEquals('Modified Building Name', $building->fresh()->name);

        // Perform restore
        $restoreService = app(RestoreService::class);
        $result = $restoreService->restore($backup, $admin);

        $this->assertNotNull($result['safety_backup']);
        $this->assertEquals(BackupType::Safety, $result['safety_backup']->backup_type);
        $this->assertEquals(RestoreStatus::Completed, $backup->fresh()->restore_status);

        // Verify data restored back to backup state!
        $this->assertEquals('Building Before Backup', Building::first()->name);
        $this->assertEquals('Initial Admin', User::first()->name);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'BACKUP_RESTORED',
            'user_id' => $admin->id,
        ]);
    }

    public function test_cannot_restore_corrupted_backup(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $backup = Backup::factory()->create([
            'status' => BackupStatus::Completed,
            'file_path' => 'backups/corrupted.zip',
        ]);

        $restoreService = app(RestoreService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Selected backup failed integrity check');

        $restoreService->restore($backup, $admin);
    }
}
