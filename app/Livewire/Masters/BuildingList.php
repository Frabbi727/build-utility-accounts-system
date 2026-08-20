<?php

namespace App\Livewire\Masters;

use App\Enums\LateFeeType;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Building;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class BuildingList extends Component
{
    use WithCrudModal;

    public string $name = '';

    public ?string $nameBn = null;

    public ?string $address = null;

    public int $dueDayOfMonth = 10;

    public string $lateFeeType = 'none';

    public string $lateFeeAmount = '0.00';

    public int $lateFeeGraceDays = 0;

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
            'lateFeeType' => ['required', Rule::enum(LateFeeType::class)],
            'lateFeeAmount' => ['required', 'numeric', 'min:0'],
            'lateFeeGraceDays' => ['required', 'integer', 'min:0', 'max:60'],
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
        $this->lateFeeType = $record?->late_fee_type->value ?? LateFeeType::None->value;
        $this->lateFeeAmount = $record === null ? '0.00' : (string) $record->late_fee_amount;
        $this->lateFeeGraceDays = $record->late_fee_grace_days ?? 0;
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
            'late_fee_type' => LateFeeType::from($this->lateFeeType),
            'late_fee_amount' => $this->lateFeeAmount,
            'late_fee_grace_days' => $this->lateFeeGraceDays,
        ])->save();

        // A first building should become the one the operator is working in.
        if (app(CurrentBuilding::class)->id() === null) {
            app(CurrentBuilding::class)->set($building->id);
        }

        return $building;
    }

    public function render(): View
    {
        $this->authorize('viewAny', Building::class);

        return view('livewire.masters.building-list', [
            'buildings' => Building::withCount(['flats', 'chargeHeads'])->orderBy('name')->get(),
            'lateFeeTypes' => LateFeeType::cases(),
        ])->layout('components.layouts.app');
    }
}
