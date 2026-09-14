<?php

namespace App\Livewire;

use App\Enums\PaymentMethod;
use App\Livewire\Concerns\WithNotices;
use App\Models\Flat;
use App\Models\Payment;
use App\Services\Billing\ReversePayment;
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
    use WithNotices;
    use WithPagination;

    public ?int $flatId = null;

    public string $method = '';

    public string $statusFilter = 'all';

    public string $from = '';

    public string $to = '';

    public string $search = '';

    public bool $showReversalModal = false;

    public ?int $reversingPaymentId = null;

    public string $reversalReason = '';

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

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

    public function openReversalModal(int $paymentId): void
    {
        $payment = Payment::findOrFail($paymentId);
        $this->authorize('reverse', $payment);

        $this->reversingPaymentId = $payment->id;
        $this->reversalReason = '';
        $this->showReversalModal = true;
    }

    public function closeReversalModal(): void
    {
        $this->showReversalModal = false;
        $this->reversingPaymentId = null;
        $this->reversalReason = '';
    }

    public function reversePayment(): void
    {
        if ($this->reversingPaymentId === null) {
            return;
        }

        $payment = Payment::findOrFail($this->reversingPaymentId);
        $this->authorize('reverse', $payment);

        $this->validate([
            'reversalReason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        app(ReversePayment::class)->handle($payment, $this->reversalReason);

        $this->notify(__('billing.payment_reversal_success', ['receipt' => $payment->receipt_no]));
        $this->closeReversalModal();
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
            ->with(['flat.owner', 'allocations.bill', 'receivedBy', 'reversedBy'])
            ->whereIn('flat_id', $flats->pluck('id'))
            ->when($this->flatId !== null, fn ($query) => $query->where('flat_id', $this->flatId))
            ->when($this->method !== '', fn ($query) => $query->where('method', $this->method))
            ->when($this->statusFilter === 'active', fn ($query) => $query->whereNull('reversed_at'))
            ->when($this->statusFilter === 'reversed', fn ($query) => $query->whereNotNull('reversed_at'))
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
