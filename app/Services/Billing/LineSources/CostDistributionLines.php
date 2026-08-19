<?php

namespace App\Services\Billing\LineSources;

use App\Enums\DistributionStatus;
use App\Models\CostDistribution;
use App\Models\CostDistributionLine;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Support\BillLineData;
use Illuminate\Support\Carbon;

/**
 * A unit's share of approved one-off costs, as bill lines.
 *
 * This source is a **pure consumer** of frozen lines. It never computes a distribution,
 * and bill generation must never be able to trigger one: the moment generation can
 * recompute a split, re-running a month stops being idempotent, because the answer would
 * depend on whatever the flats look like at that moment.
 *
 * Pickup is `billing_month <= billingMonth`, so a distribution approved after its month
 * was billed rides the next bill rather than being lost.
 */
class CostDistributionLines implements BillLineSource
{
    /**
     * @return list<BillLineData>
     */
    public function linesFor(Flat $flat, Carbon $billingMonth): array
    {
        return CostDistributionLine::query()
            ->where('flat_id', $flat->id)
            ->whereNull('applied_to_bill_id')
            ->whereHas('distribution', function ($query) use ($billingMonth): void {
                $query->where('status', DistributionStatus::Approved)
                    ->whereDate('billing_month', '<=', $billingMonth);
            })
            ->with('distribution')
            ->orderBy('id')
            ->get()
            ->map(fn (CostDistributionLine $line): BillLineData => BillLineData::make(
                $line->distribution->recovery_account_id,
                $this->describe($line->distribution),
                $line->amount,
                $line,
            ))
            ->all();
    }

    public function markBilled(array $lines, ServiceChargeBill $bill): void
    {
        $ids = array_values(array_filter(array_map(
            fn (BillLineData $line): ?int => $line->source instanceof CostDistributionLine
                ? $line->source->id
                : null,
            $lines,
        )));

        if ($ids === []) {
            return;
        }

        CostDistributionLine::whereIn('id', $ids)->update(['applied_to_bill_id' => $bill->id]);
    }

    /**
     * The month is named because a late-approved distribution rides a later bill, and an
     * owner reading "Generator repair" with no month cannot check it against anything.
     */
    private function describe(CostDistribution $distribution): string
    {
        return __('distributions.bill_line', [
            'title' => $distribution->title,
            'month' => $distribution->billing_month->format('F Y'),
        ]);
    }
}
