<?php

namespace App\Services\Billing;

use App\Models\Meter;

/**
 * How many units passed through a meter between two dial readings.
 *
 * Normally the difference, but a mechanical meter has a fixed number of dials and wraps
 * back to zero past all-nines. A 5-digit meter that read 99990 last month and 10 this
 * month recorded 20 units — it did not run 99980 units backwards. That is the only reason
 * `meters.digits` exists.
 *
 * The result is written to `meter_readings.consumption` at entry time and never
 * recomputed, because once a meter has been replaced the pair of readings on a historic
 * row no longer explains the consumption it produced.
 */
class MeterConsumption
{
    /**
     * @return string a scale-3 quantity, never negative
     */
    public function between(Meter $meter, string $previous, string $current): string
    {
        $previous = bcadd($previous, '0', 3);
        $current = bcadd($current, '0', 3);

        if (bccomp($current, $previous, 3) >= 0) {
            return bcsub($current, $previous, 3);
        }

        // The dials wrapped: count up to the meter's ceiling, then on to the new reading.
        $ceiling = bcpow('10', (string) $meter->digits, 3);

        return bcsub(bcadd($ceiling, $current, 3), $previous, 3);
    }

    /**
     * Whether a reading pair is impossible even allowing for rollover.
     *
     * A reading above the meter's own ceiling is a typo — an extra digit — not a wrap,
     * and would otherwise be billed as an enormous consumption.
     */
    public function isImplausible(Meter $meter, string $current): bool
    {
        return bccomp(bcadd($current, '0', 3), bcpow('10', (string) $meter->digits, 3), 3) >= 0;
    }
}
