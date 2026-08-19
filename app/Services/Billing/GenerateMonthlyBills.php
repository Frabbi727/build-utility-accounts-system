<?php

namespace App\Services\Billing;

use App\Enums\AccountCode;
use App\Enums\BillStatus;
use App\Models\Building;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Services\JournalService;
use App\Support\JournalLineData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generates one service charge bill per active flat for a billing month and posts
 * the matching accrual, so outstanding dues are answerable from the ledger alone.
 *
 * A bill carries one item per active charge head on the building. The accrual
 * debits Service Charge Receivable once for the bill total, carrying the flat
 * dimension, and credits each head's own income account:
 *
 *     Dr Service Charge Receivable       total   (flat dimension)
 *         Cr <head 1 income account>             amount 1
 *         Cr <head 2 income account>             amount 2
 *         ...
 */
class GenerateMonthlyBills
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly ChargeCalculator $charges,
    ) {}

    /**
     * Idempotent per (flat, billing month): re-running skips flats already billed.
     * Flats whose charge heads total nothing are skipped rather than billed zero.
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

        $lines = $this->charges->for($flat);

        if ($lines === []) {
            return null;
        }

        $total = array_reduce($lines, fn (string $sum, array $line): string => bcadd($sum, $line['amount'], 2), '0.00');

        return DB::transaction(function () use ($flat, $billingMonth, $dueDate, $lines, $total, $receivableId): ServiceChargeBill {
            $bill = ServiceChargeBill::create([
                'flat_id' => $flat->id,
                'bill_no' => $this->nextBillNo($billingMonth),
                'billing_month' => $billingMonth,
                'due_date' => $dueDate,
                'total_amount' => $total,
                'status' => BillStatus::Unpaid,
            ]);

            $journalLines = [JournalLineData::debit($receivableId, $total, $flat->id, $bill->bill_no)];

            foreach ($lines as $line) {
                $head = $line['charge_head'];

                $bill->items()->create([
                    'account_id' => $head->account_id,
                    'description' => $head->displayName(),
                    'amount' => $line['amount'],
                ]);

                $journalLines[] = JournalLineData::credit($head->account_id, $line['amount'], null, $head->name);
            }

            $this->journal->post(
                $dueDate,
                __('billing.service_charge_accrual', ['flat' => $flat->number, 'month' => $billingMonth->format('F Y')]),
                $journalLines,
                $bill,
            );

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
