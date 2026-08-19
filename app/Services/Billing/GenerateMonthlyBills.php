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
 * Dr Service Charge Receivable (carrying the flat dimension)
 *     Cr Service Charge Income
 */
class GenerateMonthlyBills
{
    public function __construct(private readonly JournalService $journal) {}

    /**
     * Idempotent per (flat, billing month): re-running skips flats already billed.
     *
     * @return Collection<int, ServiceChargeBill>
     */
    public function handle(Building $building, Carbon $month): Collection
    {
        $billingMonth = $month->copy()->startOfMonth();
        $dueDate = $billingMonth->copy()->addDays($building->due_day_of_month - 1);

        $receivable = $this->journal->account(AccountCode::ServiceChargeReceivable);
        $income = $this->journal->account(AccountCode::ServiceChargeIncome);

        $alreadyBilled = ServiceChargeBill::whereIn('flat_id', $building->flats()->pluck('id'))
            ->whereDate('billing_month', $billingMonth)
            ->pluck('flat_id')
            ->all();

        $flats = $building->flats()
            ->where('is_active', true)
            ->whereNotIn('id', $alreadyBilled)
            ->get();

        return $flats->map(function (Flat $flat) use ($building, $billingMonth, $dueDate, $receivable, $income): ServiceChargeBill {
            $amount = $building->serviceChargeFor($flat);

            return DB::transaction(function () use ($flat, $billingMonth, $dueDate, $amount, $receivable, $income): ServiceChargeBill {
                $bill = ServiceChargeBill::create([
                    'flat_id' => $flat->id,
                    'bill_no' => $this->nextBillNo($billingMonth),
                    'billing_month' => $billingMonth,
                    'due_date' => $dueDate,
                    'total_amount' => $amount,
                    'status' => BillStatus::Unpaid,
                ]);

                $bill->items()->create([
                    'account_id' => $income->id,
                    'description' => __('billing.monthly_service_charge', ['month' => $billingMonth->format('F Y')]),
                    'amount' => $amount,
                ]);

                $this->journal->post(
                    $dueDate,
                    __('billing.service_charge_accrual', ['flat' => $flat->number, 'month' => $billingMonth->format('F Y')]),
                    [
                        JournalLineData::debit($receivable, $amount, $flat->id, $bill->bill_no),
                        JournalLineData::credit($income, $amount, null, $bill->bill_no),
                    ],
                    $bill,
                );

                return $bill;
            });
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
