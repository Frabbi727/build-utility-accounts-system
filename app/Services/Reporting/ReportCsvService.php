<?php

namespace App\Services\Reporting;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Flat;
use App\Models\Payment;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Illuminate\Support\Carbon;

class ReportCsvService
{
    public function __construct(
        private readonly LedgerReports $reports,
        private readonly JournalService $journal,
    ) {}

    public function trialBalance(?Carbon $from = null, ?Carbon $to = null): string
    {
        $to = $to ?? now()->endOfMonth();
        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return '';
        }

        fputcsv($output, ['Code', 'Account Name', 'Type', 'Opening Balance', 'Debit', 'Credit', 'Net Balance']);

        $accounts = Account::where('is_postable', true)->orderBy('code')->get();

        foreach ($accounts as $account) {
            $opening = $from !== null ? $this->reports->openingBalance($account, $from) : '0.00';

            $movements = $this->reports->movements($account, $from, $to);
            $debit = '0.00';
            $credit = '0.00';

            foreach ($movements as $line) {
                $debit = bcadd($debit, (string) $line->debit, 2);
                $credit = bcadd($credit, (string) $line->credit, 2);
            }

            // As-of balance up to $to
            $netBalance = $this->journal->balanceFor($account, null, $to);

            // Skip zero rows if opening, movements, and net are all 0
            if (
                bccomp($opening, '0', 2) === 0
                && bccomp($debit, '0', 2) === 0
                && bccomp($credit, '0', 2) === 0
                && bccomp($netBalance, '0', 2) === 0
            ) {
                continue;
            }

            fputcsv($output, [
                $account->code,
                $account->name,
                $account->type->name,
                $opening,
                $debit,
                $credit,
                $netBalance,
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv !== false ? $csv : '';
    }

    public function ownerDues(?Carbon $asOf = null): string
    {
        $asOf = $asOf ?? now();
        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return '';
        }

        fputcsv($output, ['Flat', 'Building', 'Owner', 'Current (0-30)', '31-60 Days', '61-90 Days', '90+ Days', 'Total Outstanding']);

        $rows = $this->reports->agingByFlat($asOf);

        // Filter by current building if selected
        $building = app(CurrentBuilding::class)->get();
        if ($building !== null) {
            $rows = $rows->filter(fn (array $r) => $r['flat']->building_id === $building->id);
        }

        foreach ($rows as $r) {
            /** @var Flat $flat */
            $flat = $r['flat'];

            fputcsv($output, [
                $flat->number,
                $flat->building !== null ? $flat->building->displayName() : '',
                $flat->owner !== null ? $flat->owner->name : '—',
                $r['current'],
                $r['days_31_60'],
                $r['days_61_90'],
                $r['days_90_plus'],
                $r['outstanding'],
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv !== false ? $csv : '';
    }

    public function incomeExpenditure(Carbon $from, Carbon $to): string
    {
        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return '';
        }

        fputcsv($output, ['Section', 'Code', 'Account Name', 'Amount']);

        $incomes = $this->reports->balancesByType(AccountType::Income, $from, $to);
        foreach ($incomes as $r) {
            fputcsv($output, ['Income', $r['account']->code, $r['account']->name, $r['amount']]);
        }

        $expenses = $this->reports->balancesByType(AccountType::Expense, $from, $to);
        foreach ($expenses as $r) {
            fputcsv($output, ['Expenditure', $r['account']->code, $r['account']->name, $r['amount']]);
        }

        $totalIncome = $this->reports->total($incomes);
        $totalExpense = $this->reports->total($expenses);
        $netSurplus = bcsub($totalIncome, $totalExpense, 2);

        fputcsv($output, ['Summary', '', 'Total Income', $totalIncome]);
        fputcsv($output, ['Summary', '', 'Total Expenditure', $totalExpense]);
        fputcsv($output, ['Summary', '', 'Net Surplus / (Deficit)', $netSurplus]);

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv !== false ? $csv : '';
    }

    public function balanceSheet(?Carbon $asOf = null): string
    {
        $asOf = $asOf ?? now();
        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return '';
        }

        fputcsv($output, ['Section', 'Code', 'Account Name', 'Amount']);

        $assets = $this->reports->balancesByType(AccountType::Asset, null, $asOf);
        foreach ($assets as $r) {
            fputcsv($output, ['Assets', $r['account']->code, $r['account']->name, $r['amount']]);
        }

        $liabilities = $this->reports->balancesByType(AccountType::Liability, null, $asOf);
        foreach ($liabilities as $r) {
            fputcsv($output, ['Liabilities', $r['account']->code, $r['account']->name, $r['amount']]);
        }

        $equity = $this->reports->balancesByType(AccountType::Equity, null, $asOf);
        foreach ($equity as $r) {
            fputcsv($output, ['Equity', $r['account']->code, $r['account']->name, $r['amount']]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv !== false ? $csv : '';
    }

    public function cashBook(Account $account, Carbon $from, Carbon $to): string
    {
        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return '';
        }

        fputcsv($output, ['Date', 'Entry ID', 'Description', 'Reference', 'Debit', 'Credit', 'Running Balance']);

        $opening = $this->reports->openingBalance($account, $from);
        $running = $opening;

        fputcsv($output, [$from->toDateString(), '', 'Opening Balance', '', '0.00', '0.00', $opening]);

        $movements = $this->reports->movements($account, $from, $to);
        foreach ($movements as $line) {
            $debit = (string) $line->debit;
            $credit = (string) $line->credit;

            // Assets increase on debit, decrease on credit
            $running = bcadd($running, $debit, 2);
            $running = bcsub($running, $credit, 2);

            fputcsv($output, [
                $line->journalEntry->entry_date->toDateString(),
                $line->journalEntry->id,
                $line->journalEntry->description,
                $line->reference ?? '',
                $debit,
                $credit,
                $running,
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv !== false ? $csv : '';
    }

    public function collections(?Carbon $from = null, ?Carbon $to = null): string
    {
        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return '';
        }

        fputcsv($output, ['Date', 'Receipt No', 'Flat', 'Building', 'Owner', 'Method', 'Reference', 'Amount']);

        $payments = $this->reports->collections($from, $to);

        $building = app(CurrentBuilding::class)->get();
        if ($building !== null) {
            $payments = $payments->filter(fn (Payment $p) => $p->flat !== null && $p->flat->building_id === $building->id);
        }

        foreach ($payments as $payment) {
            fputcsv($output, [
                $payment->received_on->toDateString(),
                $payment->receipt_no,
                $payment->flat !== null ? $payment->flat->number : '',
                $payment->flat?->building !== null ? $payment->flat->building->displayName() : '',
                $payment->flat?->owner !== null ? $payment->flat->owner->name : '—',
                $payment->method->value,
                $payment->reference ?? '',
                $payment->amount,
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv !== false ? $csv : '';
    }

    public function expenseByCategory(Carbon $from, Carbon $to): string
    {
        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return '';
        }

        fputcsv($output, ['Code', 'Category', 'Amount']);

        $expenses = $this->reports->balancesByType(AccountType::Expense, $from, $to);
        foreach ($expenses as $r) {
            fputcsv($output, [$r['account']->code, $r['account']->name, $r['amount']]);
        }

        fputcsv($output, ['', 'Total Expenses', $this->reports->total($expenses)]);

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv !== false ? $csv : '';
    }
}
