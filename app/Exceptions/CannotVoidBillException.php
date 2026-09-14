<?php

namespace App\Exceptions;

use App\Models\ServiceChargeBill;
use DomainException;

class CannotVoidBillException extends DomainException
{
    public static function becausePaid(ServiceChargeBill $bill): self
    {
        return new self("Cannot void bill {$bill->bill_no} because it has received payments.");
    }

    public static function becausePeriodLocked(ServiceChargeBill $bill): self
    {
        return new self("Cannot void bill {$bill->bill_no} because the accounting period is locked.");
    }

    public static function becauseAlreadyVoided(ServiceChargeBill $bill): self
    {
        return new self("Cannot void bill {$bill->bill_no} because it is already voided.");
    }
}
