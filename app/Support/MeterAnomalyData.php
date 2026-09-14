<?php

namespace App\Support;

/**
 * Anomaly evaluation result for a prospective or saved meter reading.
 */
class MeterAnomalyData
{
    public function __construct(
        public readonly ?string $averageConsumption,
        public readonly ?float $variancePercentage,
        public readonly bool $hasSpike,
        public readonly bool $isNegative,
        public readonly bool $isZeroOccupied,
        public readonly ?string $warningMessage = null,
    ) {}

    public function hasAnomaly(): bool
    {
        return $this->hasSpike || $this->isNegative || $this->isZeroOccupied;
    }
}
