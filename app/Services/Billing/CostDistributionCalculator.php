<?php

namespace App\Services\Billing;

use App\Enums\DistributionBasis;
use App\Enums\ReadingStatus;
use App\Exceptions\InvalidDistributionException;
use App\Models\CostDistribution;
use App\Models\Flat;
use App\Models\Meter;
use App\Models\MeterReading;
use Illuminate\Support\Collection;

/**
 * Works out what each unit owes of a distributed cost.
 *
 * Two rules govern the arithmetic:
 *
 * 1. **The lines sum to the total exactly.** `bcdiv` truncates, so a weighted split almost
 *    always leaves a few paisa unallocated. The remainder goes to the lowest-id unit —
 *    the same convention `ChargeCalculator` uses for equal-share heads, which keeps the
 *    split deterministic and means the building recovers precisely what it spent.
 *
 * 2. **A zero denominator is refused, not worked around.** Falling back to an equal split
 *    when nobody consumed anything would quietly bill everyone for a month of bad data.
 */
class CostDistributionCalculator
{
    /**
     * The per-unit split for a distribution, as raw line data.
     *
     * @return list<array{flat_id: int, weight: string, amount: string}>
     *
     * @throws InvalidDistributionException
     */
    public function linesFor(CostDistribution $distribution): array
    {
        $flats = $distribution->building->activeFlats()->orderBy('id')->get();

        if ($flats->isEmpty()) {
            throw InvalidDistributionException::noUnits();
        }

        return $this->split(
            (string) $distribution->total_amount,
            $this->weightsFor($distribution, $flats),
        );
    }

    /**
     * Divide an amount by weight, placing the truncation remainder deterministically.
     *
     * @param  list<array{flat_id: int, weight: string}>  $weights
     * @return list<array{flat_id: int, weight: string, amount: string}>
     *
     * @throws InvalidDistributionException
     */
    public function split(string $total, array $weights): array
    {
        $totalWeight = array_reduce(
            $weights,
            fn (string $sum, array $weight): string => bcadd($sum, $weight['weight'], 4),
            '0.0000',
        );

        if (bccomp($totalWeight, '0', 4) <= 0) {
            throw InvalidDistributionException::zeroWeight();
        }

        usort($weights, fn (array $a, array $b): int => $a['flat_id'] <=> $b['flat_id']);

        $lines = [];
        $allocated = '0.00';

        foreach ($weights as $weight) {
            // Scale 6 on the numerator so the division keeps precision the money loses.
            $amount = bcdiv(bcmul($total, $weight['weight'], 6), $totalWeight, 2);

            $allocated = bcadd($allocated, $amount, 2);
            $lines[] = [...$weight, 'amount' => $amount];
        }

        // The lowest-id unit absorbs whatever truncation left behind, so the lines add
        // back up to the contract total exactly.
        $lines[0]['amount'] = bcadd($lines[0]['amount'], bcsub($total, $allocated, 2), 2);

        return $lines;
    }

    /**
     * @param  Collection<int, Flat>  $flats
     * @return list<array{flat_id: int, weight: string}>
     *
     * @throws InvalidDistributionException
     */
    private function weightsFor(CostDistribution $distribution, Collection $flats): array
    {
        return match ($distribution->basis) {
            DistributionBasis::Equal => $flats
                ->map(fn (Flat $flat): array => ['flat_id' => $flat->id, 'weight' => '1.0000'])
                ->all(),

            DistributionBasis::PerSqft => $flats
                ->map(fn (Flat $flat): array => [
                    'flat_id' => $flat->id,
                    'weight' => bcadd((string) $flat->size_sqft, '0', 4),
                ])
                ->all(),

            DistributionBasis::ByConsumption => $this->consumptionWeights($distribution, $flats),
        };
    }

    /**
     * Weights from what each unit actually consumed of a utility that month.
     *
     * Refuses if any active meter has no confirmed reading. A partial denominator is the
     * worst failure mode available here: every unit is overcharged proportionally, the
     * lines still sum to the total, and nothing looks wrong.
     *
     * @param  Collection<int, Flat>  $flats
     * @return list<array{flat_id: int, weight: string}>
     *
     * @throws InvalidDistributionException
     */
    private function consumptionWeights(CostDistribution $distribution, Collection $flats): array
    {
        $meters = Meter::query()
            ->where('utility_id', $distribution->utility_id)
            ->where('is_active', true)
            ->whereNotNull('flat_id')
            ->get();

        $confirmed = MeterReading::query()
            ->whereIn('meter_id', $meters->modelKeys())
            ->whereDate('billing_month', $distribution->billing_month)
            ->where('status', ReadingStatus::Confirmed)
            ->get();

        $missing = $meters->count() - $confirmed->count();

        if ($missing > 0) {
            throw InvalidDistributionException::readingsMissing($missing);
        }

        $consumptionByFlat = $confirmed
            ->groupBy(fn (MeterReading $reading): int => $meters->firstWhere('id', $reading->meter_id)->flat_id)
            ->map(fn (Collection $readings): string => $readings->reduce(
                fn (string $sum, MeterReading $reading): string => bcadd($sum, (string) $reading->consumption, 4),
                '0.0000',
            ));

        return $flats
            ->map(fn (Flat $flat): array => [
                'flat_id' => $flat->id,
                'weight' => $consumptionByFlat->get($flat->id, '0.0000'),
            ])
            ->all();
    }
}
