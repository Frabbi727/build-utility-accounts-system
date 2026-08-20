<?php

namespace App\Livewire\Utilities;

use App\Livewire\Concerns\WithCrudModal;
use App\Models\Flat;
use App\Models\Meter;
use App\Models\Utility;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * The building's meters, per utility.
 *
 * Leaving the unit empty makes a **common meter** — one measuring the whole building or a
 * shared load. Those are never billed to anybody: their consumption is a denominator and
 * a system-loss figure, and the money the building actually owes is the utility's own
 * invoice, recovered through a cost distribution.
 */
class MeterList extends Component
{
    use WithCrudModal;

    public ?int $utilityId = null;

    public ?int $flatId = null;

    public string $meterNo = '';

    public int $digits = 6;

    public string $initialReading = '0';

    public ?string $installedOn = null;

    public bool $isActive = true;

    /** Table filter, not part of the form. */
    public ?int $filterUtilityId = null;

    /**
     * @return array<string, mixed>
     */
    protected function formRules(): array
    {
        $buildingId = app(CurrentBuilding::class)->id();

        return [
            // Scoped to this building: no foreign key can express that a meter's utility
            // and its flat must belong to the same building as the meter.
            'utilityId' => [
                'required', 'integer',
                Rule::exists('utilities', 'id')->where('building_id', $buildingId),
            ],
            'flatId' => [
                'nullable', 'integer',
                Rule::exists('flats', 'id')->where('building_id', $buildingId),
            ],
            'meterNo' => [
                'required', 'string', 'max:255',
                Rule::unique('meters', 'meter_no')
                    ->where('utility_id', $this->utilityId)
                    ->ignore($this->editingId),
            ],
            'digits' => ['required', 'integer', 'min:1', 'max:12'],
            'initialReading' => ['required', 'numeric', 'min:0'],
            'installedOn' => ['nullable', 'date'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @param  Meter|null  $record
     */
    protected function fillForm(?Model $record): void
    {
        $this->utilityId = $record?->utility_id;
        $this->flatId = $record?->flat_id;
        $this->meterNo = $record->meter_no ?? '';
        $this->digits = $record->digits ?? 6;
        $this->initialReading = $record === null ? '0' : (string) $record->initial_reading;
        $this->installedOn = $record?->installed_on?->toDateString();
        $this->isActive = $record->is_active ?? true;
    }

    protected function findRecord(int $id): Meter
    {
        return Meter::where('building_id', app(CurrentBuilding::class)->id())->findOrFail($id);
    }

    protected function authorizeAction(string $ability, ?Model $record = null): void
    {
        $this->authorize($ability, $record ?? Meter::class);
    }

    protected function persist(): Meter
    {
        $meter = $this->editingId === null ? new Meter : $this->findRecord($this->editingId);

        $meter->fill([
            'building_id' => app(CurrentBuilding::class)->getOrFail()->id,
            'utility_id' => $this->utilityId,
            'flat_id' => $this->flatId,
            'meter_no' => $this->meterNo,
            'digits' => $this->digits,
            'initial_reading' => $this->initialReading,
            'installed_on' => $this->installedOn,
            'is_active' => $this->isActive,
        ])->save();

        return $meter;
    }

    public function render(): View
    {
        $this->authorize('viewAny', Meter::class);

        $buildingId = app(CurrentBuilding::class)->id();

        return view('livewire.utilities.meter-list', [
            'meters' => Meter::query()
                ->with(['utility', 'flat'])
                ->where('building_id', $buildingId)
                ->when($this->filterUtilityId, fn ($query) => $query->where('utility_id', $this->filterUtilityId))
                ->orderBy('utility_id')
                ->orderBy('meter_no')
                ->get(),
            'utilities' => Utility::where('building_id', $buildingId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'flats' => Flat::where('building_id', $buildingId)
                ->where('is_active', true)
                ->orderBy('number')
                ->get(),
        ])->layout('components.layouts.app');
    }
}
