<?php

namespace App\Services\Billing;

use App\Enums\AccountCode;
use App\Enums\PaymentMethod;
use App\Models\Flat;
use App\Models\Payment;
use App\Services\JournalService;
use App\Support\JournalLineData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records an owner payment, allocates it to the oldest outstanding bills first,
 * and posts the collection.
 *
 * Dr Cash/Bank
 *     Cr Service Charge Receivable (carrying the flat dimension)
 *
 * Any amount beyond the outstanding dues is credited to Advance from Owners, so
 * the receivable sub-ledger never goes negative.
 */
class RecordPayment
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly AllocationPlanner $planner,
    ) {}

    public function handle(
        Flat $flat,
        string $amount,
        PaymentMethod $method,
        Carbon $receivedOn,
        ?string $reference = null,
    ): Payment {
        $amount = bcadd($amount, '0', 2);

        return DB::transaction(function () use ($flat, $amount, $method, $receivedOn, $reference): Payment {
            $payment = Payment::create([
                'flat_id' => $flat->id,
                'receipt_no' => $this->nextReceiptNo($receivedOn),
                'amount' => $amount,
                'method' => $method,
                'received_on' => $receivedOn,
                'reference' => $reference,
                'received_by' => Auth::id(),
            ]);

            $allocated = $this->allocateToOldestDues($payment, $flat, $amount);
            $advance = bcsub($amount, $allocated, 2);

            $lines = [
                JournalLineData::debit(
                    $this->journal->account($method->accountCode()),
                    $amount,
                    null,
                    $payment->receipt_no,
                ),
            ];

            if (bccomp($allocated, '0', 2) > 0) {
                $lines[] = JournalLineData::credit(
                    $this->journal->account(AccountCode::ServiceChargeReceivable),
                    $allocated,
                    $flat->id,
                    $payment->receipt_no,
                );
            }

            if (bccomp($advance, '0', 2) > 0) {
                $lines[] = JournalLineData::credit(
                    $this->journal->account(AccountCode::AdvanceFromOwners),
                    $advance,
                    $flat->id,
                    $payment->receipt_no,
                );
            }

            $this->journal->post(
                $receivedOn,
                __('billing.payment_received', ['flat' => $flat->number, 'receipt' => $payment->receipt_no]),
                $lines,
                $payment,
            );

            return $payment->load('allocations');
        });
    }

    /**
     * Applies the payment to unpaid bills oldest-first, and returns the total allocated.
     *
     * The ordering itself lives in AllocationPlanner so the confirmation dialog can show
     * the operator the same answer this method is about to act on.
     */
    private function allocateToOldestDues(Payment $payment, Flat $flat, string $amount): string
    {
        $plan = $this->planner->plan($flat, $amount, lock: true);

        foreach ($plan['lines'] as $line) {
            $payment->allocations()->create([
                'service_charge_bill_id' => $line['bill']->id,
                'amount' => $line['amount'],
            ]);

            $line['bill']->refreshStatus();
        }

        return $plan['allocated'];
    }

    private function nextReceiptNo(Carbon $date): string
    {
        $prefix = 'RCPT-'.$date->format('Ym').'-';

        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$prefix]);

        $sequence = Payment::where('receipt_no', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
