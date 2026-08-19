<?php

namespace App\Services\Billing;

use App\Enums\AccountCode;
use App\Enums\BillStatus;
use App\Enums\LateFeeType;
use App\Models\Building;
use App\Models\ServiceChargeBill;
use App\Services\JournalService;
use App\Support\JournalLineData as Line;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Charges a late fee on bills still outstanding after their due date.
 *
 * A late fee is income the building has earned, so it is accrued exactly like a service
 * charge — the flat owes more, and Late Fee Income goes up:
 *
 *     Dr Service Charge Receivable   fee   (flat dimension)
 *         Cr Late Fee Income         fee
 *
 * The fee must also appear on the bill itself. `outstandingAmount()` is `total_amount`
 * minus allocations, so posting to the ledger alone would leave the bill disagreeing with
 * the receivable. Each fee therefore adds a BillItem *and* raises the bill's total.
 *
 * Idempotency: a bill is charged at most once per calendar month, stamped on
 * `last_late_fee_on`. Re-running on the same day, or twice in one month, does nothing.
 */
class ApplyLateFees
{
    public function __construct(private readonly JournalService $journal) {}

    /**
     * Charge every overdue bill in this building that has not been charged this month.
     *
     * @return Collection<int, ServiceChargeBill> the bills that were charged
     */
    public function handle(Building $building, ?Carbon $on = null): Collection
    {
        $on = ($on ?? Carbon::now())->copy()->startOfDay();

        if (! $building->late_fee_type->charges()) {
            return collect();
        }

        if (bccomp((string) $building->late_fee_amount, '0', 2) <= 0) {
            return collect();
        }

        // A bill becomes chargeable once its due date plus the grace period has passed.
        $chargeableBefore = $on->copy()->subDays($building->late_fee_grace_days);

        $bills = ServiceChargeBill::query()
            ->whereIn('flat_id', $building->flats()->select('id'))
            ->whereIn('status', [BillStatus::Unpaid, BillStatus::PartiallyPaid])
            ->whereDate('due_date', '<', $chargeableBefore)
            ->where(function ($query) use ($on): void {
                $query->whereNull('last_late_fee_on')
                    ->orWhereDate('last_late_fee_on', '<', $on->copy()->startOfMonth());
            })
            ->with('flat')
            ->orderBy('id')
            ->get();

        return $bills
            ->map(fn (ServiceChargeBill $bill): ?ServiceChargeBill => $this->charge($bill, $building, $on))
            ->filter()
            ->values();
    }

    private function charge(ServiceChargeBill $bill, Building $building, Carbon $on): ?ServiceChargeBill
    {
        $fee = $this->feeFor($bill, $building);

        if (bccomp($fee, '0', 2) <= 0) {
            return null;
        }

        return DB::transaction(function () use ($bill, $fee, $on): ServiceChargeBill {
            $lateFeeIncome = $this->journal->account(AccountCode::LateFeeIncome);

            $bill->items()->create([
                'account_id' => $lateFeeIncome->id,
                'description' => __('billing.late_fee_item', ['month' => $on->format('F Y')]),
                'amount' => $fee,
            ]);

            // The bill and the ledger have to move together, or dues reports disagree.
            $bill->total_amount = bcadd((string) $bill->total_amount, $fee, 2);
            $bill->last_late_fee_on = $on;
            $bill->save();

            $this->journal->post(
                $on,
                __('billing.late_fee_charged', [
                    'flat' => $bill->flat->number,
                    'bill' => $bill->bill_no,
                ]),
                [
                    Line::debit(
                        $this->journal->account(AccountCode::ServiceChargeReceivable),
                        $fee,
                        $bill->flat_id,
                        $bill->bill_no,
                    ),
                    Line::credit($lateFeeIncome, $fee, $bill->flat_id, $bill->bill_no),
                ],
                $bill,
            );

            $bill->refreshStatus();

            return $bill;
        });
    }

    /**
     * A percentage fee is charged on what is still outstanding, not the original total,
     * so a part payment reduces next month's fee.
     */
    private function feeFor(ServiceChargeBill $bill, Building $building): string
    {
        $rate = bcadd((string) $building->late_fee_amount, '0', 2);

        return match ($building->late_fee_type) {
            LateFeeType::Percent => bcdiv(bcmul($bill->outstandingAmount(), $rate, 4), '100', 2),
            LateFeeType::Fixed => $rate,
            LateFeeType::None => '0.00',
        };
    }
}
