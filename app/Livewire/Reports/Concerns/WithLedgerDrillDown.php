<?php

namespace App\Livewire\Reports\Concerns;

use App\Models\Account;
use App\Services\Reporting\LedgerReports;
use Illuminate\Support\Carbon;

trait WithLedgerDrillDown
{
    public bool $showDrillDown = false;

    public ?int $drillDownAccountId = null;

    public function openDrillDown(int $accountId): void
    {
        $this->drillDownAccountId = $accountId;
        $this->showDrillDown = true;
    }

    public function closeDrillDown(): void
    {
        $this->showDrillDown = false;
        $this->drillDownAccountId = null;
    }

    /**
     * @return array{
     *     account: Account,
     *     opening: numeric-string,
     *     movements: list<array{
     *         date: string,
     *         id: int,
     *         description: string,
     *         reference: string,
     *         debit: string,
     *         credit: string,
     *         running: string
     *     }>,
     *     totalDebit: string,
     *     totalCredit: string,
     *     closing: string
     * }|null
     */
    public function getDrillDownDetails(): ?array
    {
        if ($this->drillDownAccountId === null) {
            return null;
        }

        $account = Account::find($this->drillDownAccountId);
        if ($account === null) {
            return null;
        }

        $reports = app(LedgerReports::class);

        $fromDate = property_exists($this, 'from') && $this->from !== '' ? Carbon::parse($this->from) : null;
        $toDate = property_exists($this, 'to') && $this->to !== ''
            ? Carbon::parse($this->to)
            : (property_exists($this, 'asOf') && $this->asOf !== '' ? Carbon::parse($this->asOf) : now());

        $opening = $fromDate !== null ? $reports->openingBalance($account, $fromDate) : '0.00';
        $movements = $reports->movements($account, $fromDate, $toDate);

        $running = $opening;
        $totalDebit = '0.00';
        $totalCredit = '0.00';
        $movementRows = [];

        $isDebitNormal = $account->type->isDebitNormal();

        foreach ($movements as $line) {
            $debit = (string) $line->debit;
            $credit = (string) $line->credit;

            $totalDebit = bcadd($totalDebit, $debit, 2);
            $totalCredit = bcadd($totalCredit, $credit, 2);

            if ($isDebitNormal) {
                $running = bcadd($running, $debit, 2);
                $running = bcsub($running, $credit, 2);
            } else {
                $running = bcadd($running, $credit, 2);
                $running = bcsub($running, $debit, 2);
            }

            $movementRows[] = [
                'date' => $line->journalEntry->entry_date->format('d M Y'),
                'id' => $line->journalEntry->id,
                'description' => $line->journalEntry->description,
                'reference' => $line->reference ?? '—',
                'debit' => $debit,
                'credit' => $credit,
                'running' => $running,
            ];
        }

        return [
            'account' => $account,
            'opening' => $opening,
            'movements' => $movementRows,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
            'closing' => $running,
        ];
    }
}
