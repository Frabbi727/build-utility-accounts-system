<?php

namespace App\Livewire;

use App\Enums\AccountCode;
use App\Models\Flat;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Services\JournalService;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Per-flat statement: dues and payment history, straight from the sub-ledger.
 */
class FlatStatement extends Component
{
    public Flat $flat;

    public function mount(Flat $flat): void
    {
        // Owners may only ever open their own flat.
        $this->authorize('view', $flat);

        $this->flat = $flat;
    }

    public function render(JournalService $journal): View
    {
        $receivable = $journal->account(AccountCode::ServiceChargeReceivable);

        $lines = JournalLine::with('journalEntry.source')
            ->where('flat_id', $this->flat->id)
            ->where('account_id', $receivable->id)
            ->get()
            ->sortBy(fn (JournalLine $line): string => $line->journalEntry->entry_date->toDateString())
            ->values();

        $running = '0.00';
        $rows = $lines->map(function (JournalLine $line) use (&$running): array {
            $running = bcadd($running, bcsub((string) $line->debit, (string) $line->credit, 2), 2);

            $source = $line->journalEntry->source;

            return [
                'date' => $line->journalEntry->entry_date,
                'description' => $line->journalEntry->description,
                'memo' => $line->memo,
                'debit' => (string) $line->debit,
                'credit' => (string) $line->credit,
                'balance' => $running,
                // A collection line links back to its printable receipt.
                'payment' => $source instanceof Payment ? $source : null,
            ];
        });

        return view('livewire.flat-statement', [
            'rows' => $rows,
            'outstanding' => $journal->balanceFor($receivable, $this->flat->id),
            'advance' => $journal->balanceFor($journal->account(AccountCode::AdvanceFromOwners), $this->flat->id),
        ])->layout('components.layouts.app');
    }
}
