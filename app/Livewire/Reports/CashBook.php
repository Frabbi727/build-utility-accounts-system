<?php

namespace App\Livewire\Reports;

use App\Enums\AccountCode;
use App\Models\JournalLine;
use App\Services\JournalService;
use App\Services\Reporting\LedgerReports;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Cash and bank book: opening balance, every movement in the range, closing balance.
 */
class CashBook extends Component
{
    public string $from = '';

    public string $to = '';

    public string $account = AccountCode::CashInHand->value;

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->endOfMonth()->toDateString();
    }

    public function render(JournalService $journal, LedgerReports $reports): View
    {
        $account = $journal->account(AccountCode::from($this->account));

        $from = Carbon::parse($this->from);
        $to = Carbon::parse($this->to);

        $opening = $reports->openingBalance($account, $from);

        $running = $opening;

        $rows = $reports->movements($account, $from, $to)->map(function (JournalLine $line) use (&$running): array {
            $running = bcadd($running, bcsub((string) $line->debit, (string) $line->credit, 2), 2);

            return [
                'date' => $line->journalEntry->entry_date,
                'ref_no' => $line->journalEntry->ref_no,
                'description' => $line->memo ?? $line->journalEntry->description,
                'debit' => (string) $line->debit,
                'credit' => (string) $line->credit,
                'balance' => $running,
            ];
        });

        return view('livewire.reports.cash-book', [
            'account' => $account,
            'accounts' => [AccountCode::CashInHand, AccountCode::Bank],
            'rows' => $rows,
            'opening' => $opening,
            'closing' => $running,
        ])->layout('components.layouts.app');
    }
}
