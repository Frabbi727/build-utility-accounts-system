<?php

namespace App\Services\Accounting;

use App\Enums\AccountCode;
use App\Exceptions\InvalidJournalEntryException;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Services\JournalService;
use App\Support\JournalLineData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Brings the balances a building already had into the ledger, as one entry.
 *
 *     Dr Cash in Hand / Bank / Service Charge Receivable (per flat)
 *         Cr Accounts Payable / Security Deposits
 *         Cr General Fund   <- the balancing figure
 *
 * The General Fund line is whatever it takes to balance, which is what accumulated
 * surplus actually is. It may fall on either side, so a building carrying more
 * liabilities than assets is representable rather than rejected.
 *
 * Posting is once-only: opening balances are a statement about the day the ledger
 * started, and a second one would silently double the fund.
 */
class PostOpeningBalances
{
    public const ACTION = 'opening_balances.posted';

    public function __construct(private readonly JournalService $journal) {}

    public function alreadyPosted(): bool
    {
        return AuditLog::where('action', self::ACTION)->exists();
    }

    /**
     * @param  array<int, string>  $flatReceivables  flat id => amount already owed
     *
     * @throws InvalidJournalEntryException when the entry would carry no amounts
     */
    public function handle(
        Carbon $asOf,
        string $cash = '0',
        string $bank = '0',
        array $flatReceivables = [],
        string $payables = '0',
        string $deposits = '0',
    ): JournalEntry {
        if ($this->alreadyPosted()) {
            throw new InvalidJournalEntryException('Opening balances have already been posted.');
        }

        $lines = [];
        $debits = '0.00';
        $credits = '0.00';

        foreach ([AccountCode::CashInHand->value => $cash, AccountCode::Bank->value => $bank] as $code => $amount) {
            if (bccomp((string) $amount, '0', 2) > 0) {
                $lines[] = JournalLineData::debit($this->journal->account(AccountCode::from($code)), $amount);
                $debits = bcadd($debits, (string) $amount, 2);
            }
        }

        $receivable = $this->journal->account(AccountCode::ServiceChargeReceivable);

        foreach ($flatReceivables as $flatId => $amount) {
            if (bccomp((string) $amount, '0', 2) > 0) {
                // The flat dimension is what makes owner dues answerable per flat.
                $lines[] = JournalLineData::debit($receivable, $amount, (int) $flatId, __('accounting.opening_balance'));
                $debits = bcadd($debits, (string) $amount, 2);
            }
        }

        foreach ([AccountCode::AccountsPayable->value => $payables, AccountCode::SecurityDeposits->value => $deposits] as $code => $amount) {
            if (bccomp((string) $amount, '0', 2) > 0) {
                $lines[] = JournalLineData::credit($this->journal->account(AccountCode::from($code)), $amount);
                $credits = bcadd($credits, (string) $amount, 2);
            }
        }

        if ($lines === []) {
            throw new InvalidJournalEntryException('Opening balances need at least one amount.');
        }

        $fund = bcsub($debits, $credits, 2);

        // Already balanced without it: adding a zero fund line would be rejected.
        if (bccomp($fund, '0', 2) !== 0) {
            $generalFund = $this->journal->account(AccountCode::GeneralFund);

            $lines[] = bccomp($fund, '0', 2) > 0
                ? JournalLineData::credit($generalFund, $fund)
                : JournalLineData::debit($generalFund, bcmul($fund, '-1', 2));
        }

        return DB::transaction(function () use ($asOf, $lines): JournalEntry {
            $entry = $this->journal->post($asOf, __('accounting.opening_balances'), $lines);

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => self::ACTION,
                'subject_type' => $entry->getMorphClass(),
                'subject_id' => $entry->id,
                'payload' => ['ref_no' => $entry->ref_no],
                'created_at' => now(),
            ]);

            return $entry;
        });
    }
}
