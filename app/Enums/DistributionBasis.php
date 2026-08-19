<?php

namespace App\Enums;

/**
 * How a one-off cost is spread across units.
 *
 * These are algorithms, so unlike utilities and unit types they are code rather than
 * data. Anything a basis cannot express is handled by editing the draft lines directly
 * before approval, which is strictly more flexible than any weighting rule.
 */
enum DistributionBasis: string
{
    /** Every unit pays the same. */
    case Equal = 'equal';

    /** Weighted by floor area, so a larger unit pays proportionally more. */
    case PerSqft = 'per_sqft';

    /** Weighted by what each unit actually consumed of a utility that month. */
    case ByConsumption = 'by_consumption';

    public function label(): string
    {
        return __('distributions.basis_'.$this->value);
    }

    public function description(): string
    {
        return __('distributions.basis_help_'.$this->value);
    }

    /**
     * Whether this basis needs a utility chosen to weight against.
     */
    public function needsUtility(): bool
    {
        return $this === self::ByConsumption;
    }
}
