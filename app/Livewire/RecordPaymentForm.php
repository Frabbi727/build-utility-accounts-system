<?php

namespace App\Livewire;

use App\Enums\PaymentMethod;
use App\Livewire\Concerns\PostsToLedger;
use App\Livewire\Concerns\WithConfirmation;
use App\Livewire\Concerns\WithNotices;
use App\Models\Flat;
use App\Models\Payment;
use App\Models\ServiceChargeBill;
use App\Services\Billing\AllocationPlanner;
use App\Services\Billing\RecordPayment;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Taking money is the least reversible thing an operator does on this system, so the
 * confirmation names the flat and lists the bills the money is about to settle — worked
 * out by the same AllocationPlanner that will do the allocating.
 */
class RecordPaymentForm extends Component
{
    use PostsToLedger;
    use WithConfirmation;
    use WithNotices;

    public ?int $flatId = null;

    /** The payment just recorded, so the receipt is one click away. */
    public ?int $lastPaymentId = null;

    public string $amount = '';

    public string $method = 'cash';

    public string $receivedOn = '';

    public string $reference = '';

    public function mount(): void
    {
        $this->receivedOn = now()->toDateString();
    }

    /**
     * @return list<string>
     */
    protected function confirmableActions(): array
    {
        return ['save'];
    }

    public function askSave(): void
    {
        $this->authorize('create', Payment::class);

        $this->validate($this->rules());
        $this->clearNotice();

        $this->askConfirm('save');
    }

    public function save(): void
    {
        $this->authorize('create', Payment::class);

        $this->validate($this->rules());

        $flat = $this->currentFlat();

        if ($flat === null) {
            $this->addError('flatId', __('billing.flat_not_in_building'));

            return;
        }

        $payment = $this->postGuarded(
            fn () => app(RecordPayment::class)->handle(
                $flat,
                $this->amount,
                PaymentMethod::from($this->method),
                Carbon::parse($this->receivedOn),
                $this->reference !== '' ? $this->reference : null,
            ),
            'receivedOn',
        );

        if ($payment === null) {
            return;
        }

        $this->notify(__('billing.payment_recorded', ['receipt' => $payment->receipt_no]));

        // Kept so the operator can print the receipt straight after taking the money.
        $this->lastPaymentId = $payment->id;

        $this->reset(['amount', 'reference']);
    }

    /**
     * What this payment will settle, for the confirmation dialog.
     *
     * @return array{flat: Flat, lines: list<array{bill: ServiceChargeBill, amount: string}>, allocated: string, advance: string}|null
     */
    public function allocationPreview(): ?array
    {
        $flat = $this->currentFlat();

        if ($flat === null || ! is_numeric($this->amount)) {
            return null;
        }

        return ['flat' => $flat] + app(AllocationPlanner::class)->plan($flat, $this->amount);
    }

    /**
     * The chosen flat, scoped to the building being worked in — a payment must never land
     * on a same-numbered flat in another building.
     */
    private function currentFlat(): ?Flat
    {
        if ($this->flatId === null) {
            return null;
        }

        return $this->flatsInBuilding()->firstWhere('id', $this->flatId);
    }

    /**
     * @return Collection<int, Flat>
     */
    private function flatsInBuilding(): Collection
    {
        $building = app(CurrentBuilding::class)->get();

        return Flat::query()
            ->when($building !== null, fn ($query) => $query->where('building_id', $building->id))
            ->with('building')
            ->orderBy('number')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'flatId' => ['required', 'integer', 'exists:flats,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'string', 'in:cash,bank,bkash,nagad'],
            // A receipt dated in the future parks the entry in a period that has not
            // happened yet and quietly distorts every collection report.
            'receivedOn' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get the ledger balances, unpaid bills, and live allocation for the selected flat.
     *
     * @return array{
     *     flat: Flat,
     *     outstanding: string,
     *     advance: string,
     *     netDue: string,
     *     unpaidBills: \Illuminate\Support\Collection<int, array{bill: ServiceChargeBill, outstanding: string, allocated: string}>,
     *     allocation: array{lines: list<array{bill: ServiceChargeBill, amount: string}>, allocated: string, advance: string}|null
     * }|null
     */
    public function getBillingDetails(): ?array
    {
        $flat = $this->currentFlat();

        if ($flat === null) {
            return null;
        }

        $journal = app(JournalService::class);
        $receivable = $journal->account(\App\Enums\AccountCode::ServiceChargeReceivable);
        $advanceAccount = $journal->account(\App\Enums\AccountCode::AdvanceFromOwners);

        $outstanding = $journal->balanceFor($receivable, $flat->id);
        $advance = $journal->balanceFor($advanceAccount, $flat->id);

        // Calculate live allocation if amount is entered and is valid numeric
        $allocation = null;
        if (is_numeric($this->amount) && bccomp($this->amount, '0', 2) > 0) {
            $allocation = app(AllocationPlanner::class)->plan($flat, $this->amount);
        }

        $unpaidBills = ServiceChargeBill::where('flat_id', $flat->id)
            ->whereIn('status', [
                \App\Enums\BillStatus::Unpaid,
                \App\Enums\BillStatus::PartiallyPaid,
            ])
            ->orderBy('billing_month')
            ->orderBy('id')
            ->get();

        $unpaidBillsData = $unpaidBills->map(function (ServiceChargeBill $bill) use ($allocation): array {
            $allocatedToBill = '0.00';
            if ($allocation !== null) {
                foreach ($allocation['lines'] as $line) {
                    if ($line['bill']->id === $bill->id) {
                        $allocatedToBill = $line['amount'];
                        break;
                    }
                }
            }

            return [
                'bill' => $bill,
                'outstanding' => $bill->outstandingAmount(),
                'allocated' => $allocatedToBill,
            ];
        });

        return [
            'flat' => $flat,
            'outstanding' => $outstanding,
            'advance' => $advance,
            'netDue' => bcsub($outstanding, $advance, 2),
            'unpaidBills' => $unpaidBillsData,
            'allocation' => $allocation,
        ];
    }

    public function render(): View
    {
        return view('livewire.record-payment-form', [
            'flats' => $this->flatsInBuilding(),
            'methods' => PaymentMethod::cases(),
            'billingDetails' => $this->getBillingDetails(),
        ])->layout('components.layouts.app');
    }
}
