<?php

namespace App\Enums;

enum BackupType: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';
    case Safety = 'safety';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Automatic',
            self::Manual => 'Manual',
            self::Safety => 'Safety',
        };
    }
}
