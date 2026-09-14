<?php

namespace App\Services\Billing;

use App\Enums\ReadingStatus;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Support\MeterAnomalyData;
use Illuminate\Support\Carbon;

/**
 * Detects utility consumption anomalies, unusual usage spikes (>50% above 3-month average),
 * negative readings, and zero usage on occupied units.
 */
class MeterReadingAnomalyDetector
{
    public function __construct(
        private readonly MeterConsumption $consumption,
    ) {}

    public function detect(
        Meter $meter,
        ?string $currentReading,
        Carbon $month,
        ?string $previousReading = null,
    ): MeterAnomalyData {
        $meter->loadMissing('flat');
        $billingMonth = $month->copy()->startOfMonth();

        // 1. Calculate 3-month rolling average consumption
        $historical = MeterReading::query()
            ->where('meter_id', $meter->id)
            ->whereDate('billing_month', '<', $billingMonth)
            ->whereDate('billing_month', '>=', $billingMonth->copy()->subMonths(3))
            ->where('status', ReadingStatus::Confirmed)
            ->pluck('consumption');

        $average = null;
        if ($historical->isNotEmpty()) {
            $sum = '0.000';
            foreach ($historical as $val) {
                $sum = bcadd($sum, (string) $val, 3);
            }
            $average = bcdiv($sum, (string) $historical->count(), 3);
        }

        if ($currentReading === null || trim($currentReading) === '') {
            return new MeterAnomalyData(
                averageConsumption: $average,
                variancePercentage: null,
                hasSpike: false,
                isNegative: false,
                isZeroOccupied: false,
                warningMessage: null,
            );
        }

        $prev = $previousReading ?? $this->resolvePreviousReading($meter, $billingMonth);
        $curr = bcadd($currentReading, '0', 3);

        $isNegative = bccomp($curr, $prev, 3) < 0 && ! $this->isLikelyRollover($meter, $prev, $curr);
        $units = $this->consumption->between($meter, $prev, $curr);

        $variancePercentage = null;
        $hasSpike = false;

        if ($average !== null && bccomp($average, '0', 3) > 0) {
            $diff = bcsub($units, $average, 3);
            $variancePercentage = round(((float) $diff / (float) $average) * 100.0, 1);

            if ($variancePercentage > 50.0) {
                $hasSpike = true;
            }
        }

        $isZeroOccupied = bccomp($units, '0', 3) === 0
            && $meter->flat_id !== null
            && ($meter->flat !== null ? $meter->flat->is_active : true);

        $warnings = [];
        if ($hasSpike && $variancePercentage !== null) {
            $warnings[] = "Consumption ({$units}) is {$variancePercentage}% higher than 3-month avg ({$average}).";
        }
        if ($isNegative) {
            $warnings[] = "Current reading ({$curr}) is lower than previous ({$prev}).";
        }
        if ($isZeroOccupied) {
            $warnings[] = 'Zero consumption recorded on an active unit.';
        }

        return new MeterAnomalyData(
            averageConsumption: $average,
            variancePercentage: $variancePercentage,
            hasSpike: $hasSpike,
            isNegative: $isNegative,
            isZeroOccupied: $isZeroOccupied,
            warningMessage: empty($warnings) ? null : implode(' ', $warnings),
        );
    }

    private function resolvePreviousReading(Meter $meter, Carbon $billingMonth): string
    {
        $earlier = $meter->readings()
            ->whereDate('billing_month', '<', $billingMonth)
            ->orderByDesc('billing_month')
            ->orderByDesc('id')
            ->first();

        return bcadd((string) ($earlier === null ? $meter->initial_reading : $earlier->current_reading), '0', 3);
    }

    private function isLikelyRollover(Meter $meter, string $previous, string $current): bool
    {
        $max = bcpow('10', (string) $meter->digits, 0);
        $rolloverThreshold = bcmul($max, '0.8', 0);

        return bccomp($previous, $rolloverThreshold, 0) > 0 && bccomp($current, bcmul($max, '0.2', 0), 0) < 0;
    }
}
