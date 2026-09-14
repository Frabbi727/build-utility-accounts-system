<?php

namespace Tests\Feature\Backup;

use App\Enums\BackupStatus;
use App\Models\Backup;
use App\Services\Backup\RetentionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_retention_clean_prunes_old_backups_beyond_threshold(): void
    {
        config([
            'backup.retention.daily' => 5,
            'backup.retention.weekly' => 2,
            'backup.retention.monthly' => 1,
            'backup.retention.keep_minimum' => 1,
        ]);

        // Create an old backup from 60 days ago
        $oldBackup = Backup::factory()->create([
            'status' => BackupStatus::Completed,
            'created_at' => now()->subDays(60),
            'is_protected' => false,
        ]);
        Storage::disk('local')->put($oldBackup->file_path, 'dummy zip content');

        // Create a recent backup from 1 day ago
        $recentBackup = Backup::factory()->create([
            'status' => BackupStatus::Completed,
            'created_at' => now()->subDays(1),
            'is_protected' => false,
        ]);
        Storage::disk('local')->put($recentBackup->file_path, 'dummy zip content');

        $retentionManager = app(RetentionManager::class);
        $stats = $retentionManager->clean();

        $this->assertEquals(1, $stats['deleted_count']);
        $this->assertDatabaseMissing('backups', ['id' => $oldBackup->id]);
        $this->assertDatabaseHas('backups', ['id' => $recentBackup->id]);
    }

    public function test_retention_never_prunes_latest_or_protected_backups(): void
    {
        config([
            'backup.retention.daily' => 1,
            'backup.retention.keep_minimum' => 1,
        ]);

        // Protected old backup
        $protectedOld = Backup::factory()->create([
            'status' => BackupStatus::Completed,
            'created_at' => now()->subDays(100),
            'is_protected' => true,
        ]);
        Storage::disk('local')->put($protectedOld->file_path, 'dummy zip');

        // Oldest single completed backup (which is also the latest completed backup)
        $retentionManager = app(RetentionManager::class);
        $stats = $retentionManager->clean();

        $this->assertEquals(0, $stats['deleted_count']);
        $this->assertDatabaseHas('backups', ['id' => $protectedOld->id]);
    }
}
