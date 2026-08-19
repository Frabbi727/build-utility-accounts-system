<?php

namespace App\Livewire\Masters;

use App\Enums\AccountType;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Account;
use App\Models\AdHocCharge;
use App\Models\Flat;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One-off charges waiting to be billed, scoped to the current building's flats.
 *
 * A charge is picked up by the next bill generated on or after its effective month.
 * Once applied it becomes history: the policy stops it being edited or deleted, and a
 * correction is made on the ledger instead.
 */
class AdHocChargeList extends Component
{
    use WithCrudModal, WithPagination;

    public ?int $flatId = null;

    public ?int $accountId = null;

    public string $description = '';

    public string $amount = '';

    public string $effectiveMonth = '';

    /**
     * @return array<string, mixed>
     */
    protected function formRules(): array
    {
        return [
            'flatId' => [
                'required', 'integer',
                // Only flats in the building the operator is working in.
                Rule::exists('flats', 'id')->where('building_id', app(CurrentBuilding::class)->id()),
            ],
            'accountId' => ['required', 'integer', 'exists:accounts,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'effectiveMonth' => ['required', 'date_format:Y-m'],
        ];
    }

    /**
     * @param  AdHocCharge|null  $record
     */
    protected function fillForm(?Model $record): void
    {
        $this->flatId = $record?->flat_id;
        $this->accountId = $record?->account_id;
        $this->description = $record->description ?? '';
        $this->amount = $record === null ? '' : (string) $record->amount;
        $this->effectiveMonth = $record?->effective_month->format('Y-m') ?? now()->format('Y-m');
    }

    protected function findRecord(int $id): AdHocCharge
    {
        return AdHocCharge::whereIn('flat_id', Flat::where('building_id', app(CurrentBuilding::class)->id())->select('id'))
            ->findOrFail($id);
    }

    protected function authorizeAction(string $ability, ?Model $record = null): void
    {
        $this->authorize($ability, $record ?? AdHocCharge::class);
    }

    protected function persist(): AdHocCharge
    {
        $charge = $this->editingId === null ? new AdHocCharge : $this->findRecord($this->editingId);

        $charge->fill([
            'flat_id' => $this->flatId,
            'account_id' => $this->accountId,
            'description' => $this->description,
            'amount' => $this->amount,
            'effective_month' => Carbon::createFromFormat('Y-m', $this->effectiveMonth)->startOfMonth(),
        ])->save();

        session()->flash('status', __('masters.saved'));

        return $charge;
    }

    public function render(): View
    {
        $this->authorize('viewAny', AdHocCharge::class);

        $buildingId = app(CurrentBuilding::class)->id();

        return view('livewire.masters.ad-hoc-charge-list', [
            'charges' => AdHocCharge::query()
                ->with(['flat', 'account', 'bill'])
                ->whereIn('flat_id', Flat::where('building_id', $buildingId)->select('id'))
                ->orderByDesc('effective_month')
                ->orderByDesc('id')
                ->paginate(20),
            'flats' => Flat::where('building_id', $buildingId)->where('is_active', true)->orderBy('number')->get(),
            'incomeAccounts' => Account::where('is_postable', true)
                ->where('is_active', true)
                ->where('type', AccountType::Income)
                ->orderBy('code')
                ->get(),
        ])->layout('components.layouts.app');
    }
}
