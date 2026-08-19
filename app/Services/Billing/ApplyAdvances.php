<?php

namespace App\Services\Billing;

use App\Enums\AccountCode;
use App\Models\Payment;
use App\Models\ServiceChargeBill;
use App\Services\JournalService;
use App\Support\JournalLineData as Line;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Draws a flat's outstanding advance down against a freshly generated bill.
 *
 * An advance is not a separate balance to track: it is exactly the part of a
 * payment that was never allocated to a bill, which is why this works off
 * Payment::unallocatedAmount() rather than a cached column.
 *
 * Applying it moves the money out of the liability and clears the receivable:
 *
 *     Dr Advance from Owners            (flat dimension)
 *         Cr Service Charge Receivable  (flat dimension)
 *
 * Without this step an owner who prepays would see the next month's bill as
 * fully unpaid while their advance sat idle, and the dues report would overstate
 * what they owe.
 */
class ApplyAdvances
{
    public function __construct(private readonly JournalService $journal) {}

    /**
     * Apply any unallocated payments on this bill's flat, oldest first.
     * Returns the total applied.
     */
    public function handle(ServiceChargeBill $bill, ?Carbon $on = null): string
    {
        return DB::transaction(function () use ($bill, $on): string {
            $bill->loadMissing('flat');

            $remaining = $bill->outstandingAmount();

            if (bccomp($remaining, '0', 2) <= 0) {
                return '0.00';
            }

            $applied = '0.00';

            $payments = Payment::where('flat_id', $bill->flat_id)
                ->orderBy('received_on')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($payments as $payment) {
                if (bccomp($remaining, '0', 2) <= 0) {
                    break;
                }

                $available = $payment->unallocatedAmount();

                if (bccomp($available, '0', 2) <= 0) {
                    continue;
                }

                $apply = bccomp($remaining, $available, 2) < 0 ? $remaining : $available;

                $this->allocate($payment, $bill, $apply);

                $remaining = bcsub($remaining, $apply, 2);
                $applied = bcadd($applied, $apply, 2);
            }

            if (bccomp($applied, '0', 2) <= 0) {
                return '0.00';
            }

            $bill->refreshStatus();

            $this->journal->post(
                $on ?? Carbon::parse($bill->billing_month),
                __('billing.advance_applied', ['flat' => $bill->flat->number, 'bill' => $bill->bill_no]),
                [
                    Line::debit(
                        $this->journal->account(AccountCode::AdvanceFromOwners),
                        $applied,
                        $bill->flat_id,
                        $bill->bill_no,
                    ),
                    Line::credit(
                        $this->journal->account(AccountCode::ServiceChargeReceivable),
                        $applied,
                        $bill->flat_id,
                        $bill->bill_no,
                    ),
                ],
                $bill,
            );

            return $applied;
        });
    }

    /**
     * Move $amount of this payment onto the bill.
     *
     * A payment may already be allocated to this bill from an earlier run — a late fee,
     * for instance, re-opens a bill the payment has already paid into. `payment_allocations`
     * is unique on (payment_id, service_charge_bill_id), so the existing row grows rather
     * than a second one being inserted.
     */
    private function allocate(Payment $payment, ServiceChargeBill $bill, string $amount): void
    {
        $allocation = $payment->allocations()
            ->where('service_charge_bill_id', $bill->id)
            ->first();

        if ($allocation === null) {
            $payment->allocations()->create([
                'service_charge_bill_id' => $bill->id,
                'amount' => $amount,
            ]);

            return;
        }

        $allocation->amount = bcadd((string) $allocation->amount, $amount, 2);
        $allocation->save();
    }
}
