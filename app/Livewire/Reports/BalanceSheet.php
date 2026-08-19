<?php

namespace App\Livewire\Reports;

use App\Enums\AccountType;
use App\Services\Reporting\LedgerReports;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Balance sheet as of a date.
 *
 * The ledger carries no year-end closing entries, so income and expense are not folded
 * into a fund account. Instead the surplus to date is shown as its own equity line, and
 * assets must equal liabilities + equity + that surplus.
 */
class BalanceSheet extends Component
{
    public string $asOf = '';

    public function mount(): void
    {
        $this->asOf = now()->endOfMonth()->toDateString();
    }

    public function render(LedgerReports $reports): View
    {
        $asOf = Carbon::parse($this->asOf);

        $assets = $reports->balancesByType(AccountType::Asset, null, $asOf);
        $liabilities = $reports->balancesByType(AccountType::Liability, null, $asOf);
        $equity = $reports->balancesByType(AccountType::Equity, null, $asOf);

        $totalAssets = $reports->total($assets);
        $surplus = $reports->accumulatedSurplus($asOf);

        $totalFunded = bcadd(
            bcadd($reports->total($liabilities), $reports->total($equity), 2),
            $surplus,
            2,
        );

        return view('livewire.reports.balance-sheet', [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'totalAssets' => $totalAssets,
            'totalLiabilities' => $reports->total($liabilities),
            'totalEquity' => $reports->total($equity),
            'surplus' => $surplus,
            'totalFunded' => $totalFunded,
            'isBalanced' => bccomp($totalAssets, $totalFunded, 2) === 0,
        ])->layout('components.layouts.app');
    }
}
