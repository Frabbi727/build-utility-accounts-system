<?php

namespace App\Services;

use App\Enums\AccountCode;
use App\Exceptions\InvalidJournalEntryException;
use App\Exceptions\PeriodLockedException;
use App\Exceptions\UnbalancedEntryException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Support\JournalLineData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of journal entries and journal lines.
 *
 * Every financial event in the application funnels through post(). Nothing else
 * may insert into journal_lines — that is what keeps the ledger trustworthy.
 */
class JournalService
{
    /**
     * Post a balanced journal entry.
     *
     * @param  list<JournalLineData>  $lines
     *
     * @throws InvalidJournalEntryException|PeriodLockedException|UnbalancedEntryException
     */
    public function post(
        Carbon $entryDate,
        string $description,
        array $lines,
        ?Model $source = null,
        ?string $refNo = null,
    ): JournalEntry {
        $this->assertStructurallyValid($lines);
        $this->assertBalanced($lines);

        return DB::transaction(function () use ($entryDate, $description, $lines, $source, $refNo): JournalEntry {
            $period = $this->periodFor($entryDate);

            if ($period->isLocked()) {
                throw PeriodLockedException::for($period);
            }

            $entry = new JournalEntry([
                'entry_date' => $entryDate,
                'ref_no' => $refNo ?? $this->nextRefNo($entryDate),
                'description' => $description,
                'accounting_period_id' => $period->id,
                'created_by' => Auth::id(),
            ]);

            if ($source !== null) {
                $entry->source()->associate($source);
            }

            $entry->save();

            foreach ($lines as $line) {
                $entry->lines()->create($line->toArray());
            }

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'journal_entry.posted',
                'subject_type' => $entry->getMorphClass(),
                'subject_id' => $entry->id,
                'payload' => [
                    'ref_no' => $entry->ref_no,
                    'description' => $entry->description,
                    'lines' => array_map(fn (JournalLineData $l): array => $l->toArray(), $lines),
                ],
                'created_at' => now(),
            ]);

            return $entry->load('lines');
        });
    }

    /**
     * Reverse an entry with a contra entry. Originals are never edited or deleted.
     */
    public function reverse(JournalEntry $entry, ?Carbon $reversalDate = null, ?string $reason = null): JournalEntry
    {
        if ($entry->isReversed()) {
            throw new InvalidJournalEntryException("Entry {$entry->ref_no} has already been reversed.");
        }

        return DB::transaction(function () use ($entry, $reversalDate, $reason): JournalEntry {
            $lines = $entry->lines->map(fn ($line): JournalLineData => bccomp((string) $line->debit, '0', 2) > 0
                ? JournalLineData::credit($line->account_id, $line->debit, $line->flat_id, $line->memo)
                : JournalLineData::debit($line->account_id, $line->credit, $line->flat_id, $line->memo)
            )->all();

            $reversal = $this->post(
                $reversalDate ?? Carbon::parse($entry->entry_date),
                $reason ?? "Reversal of {$entry->ref_no}",
                $lines,
                $entry->source,
            );

            $entry->reversed_by_id = $reversal->id;
            $entry->save();

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'journal_entry.reversed',
                'subject_type' => $entry->getMorphClass(),
                'subject_id' => $entry->id,
                'payload' => ['reversed_by' => $reversal->ref_no, 'reason' => $reason],
                'created_at' => now(),
            ]);

            return $reversal;
        });
    }

    /**
     * Ledger balance of an account, in its natural (normal-balance) direction.
     * Always derived from journal_lines — no balance is ever cached.
     *
     * Pass $asOf to stop at a date; reports need that, day-to-day callers do not.
     */
    public function balanceFor(Account|int $account, ?int $flatId = null, ?Carbon $asOf = null): string
    {
        $account = $account instanceof Account ? $account : Account::findOrFail($account);

        $query = $account->journalLines();

        if ($flatId !== null) {
            $query->where('flat_id', $flatId);
        }

        if ($asOf !== null) {
            $query->whereHas('journalEntry', fn ($entry) => $entry->whereDate('entry_date', '<=', $asOf));
        }

        $debit = (string) ($query->clone()->sum('debit') ?: '0');
        $credit = (string) ($query->clone()->sum('credit') ?: '0');

        return $account->type->isDebitNormal()
            ? bcsub($debit, $credit, 2)
            : bcsub($credit, $debit, 2);
    }

    /**
     * Resolve a seeded account by its stable chart-of-accounts code.
     */
    public function account(AccountCode $code): Account
    {
        return Account::where('code', $code->value)->firstOrFail();
    }

    /**
     * Find or create the accounting period covering the given date.
     */
    public function periodFor(Carbon $date): AccountingPeriod
    {
        return AccountingPeriod::firstOrCreate(
            ['year' => (int) $date->year, 'month' => (int) $date->month],
        );
    }

    /**
     * @param  list<JournalLineData>  $lines
     */
    private function assertStructurallyValid(array $lines): void
    {
        if (count($lines) < 2) {
            throw new InvalidJournalEntryException('A journal entry needs at least two lines.');
        }

        foreach ($lines as $line) {
            $hasDebit = bccomp($line->debit, '0', 2) > 0;
            $hasCredit = bccomp($line->credit, '0', 2) > 0;

            if ($hasDebit === $hasCredit) {
                throw new InvalidJournalEntryException(
                    'Each journal line must carry either a debit or a credit, never both and never neither.'
                );
            }

            if (bccomp($line->debit, '0', 2) < 0 || bccomp($line->credit, '0', 2) < 0) {
                throw new InvalidJournalEntryException('Journal line amounts must not be negative.');
            }
        }
    }

    /**
     * @param  list<JournalLineData>  $lines
     */
    private function assertBalanced(array $lines): void
    {
        $debit = '0.00';
        $credit = '0.00';

        foreach ($lines as $line) {
            $debit = bcadd($debit, $line->debit, 2);
            $credit = bcadd($credit, $line->credit, 2);
        }

        if (bccomp($debit, $credit, 2) !== 0) {
            throw UnbalancedEntryException::make($debit, $credit);
        }
    }

    /**
     * Allocate the next reference number for the entry's month.
     *
     * Callers are always inside post()'s transaction, so a transaction-scoped advisory
     * lock serialises concurrent allocations and is released automatically on commit.
     * (Postgres rejects FOR UPDATE alongside an aggregate, so we cannot lock the rows.)
     */
    private function nextRefNo(Carbon $date): string
    {
        $prefix = 'JE-'.$date->format('Ym').'-';

        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$prefix]);

        $sequence = JournalEntry::where('ref_no', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
