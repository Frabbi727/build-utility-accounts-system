<?php

namespace App\Services\Billing\LineSources;

use App\Models\AdHocCharge;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Support\BillLineData;
use Illuminate\Support\Carbon;

/**
 * One-off charges raised against a single flat.
 *
 * Pickup is `effective_month <= billingMonth` rather than an exact match: bills are
 * unique per (flat, month) and generation skips flats already billed, so a charge
 * entered after its own month was billed would otherwise be lost forever. It rides the
 * next bill instead.
 */
class AdHocChargeLines implements BillLineSource
{
    /**
     * @return list<BillLineData>
     */
    public function linesFor(Flat $flat, Carbon $billingMonth): array
    {
        return AdHocCharge::query()
            ->where('flat_id', $flat->id)
            ->whereNull('applied_to_bill_id')
            ->whereDate('effective_month', '<=', $billingMonth)
            ->orderBy('id')
            ->get()
            ->map(fn (AdHocCharge $charge): BillLineData => BillLineData::make(
                $charge->account_id,
                $charge->description,
                $charge->amount,
                $charge,
            ))
            ->all();
    }

    public function markBilled(array $lines, ServiceChargeBill $bill): void
    {
        $ids = array_values(array_filter(array_map(
            fn (BillLineData $line): ?int => $line->source instanceof AdHocCharge ? $line->source->id : null,
            $lines,
        )));

        if ($ids === []) {
            return;
        }

        AdHocCharge::whereIn('id', $ids)->update(['applied_to_bill_id' => $bill->id]);
    }
}
