<?php

namespace App\Livewire\Masters;

use App\Livewire\Concerns\WithCrudModal;
use App\Models\UnitType;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * What kinds of unit this building has — Shop, Office, Flat, Garage.
 *
 * A type earns its keep in two places: charge heads can be restricted to it (so shops
 * pay the shutter cleaning and flats do not), and a cost distribution can weight by it.
 *
 * A type in use cannot be deleted. The foreign key restricts it, but the check is made
 * here first: on Postgres a constraint violation aborts the surrounding transaction, so
 * catching it after the fact leaves nothing usable to re-render. Refusing up front also
 * gives the operator a reason rather than a generic failure.
 */
class UnitTypeList extends Component
{
    use WithCrudModal;

    public string $name = '';

    public ?string $nameBn = null;

    public int $sortOrder = 0;

    /**
     * @return array<string, mixed>
     */
    protected function formRules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('unit_types', 'name')
                    ->where('building_id', app(CurrentBuilding::class)->id())
                    ->ignore($this->editingId),
            ],
            'nameBn' => ['nullable', 'string', 'max:255'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @param  UnitType|null  $record
     */
    protected function fillForm(?Model $record): void
    {
        $this->name = $record->name ?? '';
        $this->nameBn = $record?->name_bn;
        $this->sortOrder = $record->sort_order ?? 0;
    }

    protected function findRecord(int $id): UnitType
    {
        return UnitType::where('building_id', app(CurrentBuilding::class)->id())->findOrFail($id);
    }

    protected function authorizeAction(string $ability, ?Model $record = null): void
    {
        $this->authorize($ability, $record ?? UnitType::class);
    }

    protected function persist(): UnitType
    {
        $type = $this->editingId === null ? new UnitType : $this->findRecord($this->editingId);

        $type->fill([
            'building_id' => app(CurrentBuilding::class)->getOrFail()->id,
            'name' => $this->name,
            'name_bn' => $this->nameBn,
            'sort_order' => $this->sortOrder,
        ])->save();

        session()->flash('status', __('masters.saved'));

        return $type;
    }

    /**
     * @param  UnitType  $record
     */
    protected function deleteRecord(Model $record): bool
    {
        if ($record->flats()->exists() || $record->chargeHeads()->exists()) {
            session()->flash('error', __('masters.unit_type_in_use'));

            return false;
        }

        $record->delete();

        return true;
    }

    public function render(): View
    {
        $this->authorize('viewAny', UnitType::class);

        return view('livewire.masters.unit-type-list', [
            'unitTypes' => UnitType::query()
                ->withCount(['flats', 'chargeHeads'])
                ->where('building_id', app(CurrentBuilding::class)->id())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ])->layout('components.layouts.app');
    }
}
