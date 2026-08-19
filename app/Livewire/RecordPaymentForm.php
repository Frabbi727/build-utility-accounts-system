<?php

namespace App\Livewire;

use App\Enums\PaymentMethod;
use App\Models\Flat;
use App\Services\Billing\RecordPayment;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

class RecordPaymentForm extends Component
{
    public ?int $flatId = null;

    public string $amount = '';

    public string $method = 'cash';

    public string $receivedOn = '';

    public string $reference = '';

    public function mount(): void
    {
        $this->receivedOn = now()->toDateString();
    }

    public function save(RecordPayment $recorder): void
    {
        $this->authorize('manage', Flat::class);

        $this->validate([
            'flatId' => ['required', 'integer', 'exists:flats,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'string', 'in:cash,bank,bkash,nagad'],
            'receivedOn' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = $recorder->handle(
            Flat::findOrFail($this->flatId),
            $this->amount,
            PaymentMethod::from($this->method),
            Carbon::parse($this->receivedOn),
            $this->reference !== '' ? $this->reference : null,
        );

        session()->flash('status', __('billing.payment_recorded', ['receipt' => $payment->receipt_no]));

        $this->reset(['amount', 'reference']);
    }

    public function render(): View
    {
        return view('livewire.record-payment-form', [
            'flats' => Flat::with('building')->orderBy('number')->get(),
            'methods' => PaymentMethod::cases(),
        ])->layout('components.layouts.app');
    }
}
