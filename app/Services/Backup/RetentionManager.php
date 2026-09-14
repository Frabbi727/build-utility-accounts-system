<?php

namespace App\Services\Backup;

use App\Enums\BackupStatus;
use App\Models\Backup;
use App\Services\Audit\AuditService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RetentionManager
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * Clean old backups according to the retention policy.
     *
     * @return array{deleted_count: int, freed_bytes: int, preserved_count: int}
     */
    public function clean(): array
    {
        $keepMinimum = (int) config('backup.retention.keep_minimum', 1);
        $dailyDays = (int) config('backup.retention.daily', 30);
        $weeklyWeeks = (int) config('backup.retention.weekly', 12);
        $monthlyMonths = (int) config('backup.retention.monthly', 12);

        $now = now();
        $dailyThreshold = $now->copy()->subDays($dailyDays);
        $weeklyThreshold = $now->copy()->subWeeks($weeklyWeeks);
        $monthlyThreshold = $now->copy()->subMonths($monthlyMonths);

        // Get all completed backups ordered from oldest to newest
        $allCompleted = Backup::query()
            ->where('status', BackupStatus::Completed)
            ->orderBy('created_at', 'asc')
            ->get();

        $totalCompleted = $allCompleted->count();

        if ($totalCompleted <= $keepMinimum) {
            return [
                'deleted_count' => 0,
                'freed_bytes' => 0,
                'preserved_count' => $totalCompleted,
            ];
        }

        // Latest backup must NEVER be deleted
        $latestBackupId = $allCompleted->last()?->id;

        $toDelete = collect();
        $weeklyBuckets = [];
        $monthlyBuckets = [];

        foreach ($allCompleted as $backup) {
            // Rule: Never delete protected backups or latest backup
            if ($backup->is_protected || $backup->id === $latestBackupId) {
                continue;
            }

            /** @var Carbon $createdAt */
            $createdAt = $backup->created_at ?? $now;

            // Tier 1: Within daily retention window -> KEEP
            if ($createdAt->greaterThanOrEqualTo($dailyThreshold)) {
                continue;
            }

            // Tier 2: Between daily & weekly threshold -> KEEP ONE PER WEEK (e.g. Sunday or first of week)
            if ($createdAt->greaterThanOrEqualTo($weeklyThreshold)) {
                $weekKey = $createdAt->format('o-W'); // Year-Week number
                if (! isset($weeklyBuckets[$weekKey])) {
                    $weeklyBuckets[$weekKey] = $backup->id; // keep the first one seen in this week
                } else {
                    $toDelete->push($backup);
                }

                continue;
            }

            // Tier 3: Between weekly & monthly threshold -> KEEP ONE PER MONTH
            if ($createdAt->greaterThanOrEqualTo($monthlyThreshold)) {
                $monthKey = $createdAt->format('Y-m');
                if (! isset($monthlyBuckets[$monthKey])) {
                    $monthlyBuckets[$monthKey] = $backup->id; // keep the first one seen in this month
                } else {
                    $toDelete->push($backup);
                }

                continue;
            }

            // Tier 4: Older than monthly threshold -> DELETE
            $toDelete->push($backup);
        }

        // Ensure we don't delete below keep_minimum
        $remainingCount = $totalCompleted - $toDelete->count();
        if ($remainingCount < $keepMinimum) {
            $excessToDelete = $toDelete->count() - ($totalCompleted - $keepMinimum);
            $toDelete = $toDelete->slice(0, $toDelete->count() - $excessToDelete);
        }

        $deletedCount = 0;
        $freedBytes = 0;

        foreach ($toDelete as $backup) {
            try {
                // Delete file from primary disk
                if (Storage::disk($backup->disk)->exists($backup->file_path)) {
                    Storage::disk($backup->disk)->delete($backup->file_path);
                }

                // Delete from remote disk if configured
                $remoteDisk = config('backup.remote_disk');
                if ($remoteDisk && Storage::disk($remoteDisk)->exists($backup->file_path)) {
                    Storage::disk($remoteDisk)->delete($backup->file_path);
                }

                $freedBytes += $backup->file_size;
                $deletedCount++;

                $this->auditService->record(
                    action: 'BACKUP_RETENTION_PRUNED',
                    module: 'backup',
                    entity: $backup,
                    description: "Pruned old backup [{$backup->backup_name}] under retention policy (freed {$backup->formattedSize()}).",
                    payload: [
                        'backup_name' => $backup->backup_name,
                        'file_size' => $backup->file_size,
                        'created_at' => $backup->created_at?->toIso8601String(),
                    ],
                );

                $backup->delete();
            } catch (Throwable $e) {
                Log::error("Failed to prune backup [{$backup->backup_name}]: {$e->getMessage()}");
            }
        }

        return [
            'deleted_count' => $deletedCount,
            'freed_bytes' => $freedBytes,
            'preserved_count' => $totalCompleted - $deletedCount,
        ];
    }
}
