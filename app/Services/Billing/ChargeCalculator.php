<?php

namespace App\Services\Billing;

use App\Enums\ChargeBasis;
use App\Models\Building;
use App\Models\ChargeHead;
use App\Models\Flat;
use App\Models\FlatChargeOverride;
use Illuminate\Support\Collection;

/**
 * Turns a building's charge heads into the line items on one flat's monthly bill.
 *
 * All arithmetic goes through bc* on decimal strings — never floats.
 *
 * Equal-share rounding: an equal-share head's amount is the building's monthly
 * total, split across active flats. bcdiv truncates, so the split usually leaves a
 * few paisa unallocated. That remainder is added to the lowest-id active flat, which
 * makes the split deterministic and keeps the heads summing to the contract total
 * exactly. A flat's equal-share amount therefore depends on how many flats are
 * active when the bill is generated, which is inherent to equal-share billing.
 */
class ChargeCalculator
{
    /**
     * Per-building active flat count and lowest active flat id, resolved once.
     *
     * @var array<int, array{count: int, first_id: int|null}>
     */
    private array $context = [];

    /**
     * The bill lines for one flat, with overrides and exemptions applied.
     *
     * @return list<array{charge_head: ChargeHead, amount: string}>
     */
    public function for(Flat $flat): array
    {
        $building = $flat->relationLoaded('building') ? $flat->building : $flat->building()->firstOrFail();

        $overrides = $this->overridesFor($flat);
        $lines = [];

        foreach ($building->chargeHeads()->where('is_active', true)->get() as $head) {
            $override = $overrides->get($head->id);

            if ($override?->is_exempt === true) {
                continue;
            }

            $amount = $override !== null
                ? bcadd((string) $override->amount, '0', 2)
                : $this->computed($head, $flat, $building);

            if (bccomp($amount, '0', 2) <= 0) {
                continue;
            }

            $lines[] = ['charge_head' => $head, 'amount' => $amount];
        }

        return $lines;
    }

    /**
     * What this flat's next monthly bill would total.
     */
    public function totalFor(Flat $flat): string
    {
        $total = '0.00';

        foreach ($this->for($flat) as $line) {
            $total = bcadd($total, $line['amount'], 2);
        }

        return $total;
    }

    /**
     * The amount before any per-flat override.
     */
    public function computed(ChargeHead $head, Flat $flat, ?Building $building = null): string
    {
        return match ($head->basis) {
            ChargeBasis::PerFlat => bcadd((string) $head->amount, '0', 2),
            ChargeBasis::PerSqft => bcmul((string) $head->amount, (string) $flat->size_sqft, 2),
            ChargeBasis::EqualShare => $this->equalShare($head, $flat, $building ?? $head->building),
        };
    }

    private function equalShare(ChargeHead $head, Flat $flat, Building $building): string
    {
        $context = $this->contextFor($building);

        if ($context['count'] === 0) {
            return '0.00';
        }

        $total = bcadd((string) $head->amount, '0', 2);
        $share = bcdiv($total, (string) $context['count'], 2);

        if ($flat->id !== $context['first_id']) {
            return $share;
        }

        // The lowest-id active flat absorbs whatever truncation left behind.
        $remainder = bcsub($total, bcmul($share, (string) $context['count'], 2), 2);

        return bcadd($share, $remainder, 2);
    }

    /**
     * @return array{count: int, first_id: int|null}
     */
    private function contextFor(Building $building): array
    {
        return $this->context[$building->id] ??= [
            'count' => $building->activeFlats()->count(),
            'first_id' => $building->activeFlats()->min('id'),
        ];
    }

    /**
     * @return Collection<int, FlatChargeOverride>
     */
    private function overridesFor(Flat $flat): Collection
    {
        $overrides = $flat->relationLoaded('chargeOverrides')
            ? $flat->chargeOverrides
            : $flat->chargeOverrides()->get();

        return $overrides->keyBy('charge_head_id');
    }
}
