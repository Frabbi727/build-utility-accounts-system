<?php

namespace App\Livewire\Reports;

use App\Enums\AccountType;
use App\Services\Reporting\LedgerReports;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Spend per expense account over a period. Reads the ledger, so direct expenses and
 * vendor bills are both counted, each in the month it was incurred.
 */
class ExpenseByCategory extends Component
{
    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->endOfMonth()->toDateString();
    }

    public function render(LedgerReports $reports): View
    {
        $rows = $reports->balancesByType(
            AccountType::Expense,
            Carbon::parse($this->from),
            Carbon::parse($this->to),
        );

        $total = $reports->total($rows);

        // Each category's share of the period's spend, for the bar next to the figure.
        $rows = $rows->map(fn (array $row): array => [...$row, 'share' => bccomp($total, '0', 2) === 0
            ? '0.00'
            : bcdiv(bcmul($row['amount'], '100', 4), $total, 2),
        ]);

        return view('livewire.reports.expense-by-category', [
            'rows' => $rows,
            'total' => $total,
        ])->layout('components.layouts.app');
    }
}
