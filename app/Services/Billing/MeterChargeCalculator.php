<?php

namespace App\Services\Billing;

use App\Models\MeterReading;
use App\Support\BillLineData;

/**
 * Turns a confirmed meter reading into the money and the working shown beside it.
 *
 * The tariff comes off the reading, not off the utility: it was stamped when the reading
 * was confirmed, precisely so that a reading billed months later is still priced at the
 * rate that applied when it was taken.
 */
class MeterChargeCalculator
{
    public function __construct(private readonly TariffSlabCalculator $slabs) {}

    /**
     * What this reading costs, or null if it cannot be priced.
     *
     * @return array{
     *     total: string, energy: string, fixed: string, rate: string,
     *     slabs: list<array{units: string, rate: string, amount: string}>
     * }|null
     */
    public function priceFor(MeterReading $reading): ?array
    {
        // A common meter measures the building, not a unit — nobody is billed for it.
        // A draft reading is not settled, and an unstamped one has no rate to apply.
        if (! $reading->isConfirmed() || $reading->meter->isCommon() || $reading->tariff === null) {
            return null;
        }

        $priced = $this->slabs->amountFor($reading->tariff, (string) $reading->consumption);

        return [
            ...$priced,
            'rate' => $this->slabs->effectiveRate($priced['energy'], (string) $reading->consumption),
        ];
    }

    /**
     * The reading as a bill line, or null if it is not billable.
     *
     * The consumption month is named in the description because it need not be the bill's
     * own month: a reading confirmed after its month was billed rides the next bill, and
     * an owner reading "Electricity 120 kWh" with no month cannot check it.
     */
    public function lineFor(MeterReading $reading): ?BillLineData
    {
        $priced = $this->priceFor($reading);

        if ($priced === null) {
            return null;
        }

        $utility = $reading->meter->utility;

        return BillLineData::metered(
            $utility->account_id,
            __('utilities.bill_line', [
                'utility' => $utility->displayName(),
                'month' => $reading->billing_month->format('F Y'),
                'meter' => $reading->meter->meter_no,
            ]),
            $priced['total'],
            (string) $reading->consumption,
            $priced['rate'],
            $utility->unit_label,
            $reading,
        );
    }
}
