<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Bkash = 'bkash';
    case Nagad = 'nagad';

    /**
     * The chart-of-accounts code that money received by this method lands in.
     */
    public function accountCode(): AccountCode
    {
        return match ($this) {
            self::Cash => AccountCode::CashInHand,
            self::Bank, self::Bkash, self::Nagad => AccountCode::Bank,
        };
    }
}
