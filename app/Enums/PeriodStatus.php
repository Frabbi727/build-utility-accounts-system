<?php

namespace App\Enums;

enum PeriodStatus: string
{
    case Open = 'open';
    case Locked = 'locked';
}
