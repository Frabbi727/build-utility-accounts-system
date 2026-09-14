<?php

namespace App\Services\Billing;

use App\Enums\AccountCode;
use App\Models\Payment;
use App\Models\ServiceChargeBill;
use App\Services\JournalService;
use App\Support\JournalLineData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Reverses a recorded payment by posting balanced contra journal entries,
 * clearing allocations, and re-opening any settled bills.
 *
 *     Cr Cash/Bank (Payment method account)
 *     Dr Service Charge Receivable (carrying the flat dimension for the allocated portion)
 *     Dr Advance from Owners (carrying the flat dimension for the unallocated portion)
 */
class ReversePayment
{
    public function __construct(
        private readonly JournalService $journal,
    ) {}

    public function handle(Payment $payment, string $reason, ?Carbon $reversedOn = null): Payment
    {
        if ($payment->isReversed()) {
            throw new \InvalidArgumentException('This payment has already been reversed.');
        }

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A reversal reason is required.');
        }

        $reversedOn = $reversedOn ?? now();

        return DB::transaction(function () use ($payment, $reason, $reversedOn): Payment {
            $payment->loadMissing(['flat', 'allocations.bill']);

            $amount = $payment->amount;
            $allocations = $payment->allocations;

            $allocated = '0.00';
            /** @var list<ServiceChargeBill> $affectedBills */
            $affectedBills = [];

            foreach ($allocations as $allocation) {
                $allocated = bcadd($allocated, (string) $allocation->amount, 2);
                if ($allocation->bill !== null) {
                    $affectedBills[] = $allocation->bill;
                }
            }

            $advance = bcsub((string) $amount, $allocated, 2);

            $reference = 'REV-'.$payment->receipt_no;

            // Reversing Journal Entries:
            // 1. Cr Cash/Bank for total payment amount
            $lines = [
                JournalLineData::credit(
                    $this->journal->account($payment->method->accountCode()),
                    $amount,
                    null,
                    $reference,
                ),
            ];

            // 2. Dr Service Charge Receivable for the allocated part
            if (bccomp($allocated, '0', 2) > 0) {
                $lines[] = JournalLineData::debit(
                    $this->journal->account(AccountCode::ServiceChargeReceivable),
                    $allocated,
                    $payment->flat_id,
                    $reference,
                );
            }

            // 3. Dr Advance from Owners for the advance portion
            if (bccomp($advance, '0', 2) > 0) {
                $lines[] = JournalLineData::debit(
                    $this->journal->account(AccountCode::AdvanceFromOwners),
                    $advance,
                    $payment->flat_id,
                    $reference,
                );
            }

            // Delete payment allocations
            $payment->allocations()->delete();

            // Refresh status for all affected bills
            foreach ($affectedBills as $bill) {
                $bill->refreshStatus();
            }

            // Mark payment as reversed
            $payment->update([
                'reversed_at' => $reversedOn,
                'reversal_reason' => $reason,
                'reversed_by' => Auth::id(),
            ]);

            // Post the contra journal entry
            $this->journal->post(
                $reversedOn,
                __('billing.payment_reversed', ['receipt' => $payment->receipt_no, 'reason' => $reason]),
                $lines,
                $payment,
            );

            return $payment->fresh(['allocations', 'flat', 'reversedBy']) ?? $payment;
        });
    }
}
