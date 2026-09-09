<?php

namespace App\Enums;

enum NoticeType: string
{
    case General = 'general';
    case Maintenance = 'maintenance';
    case Emergency = 'emergency';
    case Event = 'event';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Maintenance => 'Maintenance',
            self::Emergency => 'Emergency',
            self::Event => 'Event',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::General => 'bg-slate-100 text-slate-700',
            self::Maintenance => 'bg-amber-100 text-amber-800',
            self::Emergency => 'bg-red-100 text-red-800',
            self::Event => 'bg-emerald-100 text-emerald-800',
        };
    }
}
