<?php

namespace App\Support;

use App\Models\Flat;

/**
 * In-memory simulation data for a single flat's monthly bill calculation.
 */
class FlatBillSimulation
{
    /**
     * @param  list<BillLineData>  $lines
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly Flat $flat,
        public readonly array $lines,
        public readonly string $chargeHeadsAmount,
        public readonly string $utilitiesAmount,
        public readonly string $costDistributionsAmount,
        public readonly string $adHocAmount,
        public readonly string $totalAmount,
        public readonly string $availableAdvance,
        public readonly string $estimatedAdvanceDrawdown,
        public readonly string $netReceivable,
        public readonly bool $isAlreadyBilled,
        public readonly array $warnings = [],
    ) {}

    public function willBeBilled(): bool
    {
        return ! $this->isAlreadyBilled && $this->flat->is_active && bccomp($this->totalAmount, '0', 2) > 0;
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }
}
