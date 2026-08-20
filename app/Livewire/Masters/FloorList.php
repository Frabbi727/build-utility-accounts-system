<?php

namespace App\Livewire\Masters;

use App\Livewire\Concerns\WithCrudModal;
use App\Models\Building;
use App\Models\Floor;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Floors of the building the operator is currently working in.
 */
class FloorList extends Component
{
    use WithCrudModal;

    public string $name = '';

    public ?int $level = null;

    private function building(): ?Building
    {
        return app(CurrentBuilding::class)->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'level' => [
                'required', 'integer', 'min:0', 'max:200',
                Rule::unique('floors', 'level')
                    ->where('building_id', app(CurrentBuilding::class)->id())
                    ->ignore($this->editingId),
            ],
        ];
    }

    /**
     * @param  Floor|null  $record
     */
    protected function fillForm(?Model $record): void
    {
        $this->name = $record->name ?? '';
        $this->level = $record?->level;
    }

    protected function findRecord(int $id): Floor
    {
        // Scoped to the current building so a stale id cannot reach another one.
        return Floor::where('building_id', app(CurrentBuilding::class)->id())->findOrFail($id);
    }

    protected function authorizeAction(string $ability, ?Model $record = null): void
    {
        $this->authorize($ability, $record ?? Floor::class);
    }

    protected function persist(): Floor
    {
        $floor = $this->editingId === null ? new Floor : $this->findRecord($this->editingId);

        $floor->fill([
            'building_id' => app(CurrentBuilding::class)->getOrFail()->id,
            'name' => $this->name,
            'level' => $this->level,
        ])->save();

        return $floor;
    }

    public function render(): View
    {
        $this->authorize('viewAny', Floor::class);

        $building = $this->building();

        return view('livewire.masters.floor-list', [
            'building' => $building,
            'floors' => $building === null
                ? collect()
                : $building->floors()->withCount('flats')->orderBy('level')->get(),
        ])->layout('components.layouts.app');
    }
}
