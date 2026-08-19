<?php

namespace App\Livewire\Masters;

use App\Livewire\Concerns\WithCrudModal;
use App\Models\Building;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\View;
use Livewire\Component;

class BuildingList extends Component
{
    use WithCrudModal;

    public string $name = '';

    public ?string $nameBn = null;

    public ?string $address = null;

    public int $dueDayOfMonth = 10;

    /**
     * @return array<string, mixed>
     */
    protected function formRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nameBn' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            // Capped at 28 so every month has the day.
            'dueDayOfMonth' => ['required', 'integer', 'min:1', 'max:28'],
        ];
    }

    /**
     * @param  Building|null  $record
     */
    protected function fillForm(?Model $record): void
    {
        $this->name = $record->name ?? '';
        $this->nameBn = $record?->name_bn;
        $this->address = $record?->address;
        $this->dueDayOfMonth = $record->due_day_of_month ?? 10;
    }

    protected function findRecord(int $id): Building
    {
        return Building::findOrFail($id);
    }

    protected function authorizeAction(string $ability, ?Model $record = null): void
    {
        $this->authorize($ability, $record ?? Building::class);
    }

    protected function persist(): Building
    {
        $building = $this->editingId === null ? new Building : $this->findRecord($this->editingId);

        $building->fill([
            'name' => $this->name,
            'name_bn' => $this->nameBn,
            'address' => $this->address,
            'due_day_of_month' => $this->dueDayOfMonth,
        ])->save();

        // A first building should become the one the operator is working in.
        if (app(CurrentBuilding::class)->id() === null) {
            app(CurrentBuilding::class)->set($building->id);
        }

        session()->flash('status', __('masters.saved'));

        return $building;
    }

    public function render(): View
    {
        $this->authorize('viewAny', Building::class);

        return view('livewire.masters.building-list', [
            'buildings' => Building::withCount(['flats', 'chargeHeads'])->orderBy('name')->get(),
        ])->layout('components.layouts.app');
    }
}
