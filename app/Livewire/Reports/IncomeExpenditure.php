<?php

namespace App\Livewire\Reports;

use App\Enums\AccountType;
use App\Services\Reporting\LedgerReports;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Income & expenditure for a period, on an accrual basis: a service charge counts as
 * income in the month it falls due, not the month it is collected.
 */
class IncomeExpenditure extends Component
{
    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $this->from = now()->startOfYear()->toDateString();
        $this->to = now()->endOfMonth()->toDateString();
    }

    public function render(LedgerReports $reports): View
    {
        $from = Carbon::parse($this->from);
        $to = Carbon::parse($this->to);

        $income = $reports->balancesByType(AccountType::Income, $from, $to);
        $expenses = $reports->balancesByType(AccountType::Expense, $from, $to);

        $totalIncome = $reports->total($income);
        $totalExpense = $reports->total($expenses);

        return view('livewire.reports.income-expenditure', [
            'income' => $income,
            'expenses' => $expenses,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'surplus' => bcsub($totalIncome, $totalExpense, 2),
        ])->layout('components.layouts.app');
    }
}
