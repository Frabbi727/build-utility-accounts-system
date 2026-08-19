<?php

namespace App\Livewire;

use App\Models\Account;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Trial balance built straight from journal_lines. If this does not balance,
 * something has written to the ledger outside JournalService.
 */
class TrialBalance extends Component
{
    public string $asOf = '';

    public function mount(): void
    {
        $this->asOf = now()->endOfMonth()->toDateString();
    }

    /**
     * @return Collection<int, array{account: Account, debit: numeric-string, credit: numeric-string}>
     */
    public function rows(): Collection
    {
        return Account::query()
            ->where('is_postable', true)
            ->with(['journalLines' => fn ($q) => $q->whereHas(
                'journalEntry', fn ($e) => $e->whereDate('entry_date', '<=', $this->asOf)
            )])
            ->orderBy('code')
            ->get()
            ->map(function (Account $account): array {
                $debit = '0.00';
                $credit = '0.00';

                foreach ($account->journalLines as $line) {
                    $debit = bcadd($debit, (string) $line->debit, 2);
                    $credit = bcadd($credit, (string) $line->credit, 2);
                }

                // Present each account on its net side, the way a trial balance reads.
                $net = bcsub($debit, $credit, 2);

                return [
                    'account' => $account,
                    'debit' => bccomp($net, '0', 2) > 0 ? $net : '0.00',
                    'credit' => bccomp($net, '0', 2) < 0 ? bcmul($net, '-1', 2) : '0.00',
                ];
            })
            ->filter(fn (array $row): bool => bccomp($row['debit'], '0', 2) !== 0 || bccomp($row['credit'], '0', 2) !== 0)
            ->values();
    }

    public function render(): View
    {
        $rows = $this->rows();

        $totalDebit = $rows->reduce(fn (string $carry, array $r): string => bcadd($carry, $r['debit'], 2), '0.00');
        $totalCredit = $rows->reduce(fn (string $carry, array $r): string => bcadd($carry, $r['credit'], 2), '0.00');

        return view('livewire.trial-balance', [
            'rows' => $rows,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
            'isBalanced' => bccomp($totalDebit, $totalCredit, 2) === 0,
        ])->layout('components.layouts.app');
    }
}
