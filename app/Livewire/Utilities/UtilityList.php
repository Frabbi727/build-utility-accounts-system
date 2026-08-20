<?php

namespace App\Livewire\Utilities;

use App\Enums\AccountType;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Account;
use App\Models\Utility;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * The metered things this building sells on — electricity, gas, water.
 *
 * A utility is data rather than an enum precisely so this screen exists: a market that
 * starts sub-metering water should not need a deployment to do it.
 */
class UtilityList extends Component
{
    use WithCrudModal;

    public string $name = '';

    public ?string $nameBn = null;

    public ?int $accountId = null;

    public string $unitLabel = '';

    public int $sortOrder = 0;

    public bool $isActive = true;

    /**
     * @return array<string, mixed>
     */
    protected function formRules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('utilities', 'name')
                    ->where('building_id', app(CurrentBuilding::class)->id())
                    ->ignore($this->editingId),
            ],
            'nameBn' => ['nullable', 'string', 'max:255'],
            // Income only: consumption billed to a unit is revenue, and crediting an
            // expense account here would quietly understate expenses in every report.
            'accountId' => [
                'required', 'integer',
                Rule::exists('accounts', 'id')
                    ->where('type', AccountType::Income->value)
                    ->where('is_postable', true)
                    ->where('is_active', true),
            ],
            'unitLabel' => ['required', 'string', 'max:20'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:999'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @param  Utility|null  $record
     */
    protected function fillForm(?Model $record): void
    {
        $this->name = $record->name ?? '';
        $this->nameBn = $record?->name_bn;
        $this->accountId = $record?->account_id;
        $this->unitLabel = $record->unit_label ?? '';
        $this->sortOrder = $record->sort_order ?? 0;
        $this->isActive = $record->is_active ?? true;
    }

    protected function findRecord(int $id): Utility
    {
        return Utility::where('building_id', app(CurrentBuilding::class)->id())->findOrFail($id);
    }

    protected function authorizeAction(string $ability, ?Model $record = null): void
    {
        $this->authorize($ability, $record ?? Utility::class);
    }

    protected function persist(): Utility
    {
        $utility = $this->editingId === null ? new Utility : $this->findRecord($this->editingId);

        $utility->fill([
            'building_id' => app(CurrentBuilding::class)->getOrFail()->id,
            'account_id' => $this->accountId,
            'name' => $this->name,
            'name_bn' => $this->nameBn,
            'unit_label' => $this->unitLabel,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
        ])->save();

        return $utility;
    }

    public function toggleActive(int $id): void
    {
        $utility = $this->findRecord($id);
        $this->authorizeAction('update', $utility);

        $utility->is_active = ! $utility->is_active;
        $utility->save();
    }

    public function render(): View
    {
        $this->authorize('viewAny', Utility::class);

        return view('livewire.utilities.utility-list', [
            'utilities' => Utility::query()
                ->with(['account'])
                ->withCount(['meters', 'tariffs'])
                ->where('building_id', app(CurrentBuilding::class)->id())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'incomeAccounts' => Account::where('is_postable', true)
                ->where('is_active', true)
                ->where('type', AccountType::Income)
                ->orderBy('code')
                ->get(),
        ])->layout('components.layouts.app');
    }
}
