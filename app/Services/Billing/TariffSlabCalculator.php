<?php

namespace App\Services\Billing;

use App\Models\UtilityTariff;
use App\Models\UtilityTariffSlab;
use App\Support\Money;

/**
 * Prices a consumption against a progressive slab tariff.
 *
 * Progressive means each band is charged at its own rate rather than the whole
 * consumption jumping to the top rate: 250 units against 0-75 / 75-200 / 200+ costs
 * 75 at the first rate, 125 at the second and 50 at the third.
 *
 * Two things here are load-bearing and easy to "simplify" wrongly:
 *
 * 1. **Money is produced exactly once.** Consumption is a scale-3 quantity and rates are
 *    scale 4, so each band's cost is accumulated at scale 4 and the running total is
 *    collapsed to taka only at the end. Rounding each band instead would shed a fraction
 *    of a paisa at every boundary a bill crosses.
 *
 * 2. **The fixed charge is added after rounding, not before.** It is already money, so
 *    putting it through the rounding step would be rounding an exact figure.
 *
 * The per-band breakdown returned alongside the total is for display only — the rounded
 * band amounts are never summed back up, because the total is the rounded sum, not the
 * sum of the rounded parts.
 */
class TariffSlabCalculator
{
    /**
     * @param  string  $consumption  a scale-3 quantity, already rollover-adjusted
     * @return array{
     *     energy: string,
     *     fixed: string,
     *     total: string,
     *     slabs: list<array{units: string, rate: string, amount: string}>
     * }
     */
    public function amountFor(UtilityTariff $tariff, string $consumption): array
    {
        $remaining = bcadd($consumption, '0', 3);
        $energyAtRateScale = '0.0000';
        $breakdown = [];

        foreach ($tariff->slabs as $slab) {
            if (bccomp($remaining, '0', 3) <= 0) {
                break;
            }

            $units = $this->unitsIn($slab, $remaining);
            $cost = bcmul($units, (string) $slab->rate_per_unit, 4);

            $energyAtRateScale = bcadd($energyAtRateScale, $cost, 4);
            $remaining = bcsub($remaining, $units, 3);

            $breakdown[] = [
                'units' => $units,
                'rate' => (string) $slab->rate_per_unit,
                // Display only. Never re-summed — see the class docblock.
                'amount' => Money::round($cost),
            ];
        }

        $energy = Money::round($energyAtRateScale);
        $fixed = bcadd((string) $tariff->fixed_charge, '0', 2);

        return [
            'energy' => $energy,
            'fixed' => $fixed,
            'total' => bcadd($energy, $fixed, 2),
            'slabs' => $breakdown,
        ];
    }

    /**
     * Just the money, for callers that do not need the working shown.
     */
    public function totalFor(UtilityTariff $tariff, string $consumption): string
    {
        return $this->amountFor($tariff, $consumption)['total'];
    }

    /**
     * The blended rate actually charged, for the "x per unit" line on a printed bill.
     *
     * A slab tariff has no single rate, so this is derived from what was charged rather
     * than read off any one band. Zero consumption has no meaningful rate.
     */
    public function effectiveRate(string $energy, string $consumption): string
    {
        if (bccomp($consumption, '0', 3) <= 0) {
            return '0.0000';
        }

        return bcdiv($energy, $consumption, 4);
    }

    /**
     * How much of the remaining consumption this band can absorb.
     *
     * The last band is unbounded, so it takes whatever is left. Slabs are validated
     * contiguous and non-overlapping by SlabValidator, which is what makes draining them
     * in order equivalent to charging each unit exactly once.
     */
    private function unitsIn(UtilityTariffSlab $slab, string $remaining): string
    {
        $width = $slab->width();

        if ($width === null) {
            return $remaining;
        }

        return bccomp($remaining, $width, 3) < 0 ? $remaining : $width;
    }
}
