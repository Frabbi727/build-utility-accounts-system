<?php

namespace App\Services\Expenses;

use App\Enums\AccountCode;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidJournalEntryException;
use App\Models\VendorBill;
use App\Models\VendorBillPayment;
use App\Services\JournalService;
use App\Support\JournalLineData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Settles a vendor bill, fully or partially.
 *
 * Dr Accounts Payable
 *     Cr Cash/Bank
 */
class PayVendorBill
{
    public function __construct(private readonly JournalService $journal) {}

    public function handle(
        VendorBill $bill,
        string $amount,
        PaymentMethod $method,
        Carbon $paidOn,
        ?string $reference = null,
    ): VendorBillPayment {
        $amount = bcadd($amount, '0', 2);

        if (bccomp($amount, '0', 2) <= 0) {
            throw new InvalidJournalEntryException('A vendor payment must be positive.');
        }

        return DB::transaction(function () use ($bill, $amount, $method, $paidOn, $reference): VendorBillPayment {
            // Lock the bill so two concurrent payments cannot jointly overpay it.
            $bill = VendorBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();

            if (bccomp($amount, $bill->outstandingAmount(), 2) > 0) {
                throw new InvalidJournalEntryException(
                    "Payment of {$amount} exceeds the {$bill->outstandingAmount()} outstanding on bill {$bill->bill_no}."
                );
            }

            $payment = $bill->payments()->create([
                'voucher_no' => $this->nextVoucherNo($paidOn),
                'amount' => $amount,
                'method' => $method,
                'paid_on' => $paidOn,
                'reference' => $reference,
                'paid_by' => Auth::id(),
            ]);

            $this->journal->post(
                $paidOn,
                __('expenses.vendor_bill_paid', ['bill' => $bill->bill_no, 'voucher' => $payment->voucher_no]),
                [
                    JournalLineData::debit(
                        $this->journal->account(AccountCode::AccountsPayable),
                        $amount,
                        null,
                        $payment->voucher_no,
                    ),
                    JournalLineData::credit(
                        $this->journal->account($method->accountCode()),
                        $amount,
                        null,
                        $payment->voucher_no,
                    ),
                ],
                $payment,
            );

            $bill->refreshStatus();

            return $payment;
        });
    }

    private function nextVoucherNo(Carbon $date): string
    {
        $prefix = 'PV-'.$date->format('Ym').'-';

        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$prefix]);

        $sequence = VendorBillPayment::where('voucher_no', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
