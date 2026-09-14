<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\WithNotices;
use App\Services\Reporting\LedgerReports;
use App\Support\CurrentBuilding;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Outstanding owner dues, aged. Totals are ledger figures; see
 * LedgerReports::agingByFlat() for how the buckets are derived.
 */
class OwnerDues extends Component
{
    use WithNotices;

    public string $asOf = '';

    public string $bucketFilter = 'all';

    public function mount(): void
    {
        $this->asOf = now()->toDateString();
    }

    public function render(LedgerReports $reports): View
    {
        $allRows = $reports->agingByFlat(Carbon::parse($this->asOf));

        // Filter by current building if selected
        $building = app(CurrentBuilding::class)->get();
        if ($building !== null) {
            $allRows = $allRows->filter(fn (array $r) => $r['flat']->building_id === $building->id);
        }

        $filteredRows = $allRows;
        if ($this->bucketFilter !== 'all') {
            $filteredRows = $allRows->filter(fn (array $r) => bccomp($r[$this->bucketFilter] ?? '0.00', '0', 2) > 0)->values();
        }

        $columns = ['outstanding', 'current', 'days_31_60', 'days_61_90', 'days_90_plus'];

        $totals = [];
        foreach ($columns as $column) {
            $totals[$column] = $filteredRows->reduce(
                fn (string $carry, array $row): string => bcadd($carry, $row[$column], 2),
                '0.00',
            );
        }

        return view('livewire.reports.owner-dues', [
            'rows' => $filteredRows,
            'totals' => $totals,
            'totalCount' => $allRows->count(),
        ])->layout('components.layouts.app');
    }
}
