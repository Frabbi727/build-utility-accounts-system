<?php

namespace App\Services\Billing\LineSources;

use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Services\Billing\ChargeCalculator;
use App\Support\BillLineData;
use Illuminate\Support\Carbon;

/**
 * The building's recurring charge heads, as bill lines.
 *
 * Deliberately a pure adapter. `ChargeCalculator` remains the only place a flat's
 * recurring amount is computed — there is not a single bc* call in here, and there must
 * never be one, or the rule in `.ai/rules/billing-charge-heads.md` has been broken by
 * the very refactor meant to protect it.
 */
class ChargeHeadLines implements BillLineSource
{
    public function __construct(private readonly ChargeCalculator $charges) {}

    /**
     * @return list<BillLineData>
     */
    public function linesFor(Flat $flat, Carbon $billingMonth): array
    {
        return array_map(
            fn (array $line): BillLineData => BillLineData::make(
                $line['charge_head']->account_id,
                $line['charge_head']->displayName(),
                $line['amount'],
                $line['charge_head'],
            ),
            $this->charges->for($flat),
        );
    }

    /**
     * Nothing to stamp: charge heads are recomputed every run, and the
     * unique(flat_id, billing_month) constraint on bills is what stops a second charge.
     */
    public function markBilled(array $lines, ServiceChargeBill $bill): void {}
}
