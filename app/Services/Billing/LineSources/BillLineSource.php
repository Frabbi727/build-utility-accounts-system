<?php

namespace App\Services\Billing\LineSources;

use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Support\BillLineData;
use Illuminate\Support\Carbon;

/**
 * Something that can put lines on a flat's monthly bill.
 *
 * Bill generation used to inline each kind of charge, and every kind repeated the same
 * four steps: create a BillItem, add to the bill total, append a journal credit, stamp
 * the source as billed. Four hand-maintained copies of that sequence is exactly how the
 * bill and the ledger drift apart, which `.ai/rules/billing-charge-heads.md` forbids.
 *
 * A source therefore only decides *what* to charge. GenerateMonthlyBills owns the how,
 * once, for every source.
 */
interface BillLineSource
{
    /**
     * What this source charges the flat on the bill for the given month.
     *
     * Implementations return raw lines and do not filter zero amounts — generation drops
     * those centrally, so no source has to remember to.
     *
     * @return list<BillLineData>
     */
    public function linesFor(Flat $flat, Carbon $billingMonth): array;

    /**
     * Record that these lines have been billed, so a re-run cannot charge them twice.
     *
     * Called inside the generation transaction, after the bill exists. Sources whose
     * lines are recomputed from scratch each run (charge heads) have nothing to do here.
     *
     * @param  list<BillLineData>  $lines  the lines this source produced, zeros already dropped
     */
    public function markBilled(array $lines, ServiceChargeBill $bill): void;
}
