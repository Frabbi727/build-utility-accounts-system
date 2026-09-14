<?php

namespace Tests\Feature\Backup;

use App\Services\Backup\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BackupConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_backup_attempt_is_rejected_when_locked(): void
    {
        // Simulate an existing lock acquired by a running backup
        $lock = Cache::lock('backup_operation_lock', 600);
        $this->assertTrue($lock->get());

        $backupService = app(BackupService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Another backup or restore operation is currently in progress');

        try {
            $backupService->create();
        } finally {
            $lock->release();
        }
    }
}
