<?php

namespace App\Livewire\Reports;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Services\Reporting\LedgerReports;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Receipts taken over a period, grouped by payment method, reconciled against the
 * cash and bank debits the ledger recorded for those same payments.
 */
class CollectionReport extends Component
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
        $from = Carbon::parse($this->from);
        $to = Carbon::parse($this->to);

        $payments = $reports->collections($from, $to);

        $total = $payments->reduce(
            fn (string $carry, Payment $payment): string => bcadd($carry, (string) $payment->amount, 2),
            '0.00',
        );

        $byMethod = collect(PaymentMethod::cases())
            ->map(fn (PaymentMethod $method): array => [
                'method' => $method,
                'count' => $payments->where('method', $method)->count(),
                'amount' => $payments->where('method', $method)->reduce(
                    fn (string $carry, Payment $payment): string => bcadd($carry, (string) $payment->amount, 2),
                    '0.00',
                ),
            ])
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->values();

        $ledgerTotal = $reports->ledgerCollections($from, $to);

        return view('livewire.reports.collection-report', [
            'payments' => $payments,
            'byMethod' => $byMethod,
            'total' => $total,
            'ledgerTotal' => $ledgerTotal,
            'reconciles' => bccomp($total, $ledgerTotal, 2) === 0,
        ])->layout('components.layouts.app');
    }
}
