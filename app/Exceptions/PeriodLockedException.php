<?php

namespace App\Exceptions;

use App\Models\AccountingPeriod;
use RuntimeException;

class PeriodLockedException extends RuntimeException
{
    public static function for(AccountingPeriod $period): self
    {
        return new self(
            "Accounting period {$period->year}-{$period->month} is locked; post an adjusting entry instead."
        );
    }
}
