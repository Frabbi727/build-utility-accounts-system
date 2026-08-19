<?php

namespace App\Services\Billing;

use App\Exceptions\InvalidTariffException;

/**
 * Checks that a set of tariff slabs covers every possible consumption exactly once.
 *
 * `TariffSlabCalculator` walks the slabs in order draining the consumption, which is only
 * correct if the bands are contiguous, non-overlapping, start at zero and end unbounded.
 * A gap would silently drop units from the bill; an overlap would charge them twice; a
 * bounded last slab would stop charging a heavy user beyond its ceiling.
 *
 * None of that is expressible as a column constraint — it is a property of the whole set
 * — so it is enforced here, whenever a tariff is saved.
 */
class SlabValidator
{
    /**
     * @param  list<array{from_unit: string|float|int, to_unit: string|float|int|null, rate_per_unit?: mixed}>  $slabs
     *
     * @throws InvalidTariffException
     */
    public function assertValid(array $slabs): void
    {
        if ($slabs === []) {
            throw InvalidTariffException::empty();
        }

        usort($slabs, fn (array $a, array $b): int => bccomp((string) $a['from_unit'], (string) $b['from_unit'], 3));

        $first = bcadd((string) $slabs[0]['from_unit'], '0', 3);

        if (bccomp($first, '0', 3) !== 0) {
            throw InvalidTariffException::notStartingAtZero($first);
        }

        $lastIndex = count($slabs) - 1;
        $boundary = '0.000';

        foreach ($slabs as $index => $slab) {
            $from = bcadd((string) $slab['from_unit'], '0', 3);
            $comparison = bccomp($from, $boundary, 3);

            if ($comparison > 0) {
                throw InvalidTariffException::gap($boundary, $from);
            }

            if ($comparison < 0) {
                throw InvalidTariffException::overlap($boundary, $from);
            }

            $isLast = $index === $lastIndex;
            $to = $slab['to_unit'] === null || $slab['to_unit'] === '' ? null : bcadd((string) $slab['to_unit'], '0', 3);

            if ($to === null) {
                if (! $isLast) {
                    throw InvalidTariffException::unboundedBeforeTheEnd($from);
                }

                return;
            }

            if ($isLast) {
                throw InvalidTariffException::notOpenEnded($to);
            }

            $boundary = $to;
        }
    }

    /**
     * The same check as a message rather than an exception, for form validation.
     *
     * @param  list<array{from_unit: string|float|int, to_unit: string|float|int|null, rate_per_unit?: mixed}>  $slabs
     */
    public function errorFor(array $slabs): ?string
    {
        try {
            $this->assertValid($slabs);
        } catch (InvalidTariffException $e) {
            return $e->getMessage();
        }

        return null;
    }
}
