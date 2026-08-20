<?php

namespace App\Livewire;

use App\Enums\PaymentMethod;
use App\Models\Flat;
use App\Models\Payment;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Payment history: every receipt taken, and what each one settled.
 *
 * The Collection Report answers "how much came in this month, by method". This answers
 * "who paid what, and against which bill" — the question an operator asks when an owner
 * disputes a balance.
 */
class PaymentList extends Component
{
    use WithPagination;

    public ?int $flatId = null;

    public string $method = '';

    public string $from = '';

    public string $to = '';

    public string $search = '';

    public function updatingFlatId(): void
    {
        $this->resetPage();
    }

    public function updatingMethod(): void
    {
        $this->resetPage();
    }

    public function updatingFrom(): void
    {
        $this->resetPage();
    }

    public function updatingTo(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return Collection<int, Flat>
     */
    private function flatsInBuilding(): Collection
    {
        $building = app(CurrentBuilding::class)->get();

        return Flat::query()
            ->when($building !== null, fn ($query) => $query->where('building_id', $building->id))
            ->orderBy('number')
            ->get();
    }

    public function render(): View
    {
        $this->authorize('viewAny', Payment::class);

        $flats = $this->flatsInBuilding();

        $payments = Payment::query()
            ->with(['flat.owner', 'allocations.bill', 'receivedBy'])
            ->whereIn('flat_id', $flats->pluck('id'))
            ->when($this->flatId !== null, fn ($query) => $query->where('flat_id', $this->flatId))
            ->when($this->method !== '', fn ($query) => $query->where('method', $this->method))
            ->when($this->from !== '', fn ($query) => $query->whereDate('received_on', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('received_on', '<=', $this->to))
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';

                $query->where(function ($inner) use ($term): void {
                    $inner->where('receipt_no', 'ilike', $term)
                        ->orWhere('reference', 'ilike', $term);
                });
            })
            ->orderByDesc('received_on')
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.payment-list', [
            'payments' => $payments,
            'flats' => $flats,
            'methods' => PaymentMethod::cases(),
        ])->layout('components.layouts.app');
    }
}
