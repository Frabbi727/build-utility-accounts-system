<?php

namespace App\Livewire;

use App\Livewire\Reports\Concerns\WithLedgerDrillDown;
use App\Models\Account;
use App\Services\JournalService;
use App\Services\Reporting\LedgerReports;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Trial balance built straight from journal_lines. If this does not balance,
 * something has written to the ledger outside JournalService.
 */
class TrialBalance extends Component
{
    use WithLedgerDrillDown;

    public string $from = '';

    public string $to = '';

    public string $asOf = '';

    public function mount(): void
    {
        $this->to = now()->endOfMonth()->toDateString();
        $this->asOf = $this->to;
    }

    public function updatedAsOf(string $value): void
    {
        $this->to = $value;
    }

    public function getIsRangedProperty(): bool
    {
        return $this->from !== '';
    }

    /**
     * @return Collection<int, array{account: Account, debit: numeric-string, credit: numeric-string}|array{account: Account, opening: numeric-string, debit: numeric-string, credit: numeric-string, closing: string}>
     */
    public function rows(): Collection
    {
        $journal = app(JournalService::class);
        $reports = app(LedgerReports::class);

        $toDate = Carbon::parse($this->to !== '' ? $this->to : ($this->asOf !== '' ? $this->asOf : now()->toDateString()));
        $fromDate = $this->from !== '' ? Carbon::parse($this->from) : null;

        return Account::query()
            ->where('is_postable', true)
            ->with(['journalLines' => function ($q) use ($fromDate, $toDate): void {
                $q->whereHas('journalEntry', function ($e) use ($fromDate, $toDate): void {
                    if ($fromDate !== null) {
                        $e->whereDate('entry_date', '>=', $fromDate);
                    }
                    $e->whereDate('entry_date', '<=', $toDate);
                });
            }])
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($reports, $journal, $fromDate, $toDate): array {
                $debit = '0.00';
                $credit = '0.00';

                foreach ($account->journalLines as $line) {
                    $debit = bcadd($debit, (string) $line->debit, 2);
                    $credit = bcadd($credit, (string) $line->credit, 2);
                }

                if ($fromDate !== null) {
                    $opening = $reports->openingBalance($account, $fromDate);
                    $closing = $journal->balanceFor($account, null, $toDate);

                    return [
                        'account' => $account,
                        'opening' => $opening,
                        'debit' => $debit,
                        'credit' => $credit,
                        'closing' => $closing,
                    ];
                }

                // Present each account on its net side, the way a standard trial balance reads.
                $net = bcsub($debit, $credit, 2);

                return [
                    'account' => $account,
                    'debit' => bccomp($net, '0', 2) > 0 ? $net : '0.00',
                    'credit' => bccomp($net, '0', 2) < 0 ? bcmul($net, '-1', 2) : '0.00',
                ];
            })
            ->filter(function (array $row) use ($fromDate): bool {
                if ($fromDate !== null) {
                    return bccomp($row['opening'] ?? '0.00', '0', 2) !== 0
                        || bccomp($row['debit'], '0', 2) !== 0
                        || bccomp($row['credit'], '0', 2) !== 0
                        || bccomp($row['closing'] ?? '0.00', '0', 2) !== 0;
                }

                return bccomp($row['debit'], '0', 2) !== 0 || bccomp($row['credit'], '0', 2) !== 0;
            })
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
            'isRanged' => $this->getIsRangedProperty(),
        ])->layout('components.layouts.app');
    }
}
