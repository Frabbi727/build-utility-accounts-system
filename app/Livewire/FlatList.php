<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithCrudModal;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Services\Billing\ChargeCalculator;
use App\Services\Masters\GenerateFlats;
use App\Services\Reporting\LedgerReports;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class FlatList extends Component
{
    use WithCrudModal, WithPagination;

    public string $search = '';

    public string $number = '';

    public ?int $floorId = null;

    public ?int $ownerId = null;

    public ?int $unitTypeId = null;

    public string $sizeSqft = '0';

    public bool $isActive = true;

    /** The bulk generator panel, which is closed until asked for. */
    public bool $showBulkForm = false;

    public int $fromLevel = 1;

    public int $toLevel = 6;

    public string $suffixes = 'A,B';

    public string $bulkSizeSqft = '1200';

    public bool $createMissingFloors = true;

    private function building(): ?Building
    {
        return app(CurrentBuilding::class)->get();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formRules(): array
    {
        return [
            'number' => [
                'required', 'string', 'max:255',
                Rule::unique('flats', 'number')
                    ->where('building_id', app(CurrentBuilding::class)->id())
                    ->ignore($this->editingId),
            ],
            'floorId' => [
                'nullable', 'integer',
                Rule::exists('floors', 'id')->where('building_id', app(CurrentBuilding::class)->id()),
            ],
            'ownerId' => ['nullable', 'integer', 'exists:owners,id'],
            'unitTypeId' => [
                'nullable', 'integer',
                // Scoped to this building: no foreign key can express that a flat's unit
                // type must belong to the same building as the flat.
                Rule::exists('unit_types', 'id')->where('building_id', app(CurrentBuilding::class)->id()),
            ],
            'sizeSqft' => ['required', 'numeric', 'min:0'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @param  Flat|null  $record
     */
    protected function fillForm(?Model $record): void
    {
        $this->number = $record->number ?? '';
        $this->floorId = $record?->floor_id;
        $this->ownerId = $record?->owner_id;
        $this->unitTypeId = $record?->unit_type_id;
        $this->sizeSqft = $record === null ? '0' : (string) $record->size_sqft;
        $this->isActive = $record->is_active ?? true;
    }

    protected function findRecord(int $id): Flat
    {
        return Flat::where('building_id', app(CurrentBuilding::class)->id())->findOrFail($id);
    }

    protected function authorizeAction(string $ability, ?Model $record = null): void
    {
        // FlatPolicy speaks in terms of manage() rather than per-verb abilities.
        $this->authorize('manage', Flat::class);
    }

    protected function persist(): Flat
    {
        $flat = $this->editingId === null ? new Flat : $this->findRecord($this->editingId);

        $flat->fill([
            'building_id' => app(CurrentBuilding::class)->getOrFail()->id,
            'floor_id' => $this->floorId,
            'owner_id' => $this->ownerId,
            'unit_type_id' => $this->unitTypeId,
            'number' => $this->number,
            'size_sqft' => $this->sizeSqft,
            'is_active' => $this->isActive,
        ])->save();

        return $flat;
    }

    /**
     * Flats are never deleted once they carry bills or ledger history; they are
     * deactivated instead, which also drops them out of the next bill run.
     */
    public function toggleActive(int $id): void
    {
        $flat = $this->findRecord($id);

        $this->authorizeAction('update', $flat);

        $flat->is_active = ! $flat->is_active;
        $flat->save();
    }

    public function startBulk(): void
    {
        $this->authorizeAction('create');

        $this->resetValidation();
        $this->showBulkForm = true;
    }

    public function cancelBulk(): void
    {
        $this->resetValidation();
        $this->showBulkForm = false;
    }

    public function generateFlats(GenerateFlats $generator): void
    {
        $this->authorizeAction('create');

        $this->validate([
            'fromLevel' => ['required', 'integer', 'min:0', 'max:200'],
            'toLevel' => ['required', 'integer', 'min:0', 'max:200', 'gte:fromLevel'],
            'suffixes' => ['required', 'string', 'max:255'],
            'bulkSizeSqft' => ['required', 'numeric', 'min:0'],
        ]);

        $suffixes = $generator->parseSuffixes($this->suffixes);

        if ($suffixes === []) {
            $this->addError('suffixes', __('validation.required', ['attribute' => __('masters.flat_suffixes')]));

            return;
        }

        $created = $generator->handle(
            app(CurrentBuilding::class)->getOrFail(),
            $this->fromLevel,
            $this->toLevel,
            $suffixes,
            $this->bulkSizeSqft,
            $this->createMissingFloors,
        );

        $this->notify($created === 0
            ? __('masters.no_flats_generated')
            : __('masters.flats_generated', ['count' => $created]));

        $this->showBulkForm = false;
        $this->resetPage();
    }

    /**
     * @param  Collection<int, Flat>  $flats
     * @return Collection<int, string>
     */
    private function monthlyCharges(Collection $flats, ?Building $building, ChargeCalculator $charges): Collection
    {
        if ($building === null) {
            return collect();
        }

        return $flats->mapWithKeys(function (Flat $flat) use ($building, $charges): array {
            $flat->setRelation('building', $building);

            return [$flat->id => $flat->is_active ? $charges->totalFor($flat) : '0.00'];
        });
    }

    public function render(LedgerReports $reports, ChargeCalculator $charges): View
    {
        $this->authorize('viewAny', Flat::class);

        $building = $this->building();

        $flats = Flat::query()
            ->when($building !== null, fn ($q) => $q->where('building_id', $building->id))
            ->when($building === null, fn ($q) => $q->whereRaw('1 = 0'))
            ->with(['building', 'owner', 'floor', 'chargeOverrides'])
            ->when($this->search !== '', fn ($q) => $q->where('number', 'ilike', "%{$this->search}%"))
            ->orderBy('number')
            ->paginate(20);

        // Dues come from the ledger, never from a stored balance.
        $outstanding = $reports->outstandingByFlat();

        $dues = $flats->mapWithKeys(fn (Flat $flat): array => [
            $flat->id => $outstanding[$flat->id] ?? '0.00',
        ]);

        return view('livewire.flat-list', [
            'building' => $building,
            'flats' => $flats,
            'dues' => $dues,
            'monthlyCharges' => $this->monthlyCharges($flats->getCollection(), $building, $charges),
            'floors' => $building === null ? collect() : $building->floors()->orderBy('level')->get(),
            'owners' => Owner::orderBy('name')->get(),
            'unitTypes' => $building === null
                ? collect()
                : $building->unitTypes()->orderBy('sort_order')->orderBy('name')->get(),
        ])->layout('components.layouts.app');
    }
}
