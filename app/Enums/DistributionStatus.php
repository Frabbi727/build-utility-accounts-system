<?php

namespace App\Enums;

/**
 * Whether a distribution's per-unit lines are still being worked out or settled.
 */
enum DistributionStatus: string
{
    /** Lines are recomputed and editable. Nothing is owed by anybody yet. */
    case Draft = 'draft';

    /**
     * Lines are frozen and will be billed. Approval deliberately posts **nothing** to
     * the ledger — the money is accrued when the bill is generated, and posting at both
     * points would double-count the income while leaving both entries balanced.
     */
    case Approved = 'approved';

    public function label(): string
    {
        return __('distributions.status_'.$this->value);
    }

    public function isBillable(): bool
    {
        return $this === self::Approved;
    }
}
