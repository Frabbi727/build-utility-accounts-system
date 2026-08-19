<?php

namespace Tests\Concerns;

use App\Models\JournalEntry;
use App\Models\JournalLine;

/**
 * The invariant the whole application exists to protect, as an assertion.
 *
 * `JournalService` refuses to post an unbalanced entry, so a failure here means
 * something wrote to `journal_lines` without going through it — which is the one
 * thing `.ai/rules/accounting-ledger.md` forbids outright.
 */
trait AssertsLedger
{
    /**
     * Every debit in the ledger is matched by a credit.
     */
    protected function assertLedgerIsBalanced(): void
    {
        $debits = (string) (JournalLine::sum('debit') ?: '0');
        $credits = (string) (JournalLine::sum('credit') ?: '0');

        $this->assertSame(
            0,
            bccomp($debits, $credits, 2),
            "Ledger is out of balance: debits {$debits} vs credits {$credits}.",
        );
    }

    /**
     * Every entry balances on its own, not merely in aggregate.
     *
     * A pair of opposite mistakes cancels out in the totals, so the aggregate check
     * alone can pass over a genuinely broken entry.
     */
    protected function assertEveryEntryIsBalanced(): void
    {
        foreach (JournalEntry::with('lines')->get() as $entry) {
            $this->assertSame(
                0,
                bccomp(
                    (string) ($entry->lines->sum('debit') ?: '0'),
                    (string) ($entry->lines->sum('credit') ?: '0'),
                    2,
                ),
                "Entry {$entry->ref_no} is unbalanced.",
            );
        }
    }
}
