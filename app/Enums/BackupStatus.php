<?php

namespace App\Enums;

enum BackupStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Corrupted = 'corrupted';
    case Restoring = 'restoring';
    case Restored = 'restored';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Corrupted => 'Corrupted',
            self::Restoring => 'Restoring',
            self::Restored => 'Restored',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Completed => 'emerald',
            self::Running, self::Restoring => 'amber',
            self::Pending => 'slate',
            self::Restored => 'blue',
            self::Failed, self::Corrupted => 'rose',
        };
    }
}
