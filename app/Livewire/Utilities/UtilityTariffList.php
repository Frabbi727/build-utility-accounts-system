<?php

namespace App\Livewire\Utilities;

use App\Livewire\Concerns\WithCrudModal;
use App\Models\Utility;
use App\Models\UtilityTariff;
use App\Services\Billing\SlabValidator;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Rate cards per utility, versioned by their start date.
 *
 * The slab repeater is why this screen is not a plain WithCrudModal form over one record:
 * contiguity is a property of the whole set of bands, not of any one of them, so the
 * rows are edited together and validated together in persist().
 *
 * A tariff that has already priced a confirmed reading cannot be edited — the policy
 * refuses it, because changing the rate would change what an issued bill computes to.
 * The answer is a new tariff, which automatically closes the previous one off.
 */
class UtilityTariffList extends Component
{
    use WithCrudModal;

    public ?int $utilityId = null;

    public string $effectiveFrom = '';

    public string $fixedCharge = '0';

    /**
     * The bands being edited, as raw form rows.
     *
     * @var list<array{from_unit: string, to_unit: string|null, rate_per_unit: string}>
     */
    public array $slabs = [];

    /** Table filter, not part of the form. */
    public ?int $filterUtilityId = null;

    /**
     * @return array<string, mixed>
     */
    protected function formRules(): array
    {
        return [
            'utilityId' => [
                'required', 'integer',
                Rule::exists('utilities', 'id')->where('building_id', app(CurrentBuilding::class)->id()),
            ],
            'effectiveFrom' => [
                'required', 'date',
                Rule::unique('utility_tariffs', 'effective_from')
                    ->where('utility_id', $this->utilityId)
                    ->ignore($this->editingId),
            ],
            'fixedCharge' => ['required', 'numeric', 'min:0'],
            'slabs' => ['required', 'array', 'min:1'],
            'slabs.*.from_unit' => ['required', 'numeric', 'min:0'],
            'slabs.*.to_unit' => ['nullable', 'numeric', 'gt:0'],
            'slabs.*.rate_per_unit' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @param  UtilityTariff|null  $record
     */
    protected function fillForm(?Model $record): void
    {
        $this->utilityId = $record === null ? $this->filterUtilityId : $record->utility_id;
        $this->effectiveFrom = $record === null
            ? now()->startOfMonth()->toDateString()
            : $record->effective_from->toDateString();
        $this->fixedCharge = $record === null ? '0' : (string) $record->fixed_charge;

        $this->slabs = $record === null
            ? [['from_unit' => '0', 'to_unit' => null, 'rate_per_unit' => '']]
            : $record->slabs->map(fn ($slab): array => [
                'from_unit' => (string) $slab->from_unit,
                'to_unit' => $slab->to_unit === null ? null : (string) $slab->to_unit,
                'rate_per_unit' => (string) $slab->rate_per_unit,
            ])->all();
    }

    protected function findRecord(int $id): UtilityTariff
    {
        return UtilityTariff::query()
            ->whereIn('utility_id', Utility::where('building_id', app(CurrentBuilding::class)->id())->select('id'))
            ->with('slabs')
            ->findOrFail($id);
    }

    protected function authorizeAction(string $ability, ?Model $record = null): void
    {
        $this->authorize($ability, $record ?? UtilityTariff::class);
    }

    public function addSlab(): void
    {
        $last = end($this->slabs);

        // A new band starts where the previous one ended, which is the only placement
        // that can satisfy the contiguity check.
        $this->slabs[] = [
            'from_unit' => $last === false ? '0' : ($last['to_unit'] ?? ''),
            'to_unit' => null,
            'rate_per_unit' => '',
        ];
    }

    public function removeSlab(int $index): void
    {
        unset($this->slabs[$index]);

        $this->slabs = array_values($this->slabs);
    }

    protected function persist(): UtilityTariff
    {
        // Contiguity spans every row, so it cannot be a per-field rule.
        $error = app(SlabValidator::class)->errorFor(array_map(
            fn (array $slab): array => [
                'from_unit' => $slab['from_unit'],
                'to_unit' => $slab['to_unit'] === '' ? null : $slab['to_unit'],
            ],
            $this->slabs,
        ));

        if ($error !== null) {
            throw ValidationException::withMessages(['slabs' => $error]);
        }

        return DB::transaction(function (): UtilityTariff {
            $tariff = $this->editingId === null ? new UtilityTariff : $this->findRecord($this->editingId);

            $tariff->fill([
                'utility_id' => $this->utilityId,
                'effective_from' => $this->effectiveFrom,
                'fixed_charge' => $this->fixedCharge,
            ])->save();

            // The bands are replaced wholesale: they are only ever edited as a set, and
            // an in-use tariff never reaches here because the policy refuses the update.
            $tariff->slabs()->delete();
            $tariff->slabs()->createMany(array_map(
                fn (array $slab): array => [
                    'from_unit' => $slab['from_unit'],
                    'to_unit' => $slab['to_unit'] === '' ? null : $slab['to_unit'],
                    'rate_per_unit' => $slab['rate_per_unit'],
                ],
                $this->slabs,
            ));

            $this->closePreviousTariff($tariff);

            session()->flash('status', __('masters.saved'));

            return $tariff;
        });
    }

    /**
     * A new rate card ends the one before it the day before it starts, so the two never
     * both claim a date and `Utility::tariffOn()` stays unambiguous.
     */
    private function closePreviousTariff(UtilityTariff $tariff): void
    {
        UtilityTariff::where('utility_id', $tariff->utility_id)
            ->where('id', '!=', $tariff->id)
            ->whereDate('effective_from', '<', $tariff->effective_from)
            ->where(function ($query) use ($tariff): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $tariff->effective_from);
            })
            ->update(['effective_to' => $tariff->effective_from->copy()->subDay()]);
    }

    public function render(): View
    {
        $this->authorize('viewAny', UtilityTariff::class);

        $buildingId = app(CurrentBuilding::class)->id();
        $utilityIds = Utility::where('building_id', $buildingId)->select('id');

        return view('livewire.utilities.utility-tariff-list', [
            'tariffs' => UtilityTariff::query()
                ->with(['utility', 'slabs'])
                ->withCount('readings')
                ->whereIn('utility_id', $utilityIds)
                ->when($this->filterUtilityId, fn ($query) => $query->where('utility_id', $this->filterUtilityId))
                ->orderBy('utility_id')
                ->orderByDesc('effective_from')
                ->get(),
            'utilities' => Utility::where('building_id', $buildingId)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ])->layout('components.layouts.app');
    }
}
