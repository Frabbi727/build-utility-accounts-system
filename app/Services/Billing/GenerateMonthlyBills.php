<?php

namespace App\Services\Billing;

use App\Enums\AccountCode;
use App\Enums\BillStatus;
use App\Models\Building;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Services\Billing\LineSources\BillLineSource;
use App\Services\JournalService;
use App\Support\BillLineData;
use App\Support\JournalLineData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generates one service charge bill per active flat for a billing month and posts
 * the matching accrual, so outstanding dues are answerable from the ledger alone.
 *
 * What a bill contains is decided by the registered `BillLineSource`s — recurring charge
 * heads, metered utilities, distributed costs, one-off charges. This class owns only how
 * a line becomes money, and does it identically for all of them:
 *
 *     Dr Service Charge Receivable       total   (flat dimension)
 *         Cr <line 1 income account>             amount 1
 *         Cr <line 2 income account>             amount 2
 *         ...
 *
 * Two invariants live here rather than in the sources, so no source can forget them:
 * zero-amount lines are dropped (JournalService requires every line to be a debit or a
 * credit, and a zero is neither), and a flat is billed if *any* source produced a line.
 */
class GenerateMonthlyBills
{
    /**
     * @param  list<BillLineSource>  $sources  in the order their lines appear on the bill
     */
    public function __construct(
        private readonly JournalService $journal,
        private readonly ApplyAdvances $advances,
        private readonly array $sources,
    ) {}

    /**
     * Idempotent per (flat, billing month): re-running skips flats already billed.
     * Flats whose sources total nothing are skipped rather than billed zero.
     *
     * @return Collection<int, ServiceChargeBill>
     */
    public function handle(Building $building, Carbon $month): Collection
    {
        $billingMonth = $month->copy()->startOfMonth();
        $dueDate = $billingMonth->copy()->addDays($building->due_day_of_month - 1);

        $receivable = $this->journal->account(AccountCode::ServiceChargeReceivable);

        $alreadyBilled = ServiceChargeBill::whereIn('flat_id', $building->flats()->select('id'))
            ->whereDate('billing_month', $billingMonth)
            ->pluck('flat_id')
            ->all();

        $flats = $building->flats()
            ->where('is_active', true)
            ->whereNotIn('id', $alreadyBilled)
            ->with('chargeOverrides')
            ->orderBy('id')
            ->get();

        return $flats
            ->map(fn (Flat $flat): ?ServiceChargeBill => $this->billFor($flat, $building, $billingMonth, $dueDate, $receivable->id))
            ->filter()
            ->values();
    }

    private function billFor(
        Flat $flat,
        Building $building,
        Carbon $billingMonth,
        Carbon $dueDate,
        int $receivableId,
    ): ?ServiceChargeBill {
        $flat->setRelation('building', $building);

        /** @var array<int, list<BillLineData>> $bySource */
        $bySource = [];
        $total = '0.00';

        foreach ($this->sources as $index => $source) {
            // Zeros are dropped here, once, so no source has to remember to.
            $lines = array_values(array_filter(
                $source->linesFor($flat, $billingMonth),
                fn (BillLineData $line): bool => ! $line->isZero(),
            ));

            $bySource[$index] = $lines;

            foreach ($lines as $line) {
                $total = bcadd($total, $line->amount, 2);
            }
        }

        // A flat with nothing to charge is not billed zero — but one charged by any
        // single source is billed, even if the others produced nothing.
        if (bccomp($total, '0', 2) <= 0) {
            return null;
        }

        return DB::transaction(function () use ($flat, $billingMonth, $dueDate, $bySource, $total, $receivableId): ServiceChargeBill {
            $bill = ServiceChargeBill::create([
                'flat_id' => $flat->id,
                'bill_no' => $this->nextBillNo($billingMonth),
                'billing_month' => $billingMonth,
                'due_date' => $dueDate,
                'total_amount' => $total,
                'status' => BillStatus::Unpaid,
            ]);

            $journalLines = [JournalLineData::debit($receivableId, $total, $flat->id, $bill->bill_no)];

            foreach ($bySource as $index => $lines) {
                foreach ($lines as $line) {
                    $bill->items()->create($line->toArray());

                    $journalLines[] = JournalLineData::credit(
                        $line->accountId,
                        $line->amount,
                        null,
                        $line->description,
                    );
                }

                // Stamped so a re-run cannot bill the same source record twice.
                $this->sources[$index]->markBilled($lines, $bill);
            }

            $this->journal->post(
                $dueDate,
                __('billing.service_charge_accrual', ['flat' => $flat->number, 'month' => $billingMonth->format('F Y')]),
                $journalLines,
                $bill,
            );

            // A prepaying owner's credit is drawn down at once, so the new bill
            // never shows as unpaid while an advance sits idle against the flat.
            $bill->setRelation('flat', $flat);
            $this->advances->handle($bill, $dueDate);

            return $bill;
        });
    }

    private function nextBillNo(Carbon $month): string
    {
        $prefix = 'BILL-'.$month->format('Ym').'-';

        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$prefix]);

        $sequence = ServiceChargeBill::where('bill_no', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
