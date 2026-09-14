<?php

namespace App\Support;

use App\Models\Building;
use Illuminate\Support\Carbon;

/**
 * Aggregated dry-run simulation results for a building's monthly billing run.
 */
class BillSimulationResult
{
    /**
     * @param  list<FlatBillSimulation>  $flats
     * @param  list<string>  $buildingWarnings
     */
    public function __construct(
        public readonly Building $building,
        public readonly Carbon $billingMonth,
        public readonly Carbon $dueDate,
        public readonly array $flats,
        public readonly int $totalActiveFlats,
        public readonly int $totalFlatsToBill,
        public readonly int $alreadyBilledCount,
        public readonly string $estimatedTotalAmount,
        public readonly string $estimatedChargeHeadsAmount,
        public readonly string $estimatedUtilitiesAmount,
        public readonly string $estimatedCostDistributionsAmount,
        public readonly string $estimatedAdHocAmount,
        public readonly string $estimatedAdvancesAmount,
        public readonly string $estimatedNetReceivable,
        public readonly int $pendingDraftReadingsCount,
        public readonly int $pendingDraftDistributionsCount,
        public readonly array $buildingWarnings = [],
    ) {}

    public function hasWarnings(): bool
    {
        if (! empty($this->buildingWarnings)) {
            return true;
        }

        foreach ($this->flats as $flat) {
            if ($flat->hasWarnings()) {
                return true;
            }
        }

        return false;
    }
}
