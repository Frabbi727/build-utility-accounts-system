<?php

namespace App\Livewire;

use App\Enums\DistributionStatus;
use App\Enums\ReadingStatus;
use App\Livewire\Concerns\PostsToLedger;
use App\Livewire\Concerns\WithConfirmation;
use App\Livewire\Concerns\WithNotices;
use App\Models\Building;
use App\Models\CostDistribution;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\ServiceChargeBill;
use App\Services\Billing\GenerateMonthlyBills;
use App\Support\CurrentBuilding;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Generating a month's bills is a one-way door: `service_charge_bills` is unique on
 * (flat_id, billing_month) and generation skips flats already billed, so anything left
 * unconfirmed or unapproved will not join this month's bill — it rides the next one.
 *
 * That is recoverable but confusing, so the confirmation dialog states exactly what is
 * about to be left behind before the operator commits.
 */
class GenerateBills extends Component
{
    use PostsToLedger;
    use WithConfirmation;
    use WithNotices;

    public ?int $buildingId = null;

    public string $month = '';

    public function mount(): void
    {
        $this->buildingId = app(CurrentBuilding::class)->id();
        $this->month = now()->format('Y-m');
    }

    /**
     * @return list<string>
     */
    protected function confirmableActions(): array
    {
        return ['generate'];
    }

    /**
     * Validate before asking: a dialog summarising an invalid month helps nobody.
     */
    public function askGenerate(): void
    {
        $this->authorize('create', ServiceChargeBill::class);

        $this->validate($this->rules());
        $this->clearNotice();

        $this->askConfirm('generate');
    }

    /**
     * Resolved from the container rather than method-injected: the confirmation dialog
     * calls this through runConfirmed(), which is a plain PHP call with no injection.
     */
    public function generate(): void
    {
        $this->authorize('create', ServiceChargeBill::class);

        $this->validate($this->rules());

        $building = Building::findOrFail($this->buildingId);
        $month = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();

        $bills = $this->postGuarded(
            fn () => app(GenerateMonthlyBills::class)->handle($building, $month),
            'month',
        );

        if ($bills === null) {
            return;
        }

        $this->notify($bills->isEmpty()
            ? __('billing.nothing_to_generate')
            : __('billing.bills_generated', ['count' => $bills->count()]));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'buildingId' => ['required', 'integer', 'exists:buildings,id'],
            'month' => ['required', 'date_format:Y-m'],
        ];
    }

    /**
     * What will not make it onto this month's bills if it is generated right now.
     *
     * @return array{readings: int, distributions: int}
     */
    public function pendingWork(): array
    {
        if ($this->buildingId === null || ! $this->isValidMonth()) {
            return ['readings' => 0, 'distributions' => 0];
        }

        $month = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();

        return [
            'readings' => MeterReading::query()
                ->whereIn('meter_id', Meter::where('building_id', $this->buildingId)->select('id'))
                ->whereDate('billing_month', '<=', $month)
                ->where('status', ReadingStatus::Draft)
                ->count(),
            'distributions' => CostDistribution::query()
                ->where('building_id', $this->buildingId)
                ->whereDate('billing_month', '<=', $month)
                ->where('status', DistributionStatus::Draft)
                ->count(),
        ];
    }

    /**
     * How many flats this run would actually bill — those active and not already billed.
     */
    public function flatsToBill(): int
    {
        if ($this->buildingId === null || ! $this->isValidMonth()) {
            return 0;
        }

        $building = Building::find($this->buildingId);

        if ($building === null) {
            return 0;
        }

        $month = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();

        $alreadyBilled = ServiceChargeBill::whereIn('flat_id', $building->flats()->select('id'))
            ->whereDate('billing_month', $month)
            ->pluck('flat_id');

        return $building->activeFlats()->whereNotIn('flats.id', $alreadyBilled)->count();
    }

    private function isValidMonth(): bool
    {
        return preg_match('/^\d{4}-\d{2}$/', $this->month) === 1;
    }

    public function render(): View
    {
        return view('livewire.generate-bills', [
            'buildings' => Building::orderBy('name')->get(),
            'pending' => $this->pendingWork(),
        ])->layout('components.layouts.app');
    }
}
