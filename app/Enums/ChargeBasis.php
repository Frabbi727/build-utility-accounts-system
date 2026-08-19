<?php

namespace App\Enums;

/**
 * How a charge head turns into a per-flat amount on a monthly bill.
 */
enum ChargeBasis: string
{
    /** A fixed taka amount every flat pays. */
    case PerFlat = 'per_flat';

    /** A rate multiplied by the flat's size in square feet. */
    case PerSqft = 'per_sqft';

    /** A monthly total for the whole building, split evenly across active flats. */
    case EqualShare = 'equal_share';

    public function label(): string
    {
        return __('masters.basis_'.$this->value);
    }

    /**
     * What the `amount` column means for this basis, for form hints.
     */
    public function amountLabel(): string
    {
        return __('masters.basis_amount_'.$this->value);
    }
}
