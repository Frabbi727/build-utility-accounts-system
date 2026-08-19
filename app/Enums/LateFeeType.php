<?php

namespace App\Enums;

/**
 * How a building charges a late fee on an overdue bill.
 */
enum LateFeeType: string
{
    /** No late fee is charged. */
    case None = 'none';

    /** A percentage of the amount still outstanding when the fee is charged. */
    case Percent = 'percent';

    /** A flat taka amount, regardless of what is outstanding. */
    case Fixed = 'fixed';

    public function label(): string
    {
        return __('masters.late_fee_'.$this->value);
    }

    /**
     * What the `late_fee_amount` column means for this type, for form hints.
     */
    public function amountLabel(): string
    {
        return __('masters.late_fee_amount_'.$this->value);
    }

    public function charges(): bool
    {
        return $this !== self::None;
    }
}
