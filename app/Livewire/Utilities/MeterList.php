<?php

namespace App\Livewire\Utilities;

use App\Livewire\Concerns\WithCrudModal;
use App\Models\Flat;
use App\Models\FlatUtilityConfig;
use App\Models\Floor;
use App\Models\Meter;
use App\Models\Utility;
use App\Services\Utilities\GenerateUtilityMeters;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
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

    public ?int $filterUtilityId = null;

    /** Auto Generate Wizard State */
    public bool $showAutoGenerateModal = false;

    public string $scope = 'all'; // 'all', 'floor', 'selected'

    public ?int $scopeFloorId = null;

    /** @var list<int> */
    public array $selectedFlatIds = [];

    /** @var list<int> */
    public array $selectedUtilityIds = [];

    /** @var array<int, string> */
    public array $utilityPrefixes = []; // utility_id => prefix string

    /** @var array<string, bool> */
    public array $flatUtilityOverrides = []; // "$flatId-$utilityId" => bool

    public bool $persistOverrides = true;

    /** @var array{totalFlatsCount: int, eligibleMetersCount: int, skippedMetersCount: int, excludedFlatsCount: int, skippedDetails: list<array{flat_id: int, flat_number: string, utility_id: int, utility_name: string, reason: string}>, inactiveWarnings: list<array{flat_id: int, flat_number: string, utility_id: int, utility_name: string, meter_no: string}>, utilityCounts: array<int, int>}|null */
    public ?array $previewSummary = null;

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

    public function startAutoGenerate(): void
    {
        $this->authorizeAction('create');

        $building = app(CurrentBuilding::class)->getOrFail();

        $activeUtilities = Utility::where('building_id', $building->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $activeFlats = Flat::where('building_id', $building->id)
            ->where('is_active', true)
            ->orderBy('number')
            ->get();

        $this->scope = 'all';
        $this->scopeFloorId = null;
        $this->selectedFlatIds = $activeFlats->pluck('id')->all();
        $this->selectedUtilityIds = $activeUtilities->pluck('id')->all();
        $this->flatUtilityOverrides = [];
        $this->persistOverrides = true;

        $savedConfigs = FlatUtilityConfig::whereIn('flat_id', $activeFlats->pluck('id'))
            ->whereIn('utility_id', $activeUtilities->pluck('id'))
            ->get();

        foreach ($savedConfigs as $config) {
            $this->flatUtilityOverrides["{$config->flat_id}-{$config->utility_id}"] = (bool) $config->is_enabled;
        }

        $this->utilityPrefixes = [];
        foreach ($activeUtilities as $utility) {
            $this->utilityPrefixes[$utility->id] = strtoupper(Str::limit($utility->name, 4, ''));
        }

        $this->showAutoGenerateModal = true;
        $this->updatePreview();
    }

    public function cancelAutoGenerate(): void
    {
        $this->showAutoGenerateModal = false;
        $this->scope = 'all';
        $this->scopeFloorId = null;
        $this->selectedFlatIds = [];
        $this->selectedUtilityIds = [];
        $this->utilityPrefixes = [];
        $this->flatUtilityOverrides = [];
        $this->previewSummary = null;
    }

    public function updatedScope(): void
    {
        $building = app(CurrentBuilding::class)->getOrFail();

        if ($this->scope === 'all') {
            $this->selectedFlatIds = Flat::where('building_id', $building->id)
                ->where('is_active', true)
                ->pluck('id')
                ->all();
        } elseif ($this->scope === 'floor' && $this->scopeFloorId !== null) {
            $this->selectedFlatIds = Flat::where('building_id', $building->id)
                ->where('floor_id', $this->scopeFloorId)
                ->where('is_active', true)
                ->pluck('id')
                ->all();
        }

        $this->updatePreview();
    }

    public function updatedScopeFloorId(): void
    {
        if ($this->scope === 'floor') {
            $building = app(CurrentBuilding::class)->getOrFail();
            $this->selectedFlatIds = Flat::where('building_id', $building->id)
                ->where('floor_id', $this->scopeFloorId)
                ->where('is_active', true)
                ->pluck('id')
                ->all();
        }

        $this->updatePreview();
    }

    public function updatedSelectedFlatIds(): void
    {
        $this->updatePreview();
    }

    public function updatedSelectedUtilityIds(): void
    {
        $this->updatePreview();
    }

    public function toggleFlatUtility(int $flatId, int $utilityId): void
    {
        $key = "{$flatId}-{$utilityId}";
        $current = $this->flatUtilityOverrides[$key] ?? true;
        $this->flatUtilityOverrides[$key] = ! $current;

        $this->updatePreview();
    }

    public function updatePreview(?GenerateUtilityMeters $generator = null): void
    {
        $generator ??= app(GenerateUtilityMeters::class);
        $building = app(CurrentBuilding::class)->getOrFail();

        $targetFlatIds = $this->resolveScopedFlatIds();

        $preview = $generator->preview(
            building: $building,
            flatIds: $targetFlatIds,
            utilityIds: $this->selectedUtilityIds,
            flatUtilityOverrides: $this->flatUtilityOverrides,
        );

        $this->previewSummary = [
            'totalFlatsCount' => $preview->totalFlatsCount,
            'eligibleMetersCount' => $preview->eligibleMetersCount,
            'skippedMetersCount' => $preview->skippedMetersCount,
            'excludedFlatsCount' => $preview->excludedFlatsCount,
            'skippedDetails' => $preview->skippedDetails,
            'inactiveWarnings' => $preview->inactiveWarnings,
            'utilityCounts' => $preview->utilityCounts,
        ];
    }

    /**
     * @return list<int>
     */
    private function resolveScopedFlatIds(): array
    {
        $building = app(CurrentBuilding::class)->getOrFail();

        if ($this->scope === 'all') {
            return Flat::where('building_id', $building->id)
                ->where('is_active', true)
                ->pluck('id')
                ->all();
        }

        if ($this->scope === 'floor') {
            if ($this->scopeFloorId === null) {
                return [];
            }

            return Flat::where('building_id', $building->id)
                ->where('floor_id', $this->scopeFloorId)
                ->where('is_active', true)
                ->pluck('id')
                ->all();
        }

        return Flat::where('building_id', $building->id)
            ->whereIn('id', $this->selectedFlatIds)
            ->where('is_active', true)
            ->pluck('id')
            ->all();
    }

    public function executeAutoGenerate(?GenerateUtilityMeters $generator = null): void
    {
        $this->authorizeAction('create');

        $generator ??= app(GenerateUtilityMeters::class);
        $building = app(CurrentBuilding::class)->getOrFail();

        if (empty($this->selectedUtilityIds)) {
            $this->notifyError(__('utilities.no_utilities_selected'));

            return;
        }

        $targetFlatIds = $this->resolveScopedFlatIds();
        if (empty($targetFlatIds)) {
            $this->notifyError(__('utilities.no_flats_selected'));

            return;
        }

        $result = $generator->handle(
            building: $building,
            flatIds: $targetFlatIds,
            utilityIds: $this->selectedUtilityIds,
            utilityPrefixes: $this->utilityPrefixes,
            flatUtilityOverrides: $this->flatUtilityOverrides,
            persistOverrides: $this->persistOverrides,
        );

        $this->cancelAutoGenerate();

        $this->notify(__('utilities.meters_generated_summary', [
            'created' => $result->metersCreated,
            'skipped' => $result->metersSkipped,
            'excluded' => $result->flatsExcluded,
        ]));
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
            'floors' => Floor::where('building_id', $buildingId)
                ->orderBy('level')
                ->get(),
        ])->layout('components.layouts.app');
    }
}
