<?php

namespace App\Support;

class MeterGenerationPreviewData
{
    /**
     * @param  list<array{flat_id: int, flat_number: string, utility_id: int, utility_name: string, reason: string}>  $skippedDetails
     * @param  list<array{flat_id: int, flat_number: string, utility_id: int, utility_name: string, meter_no: string}>  $inactiveWarnings
     * @param  array<int, int>  $utilityCounts
     */
    public function __construct(
        public readonly int $totalFlatsCount,
        public readonly int $eligibleMetersCount,
        public readonly int $skippedMetersCount,
        public readonly int $excludedFlatsCount,
        public readonly array $skippedDetails = [],
        public readonly array $inactiveWarnings = [],
        public readonly array $utilityCounts = [],
    ) {}
}
