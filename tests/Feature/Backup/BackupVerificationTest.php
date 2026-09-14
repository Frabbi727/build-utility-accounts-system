<?php

namespace Tests\Feature\Backup;

use App\Enums\BackupStatus;
use App\Models\Backup;
use App\Services\Backup\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_verify_returns_true_for_valid_backup(): void
    {
        $backupService = app(BackupService::class);
        $backup = $backupService->create();

        $result = $backupService->verify($backup);

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['checksum_matches']);
        $this->assertTrue($result['file_exists']);
        $this->assertGreaterThan(0, $result['size_bytes']);
    }

    public function test_verify_detects_missing_file(): void
    {
        $backup = Backup::factory()->create([
            'disk' => 'local',
            'file_path' => 'backups/missing_archive.zip',
            'status' => BackupStatus::Completed,
        ]);

        $backupService = app(BackupService::class);
        $result = $backupService->verify($backup);

        $this->assertFalse($result['valid']);
        $this->assertFalse($result['file_exists']);
    }

    public function test_verify_detects_tampered_or_corrupted_checksum(): void
    {
        $backupService = app(BackupService::class);
        $backup = $backupService->create();

        // Tamper with checksum in database
        $backup->update(['checksum' => '0000000000000000000000000000000000000000000000000000000000000000']);

        $result = $backupService->verify($backup);

        $this->assertFalse($result['valid']);
        $this->assertFalse($result['checksum_matches']);
        $this->assertStringContainsString('Checksum mismatch', $result['message']);
    }
}
