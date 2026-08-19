<?php

namespace App\Livewire;

use App\Models\Building;
use App\Models\Flat;
use App\Services\Billing\GenerateMonthlyBills;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

class GenerateBills extends Component
{
    public ?int $buildingId = null;

    public string $month = '';

    public function mount(): void
    {
        $this->buildingId = Building::query()->value('id');
        $this->month = now()->format('Y-m');
    }

    public function generate(GenerateMonthlyBills $generator): void
    {
        $this->authorize('manage', Flat::class);

        $this->validate([
            'buildingId' => ['required', 'integer', 'exists:buildings,id'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $building = Building::findOrFail($this->buildingId);
        $bills = $generator->handle($building, Carbon::createFromFormat('Y-m', $this->month)->startOfMonth());

        session()->flash('status', $bills->isEmpty()
            ? __('billing.nothing_to_generate')
            : __('billing.bills_generated', ['count' => $bills->count()]));
    }

    public function render(): View
    {
        return view('livewire.generate-bills', [
            'buildings' => Building::orderBy('name')->get(),
        ])->layout('components.layouts.app');
    }
}
