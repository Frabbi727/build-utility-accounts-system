<?php

namespace App\Exceptions;

use App\Models\AccountingPeriod;
use RuntimeException;

class PeriodLockedException extends RuntimeException
{
    /** The period that refused the post, so callers can name it to the operator. */
    public ?AccountingPeriod $period = null;

    public static function for(AccountingPeriod $period): self
    {
        $exception = new self(
            "Accounting period {$period->year}-{$period->month} is locked; post an adjusting entry instead."
        );

        $exception->period = $period;

        return $exception;
    }
}
