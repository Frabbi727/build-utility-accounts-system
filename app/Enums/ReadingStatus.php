<?php

namespace App\Enums;

/**
 * Whether a meter reading is settled enough to charge somebody for.
 */
enum ReadingStatus: string
{
    /** Entered, still editable, priced at nothing and billed to nobody. */
    case Draft = 'draft';

    /**
     * Settled. The tariff is stamped, the reading is billable, and a consumption-based
     * cost distribution may be approved against it.
     */
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return __('utilities.reading_status_'.$this->value);
    }

    public function isBillable(): bool
    {
        return $this === self::Confirmed;
    }
}
