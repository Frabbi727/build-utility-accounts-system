<?php

namespace App\Models;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Enums\RestoreStatus;
use Database\Factories\BackupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $backup_name
 * @property BackupType $backup_type
 * @property string $environment
 * @property string $file_path
 * @property string $disk
 * @property int $file_size
 * @property string|null $database_name
 * @property string|null $checksum
 * @property BackupStatus $status
 * @property RestoreStatus|null $restore_status
 * @property string|null $error_message
 * @property bool $is_protected
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $retention_date
 * @property int|null $created_by
 * @property Carbon|null $completed_at
 * @property Carbon|null $restored_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable([
    'backup_name',
    'backup_type',
    'environment',
    'file_path',
    'disk',
    'file_size',
    'database_name',
    'checksum',
    'status',
    'restore_status',
    'error_message',
    'is_protected',
    'metadata',
    'retention_date',
    'created_by',
    'completed_at',
    'restored_at',
])]
class Backup extends Model
{
    /** @use HasFactory<BackupFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'backup_type' => BackupType::class,
            'status' => BackupStatus::class,
            'restore_status' => RestoreStatus::class,
            'metadata' => 'array',
            'is_protected' => 'boolean',
            'file_size' => 'integer',
            'retention_date' => 'datetime',
            'completed_at' => 'datetime',
            'restored_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get human-readable file size.
     */
    public function formattedSize(): string
    {
        $bytes = $this->file_size;

        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / pow(1024, $i), 2).' '.$units[$i];
    }

    /**
     * Format timestamp in local Asia/Dhaka application timezone.
     */
    public function formattedCreatedAt(string $format = 'd M Y, h:i A'): string
    {
        return $this->created_at ? $this->created_at->copy()->timezone('Asia/Dhaka')->format($format) : '—';
    }

    public function isCompleted(): bool
    {
        return $this->status === BackupStatus::Completed;
    }

    public function isFailed(): bool
    {
        return $this->status === BackupStatus::Failed || $this->status === BackupStatus::Corrupted;
    }

    public function isRestorable(): bool
    {
        if ($this->status !== BackupStatus::Completed) {
            return false;
        }

        return Storage::disk($this->disk)->exists($this->file_path);
    }
}
