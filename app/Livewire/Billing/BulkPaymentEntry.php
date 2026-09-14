<?php

namespace App\Livewire\Billing;

use App\Enums\AccountCode;
use App\Enums\PaymentMethod;
use App\Livewire\Concerns\WithNotices;
use App\Models\Flat;
use App\Models\Payment;
use App\Services\Billing\RecordPayment;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

class BulkPaymentEntry extends Component
{
    use WithNotices;

    public string $defaultReceivedOn = '';

    public string $defaultMethod = 'cash';

    public string $search = '';

    /**
     * @var array<int, array{
     *     enabled: bool,
     *     amount: string,
     *     method: string,
     *     reference: string
     * }>
     */
    public array $entries = [];

    public function mount(): void
    {
        $this->defaultReceivedOn = now()->toDateString();
        $this->initializeEntries();
    }

    public function initializeEntries(): void
    {
        $flats = $this->flatsInBuilding();

        foreach ($flats as $flat) {
            if (! isset($this->entries[$flat->id])) {
                $this->entries[$flat->id] = [
                    'enabled' => false,
                    'amount' => '',
                    'method' => $this->defaultMethod,
                    'reference' => '',
                ];
            }
        }
    }

    public function updatedDefaultMethod(string $method): void
    {
        foreach ($this->entries as $flatId => $entry) {
            if (! $entry['enabled'] || $entry['amount'] === '') {
                $this->entries[$flatId]['method'] = $method;
            }
        }
    }

    public function autoFillDues(): void
    {
        $journal = app(JournalService::class);
        $receivable = $journal->account(AccountCode::ServiceChargeReceivable);
        $flats = $this->flatsInBuilding();

        foreach ($flats as $flat) {
            $due = $journal->balanceFor($receivable, $flat->id);
            if (bccomp($due, '0', 2) > 0) {
                $this->entries[$flat->id] = [
                    'enabled' => true,
                    'amount' => $due,
                    'method' => $this->entries[$flat->id]['method'] ?? $this->defaultMethod,
                    'reference' => $this->entries[$flat->id]['reference'] ?? '',
                ];
            }
        }
    }

    public function selectAll(): void
    {
        foreach ($this->entries as $flatId => $entry) {
            $this->entries[$flatId]['enabled'] = true;
        }
    }

    public function clearAll(): void
    {
        foreach ($this->entries as $flatId => $entry) {
            $this->entries[$flatId]['enabled'] = false;
            $this->entries[$flatId]['amount'] = '';
            $this->entries[$flatId]['reference'] = '';
        }
    }

    public function save(): void
    {
        $this->authorize('create', Payment::class);

        $this->validate([
            'defaultReceivedOn' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $rules = [];
        $messages = [];
        $enabledCount = 0;

        foreach ($this->entries as $flatId => $entry) {
            if ($entry['enabled']) {
                $enabledCount++;
                $rules["entries.{$flatId}.amount"] = ['required', 'numeric', 'gt:0'];
                $rules["entries.{$flatId}.method"] = ['required', 'string', 'in:cash,bank,bkash,nagad'];
                $rules["entries.{$flatId}.reference"] = ['nullable', 'string', 'max:255'];
            }
        }

        if ($enabledCount === 0) {
            $this->notify(__('billing.no_payments_entered'), 'error');

            return;
        }

        $this->validate($rules, $messages);

        $receivedOn = Carbon::parse($this->defaultReceivedOn);
        $flats = $this->flatsInBuilding()->keyBy('id');
        $recordPayment = app(RecordPayment::class);
        $recordedCount = 0;

        DB::transaction(function () use ($flats, $recordPayment, $receivedOn, &$recordedCount): void {
            foreach ($this->entries as $flatId => $entry) {
                if (! $entry['enabled']) {
                    continue;
                }

                $flat = $flats->get($flatId);
                if ($flat === null) {
                    continue;
                }

                $amount = bcadd($entry['amount'], '0', 2);
                if (bccomp($amount, '0', 2) <= 0) {
                    continue;
                }

                $recordPayment->handle(
                    flat: $flat,
                    amount: $amount,
                    method: PaymentMethod::from($entry['method']),
                    receivedOn: $receivedOn,
                    reference: $entry['reference'] !== '' ? $entry['reference'] : null,
                );

                $recordedCount++;
            }
        });

        $this->clearAll();
        $this->notify(__('billing.bulk_payments_saved', ['count' => $recordedCount]));
    }

    /**
     * @return Collection<int, Flat>
     */
    private function flatsInBuilding(): Collection
    {
        $building = app(CurrentBuilding::class)->get();

        return Flat::query()
            ->when($building !== null, fn ($query) => $query->where('building_id', $building->id))
            ->with(['owner', 'building'])
            ->orderBy('number')
            ->get();
    }

    /**
     * @return list<array{
     *     flat: Flat,
     *     receivable: string,
     *     advance: string,
     *     netDue: string
     * }>
     */
    public function getFlatsDataProperty(): array
    {
        $flats = $this->flatsInBuilding();
        $journal = app(JournalService::class);
        $receivableAccount = $journal->account(AccountCode::ServiceChargeReceivable);
        $advanceAccount = $journal->account(AccountCode::AdvanceFromOwners);

        $data = [];
        $search = trim(strtolower($this->search));

        foreach ($flats as $flat) {
            if ($search !== '') {
                $matchNumber = str_contains(strtolower($flat->number), $search);
                $matchOwner = $flat->owner !== null && str_contains(strtolower($flat->owner->name), $search);
                if (! $matchNumber && ! $matchOwner) {
                    continue;
                }
            }

            $receivable = $journal->balanceFor($receivableAccount, $flat->id);
            $advance = $journal->balanceFor($advanceAccount, $flat->id);

            $data[] = [
                'flat' => $flat,
                'receivable' => $receivable,
                'advance' => $advance,
                'netDue' => bcsub($receivable, $advance, 2),
            ];
        }

        return $data;
    }

    public function render(): View
    {
        $this->authorize('create', Payment::class);

        return view('livewire.billing.bulk-payment-entry', [
            'methods' => PaymentMethod::cases(),
            'flatsData' => $this->flatsData,
        ])->layout('components.layouts.app');
    }
}
