<?php

namespace App\Services\Billing\LineSources;

use App\Enums\ReadingStatus;
use App\Models\Flat;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\ServiceChargeBill;
use App\Services\Billing\MeterChargeCalculator;
use App\Support\BillLineData;
use Illuminate\Support\Carbon;

/**
 * Metered utility consumption, as bill lines.
 *
 * Only confirmed readings on the flat's own meters are picked up — a common meter belongs
 * to the building and is recovered through a cost distribution, not billed to a unit.
 *
 * Pickup is `billing_month <= billingMonth`, the same shape ad-hoc charges use and for
 * the same reason: bills are unique per (flat, month), so a reading confirmed after its
 * own month was billed would otherwise never be charged at all.
 */
class MeterReadingLines implements BillLineSource
{
    public function __construct(private readonly MeterChargeCalculator $charges) {}

    /**
     * @return list<BillLineData>
     */
    public function linesFor(Flat $flat, Carbon $billingMonth): array
    {
        $readings = MeterReading::query()
            ->whereIn('meter_id', Meter::where('flat_id', $flat->id)->select('id'))
            ->where('status', ReadingStatus::Confirmed)
            ->whereNull('applied_to_bill_id')
            ->whereDate('billing_month', '<=', $billingMonth)
            ->with(['meter.utility', 'tariff.slabs'])
            ->orderBy('billing_month')
            ->orderBy('id')
            ->get();

        return $readings
            ->map(fn (MeterReading $reading): ?BillLineData => $this->charges->lineFor($reading))
            ->filter()
            ->values()
            ->all();
    }

    public function markBilled(array $lines, ServiceChargeBill $bill): void
    {
        $ids = array_values(array_filter(array_map(
            fn (BillLineData $line): ?int => $line->source instanceof MeterReading ? $line->source->id : null,
            $lines,
        )));

        if ($ids === []) {
            return;
        }

        MeterReading::whereIn('id', $ids)->update(['applied_to_bill_id' => $bill->id]);
    }
}
