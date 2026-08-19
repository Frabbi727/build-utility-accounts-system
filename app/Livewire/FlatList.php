<?php

namespace App\Livewire;

use App\Models\Flat;
use App\Services\Reporting\LedgerReports;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class FlatList extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render(LedgerReports $reports): View
    {
        $this->authorize('viewAny', Flat::class);

        $flats = Flat::query()
            ->with(['building', 'owner'])
            ->when($this->search !== '', fn ($q) => $q->where('number', 'ilike', "%{$this->search}%"))
            ->orderBy('number')
            ->paginate(20);

        // Dues come from the ledger, never from a stored balance.
        $outstanding = $reports->outstandingByFlat();

        $dues = $flats->mapWithKeys(fn (Flat $flat): array => [
            $flat->id => $outstanding[$flat->id] ?? '0.00',
        ]);

        return view('livewire.flat-list', ['flats' => $flats, 'dues' => $dues])
            ->layout('components.layouts.app');
    }
}
