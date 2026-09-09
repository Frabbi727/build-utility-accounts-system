<?php

namespace App\Enums;

enum MaintenanceCategory: string
{
    case Plumbing = 'plumbing';
    case Electrical = 'electrical';
    case Elevator = 'elevator';
    case Cleaning = 'cleaning';
    case Security = 'security';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Plumbing => 'Plumbing',
            self::Electrical => 'Electrical',
            self::Elevator => 'Elevator',
            self::Cleaning => 'Cleaning',
            self::Security => 'Security',
            self::Other => 'Other',
        };
    }
}
