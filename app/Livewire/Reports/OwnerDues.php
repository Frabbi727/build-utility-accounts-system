<?php

namespace App\Livewire\Reports;

use App\Services\Reporting\LedgerReports;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Outstanding owner dues, aged. Totals are ledger figures; see
 * LedgerReports::agingByFlat() for how the buckets are derived.
 */
class OwnerDues extends Component
{
    public string $asOf = '';

    public function mount(): void
    {
        $this->asOf = now()->toDateString();
    }

    public function render(LedgerReports $reports): View
    {
        $rows = $reports->agingByFlat(Carbon::parse($this->asOf));

        $columns = ['outstanding', 'current', 'days_31_60', 'days_61_90', 'days_90_plus'];

        $totals = [];

        foreach ($columns as $column) {
            $totals[$column] = $rows->reduce(
                fn (string $carry, array $row): string => bcadd($carry, $row[$column], 2),
                '0.00',
            );
        }

        return view('livewire.reports.owner-dues', ['rows' => $rows, 'totals' => $totals])
            ->layout('components.layouts.app');
    }
}
