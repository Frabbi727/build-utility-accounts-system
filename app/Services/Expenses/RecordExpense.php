<?php

namespace App\Services\Expenses;

use App\Enums\PaymentMethod;
use App\Exceptions\InvalidJournalEntryException;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Staff;
use App\Models\Vendor;
use App\Services\JournalService;
use App\Support\JournalLineData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records a direct expenditure paid straight from cash or bank — petty cash,
 * salary payments, utilities settled on the spot. No payable is involved.
 *
 * Dr (expense account)
 *     Cr Cash/Bank
 */
class RecordExpense
{
    public function __construct(private readonly JournalService $journal) {}

    public function handle(
        Account $account,
        string $amount,
        PaymentMethod $method,
        Carbon $spentOn,
        string $description,
        ?int $buildingId = null,
        ?Vendor $vendor = null,
        ?Staff $staff = null,
        ?string $reference = null,
    ): Expense {
        $amount = bcadd($amount, '0', 2);

        if (bccomp($amount, '0', 2) <= 0) {
            throw new InvalidJournalEntryException('An expense must be positive.');
        }

        if (! $account->type->isDebitNormal()) {
            throw new InvalidJournalEntryException(
                "Account {$account->code} is not an expense or asset account and cannot be charged as an expense."
            );
        }

        return DB::transaction(function () use ($account, $amount, $method, $spentOn, $description, $buildingId, $vendor, $staff, $reference): Expense {
            $expense = Expense::create([
                'building_id' => $buildingId,
                'account_id' => $account->id,
                'vendor_id' => $vendor?->id,
                'staff_id' => $staff?->id,
                'voucher_no' => $this->nextVoucherNo($spentOn),
                'amount' => $amount,
                'method' => $method,
                'spent_on' => $spentOn,
                'description' => $description,
                'reference' => $reference,
                'recorded_by' => Auth::id(),
            ]);

            $this->journal->post(
                $spentOn,
                __('expenses.expense_posted', ['voucher' => $expense->voucher_no, 'description' => $description]),
                [
                    JournalLineData::debit($account, $amount, null, $expense->voucher_no),
                    JournalLineData::credit(
                        $this->journal->account($method->accountCode()),
                        $amount,
                        null,
                        $expense->voucher_no,
                    ),
                ],
                $expense,
            );

            return $expense;
        });
    }

    private function nextVoucherNo(Carbon $date): string
    {
        $prefix = 'EXP-'.$date->format('Ym').'-';

        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$prefix]);

        $sequence = Expense::where('voucher_no', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
