<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * The narrow rounding exception to the truncation rule.
 *
 * Everywhere else in this application money is truncated: `bcdiv` truncates, and
 * `ChargeCalculator` and `ApplyLateFees` deliberately place the leftover paisa rather
 * than round it. That is correct when a known total is being split, because the parts
 * must add back up to the whole.
 *
 * Metered billing is not a split. A consumption at scale 3 multiplied by a rate at
 * scale 4 lands at scale 4 and has to become money exactly once. Truncating there
 * would shave a fraction off every single utility line, always in the building's
 * favour, forever. This class is the one place that rounds half-up instead.
 *
 * See `.ai/rules/money-arithmetic.md`.
 */
final class Money
{
    /**
     * Round a non-negative decimal string half-up.
     *
     * `bcadd` truncates to the requested scale, so adding half a unit of that scale
     * first is exactly round-half-up — and it stays exact, because no value ever
     * becomes a float.
     */
    public static function round(string $value, int $scale = 2): string
    {
        if ($scale < 0) {
            throw new InvalidArgumentException('Money::round() needs a non-negative scale.');
        }

        // Negatives would round away from zero here, which is not what any caller wants.
        // No money in this application is negative, so refuse rather than guess.
        if (bccomp($value, '0', $scale + 6) < 0) {
            throw new InvalidArgumentException('Money::round() does not take negative amounts.');
        }

        return bcadd($value, '0.'.str_repeat('0', $scale).'5', $scale);
    }
}
