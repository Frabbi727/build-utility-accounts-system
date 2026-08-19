<?php

namespace App\Livewire\Utilities;

use App\Enums\ReadingStatus;
use App\Exceptions\ReadingNotConfirmableException;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Utility;
use App\Services\Billing\ConfirmReading;
use App\Services\Billing\MeterConsumption;
use App\Support\CurrentBuilding;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

/**
 * A month's readings for every active meter, entered as one sheet.
 *
 * Deliberately not a CRUD modal per reading. Readings are taken in one pass around the
 * building, so the screen that matches the work is a grid: every meter listed with its
 * previous value already filled in, one number to type per row, then save and confirm
 * together.
 *
 * Confirming is what settles a reading: it stamps the tariff and makes the row billable.
 * Anything already on a bill is shown read-only, because a posted bill is never edited.
 */
class ReadingSheet extends Component
{
    public string $month = '';

    public ?int $utilityId = null;

    /**
     * Row state keyed by meter id.
     *
     * @var array<int, array{current: string, date: string, estimated: bool, note: string}>
     */
    public array $rows = [];

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->loadRows();
    }

    public function updatedMonth(): void
    {
        $this->loadRows();
    }

    public function updatedUtilityId(): void
    {
        $this->loadRows();
    }

    private function billingMonth(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Meter>
     */
    private function meters()
    {
        return Meter::query()
            ->where('building_id', app(CurrentBuilding::class)->id())
            ->where('is_active', true)
            ->when($this->utilityId, fn ($query) => $query->where('utility_id', $this->utilityId))
            ->with(['utility', 'flat'])
            ->orderBy('utility_id')
            ->orderBy('meter_no')
            ->get();
    }

    /**
     * @return Collection<int, MeterReading>
     */
    private function readingsForMonth()
    {
        return MeterReading::query()
            ->whereIn('meter_id', $this->meters()->modelKeys())
            ->whereDate('billing_month', $this->billingMonth())
            ->with('tariff')
            ->get()
            ->keyBy('meter_id');
    }

    /**
     * Seed each row from the existing reading if there is one, otherwise leave the
     * current value blank for the operator to fill in.
     */
    private function loadRows(): void
    {
        $existing = $this->readingsForMonth();
        $default = $this->billingMonth()->copy()->endOfMonth()->toDateString();

        // A plain array, not the Collection: Collection::offsetGet raises on a missing
        // key and `??` cannot rescue it, because the error comes from inside the method.
        $existing = $existing->all();

        $this->rows = $this->meters()->mapWithKeys(function (Meter $meter) use ($existing, $default): array {
            $reading = $existing[$meter->id] ?? null;

            return [
                $meter->id => [
                    'current' => $reading === null ? '' : (string) $reading->current_reading,
                    'date' => $reading === null ? $default : $reading->reading_date->toDateString(),
                    'estimated' => $reading !== null && $reading->is_estimated,
                    'note' => $reading === null ? '' : (string) $reading->note,
                ],
            ];
        })->all();
    }

    /**
     * What the previous reading is for a meter in this month: the last reading before
     * this one, or the dial the meter was installed at.
     */
    private function previousFor(Meter $meter): string
    {
        $earlier = $meter->readings()
            ->whereDate('billing_month', '<', $this->billingMonth())
            ->orderByDesc('billing_month')
            ->orderByDesc('id')
            ->first();

        return bcadd((string) ($earlier === null ? $meter->initial_reading : $earlier->current_reading), '0', 3);
    }

    public function save(): void
    {
        $this->authorize('create', MeterReading::class);

        $consumption = app(MeterConsumption::class);
        $billingMonth = $this->billingMonth();
        $saved = 0;

        DB::transaction(function () use ($consumption, $billingMonth, &$saved): void {
            foreach ($this->meters() as $meter) {
                $row = $this->rows[$meter->id] ?? null;

                if ($row === null || trim((string) $row['current']) === '') {
                    continue;
                }

                $existing = MeterReading::where('meter_id', $meter->id)
                    ->whereDate('billing_month', $billingMonth)
                    ->first();

                // A reading already on a bill is history and is left exactly as it is.
                if ($existing?->isApplied()) {
                    continue;
                }

                if ($consumption->isImplausible($meter, (string) $row['current'])) {
                    $this->addError("rows.{$meter->id}.current", __('utilities.implausible_reading', [
                        'reading' => $row['current'],
                        'digits' => $meter->digits,
                    ]));

                    continue;
                }

                $previous = $this->previousFor($meter);

                MeterReading::updateOrCreate(
                    ['meter_id' => $meter->id, 'billing_month' => $billingMonth],
                    [
                        'reading_date' => $row['date'],
                        'previous_reading' => $previous,
                        'current_reading' => $row['current'],
                        // Computed once, at entry: rollover and meter replacement both
                        // make this un-derivable from the two dial values later.
                        'consumption' => $consumption->between($meter, $previous, (string) $row['current']),
                        'is_estimated' => (bool) $row['estimated'],
                        'note' => $row['note'] === '' ? null : $row['note'],
                        'recorded_by' => Auth::id(),
                    ],
                );

                $saved++;
            }
        });

        if ($saved > 0) {
            session()->flash('status', __('utilities.readings_saved'));
        }

        $this->loadRows();
    }

    public function confirm(int $readingId): void
    {
        $reading = $this->findReading($readingId);
        $this->authorize('confirm', $reading);

        try {
            app(ConfirmReading::class)->handle($reading);
        } catch (ReadingNotConfirmableException) {
            $this->addError('month', __('utilities.no_tariff_for_reading'));

            return;
        }

        session()->flash('status', __('utilities.readings_confirmed', ['count' => 1]));
    }

    public function confirmAll(): void
    {
        $this->authorize('create', MeterReading::class);

        $service = app(ConfirmReading::class);
        $confirmed = 0;
        $failed = false;

        foreach ($this->readingsForMonth() as $reading) {
            if ($reading->isConfirmed()) {
                continue;
            }

            try {
                $service->handle($reading);
                $confirmed++;
            } catch (ReadingNotConfirmableException) {
                $failed = true;
            }
        }

        if ($failed) {
            $this->addError('month', __('utilities.no_tariff_for_reading'));
        }

        if ($confirmed > 0) {
            session()->flash('status', __('utilities.readings_confirmed', ['count' => $confirmed]));
        }
    }

    public function revert(int $readingId): void
    {
        $reading = $this->findReading($readingId);
        $this->authorize('update', $reading);

        try {
            app(ConfirmReading::class)->revert($reading);
        } catch (ReadingNotConfirmableException) {
            $this->addError('month', __('utilities.already_billed'));
        }
    }

    private function findReading(int $id): MeterReading
    {
        return MeterReading::query()
            ->whereIn('meter_id', Meter::where('building_id', app(CurrentBuilding::class)->id())->select('id'))
            ->with('meter.utility')
            ->findOrFail($id);
    }

    public function render(): View
    {
        $this->authorize('viewAny', MeterReading::class);

        $meters = $this->meters();
        $readings = $this->readingsForMonth();

        return view('livewire.utilities.reading-sheet', [
            'meters' => $meters,
            'readings' => $readings,
            'previous' => $meters->mapWithKeys(fn (Meter $meter): array => [
                $meter->id => $this->previousFor($meter),
            ]),
            'utilities' => Utility::where('building_id', app(CurrentBuilding::class)->id())
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'pendingCount' => $readings->filter(fn (MeterReading $r): bool => $r->status === ReadingStatus::Draft)->count(),
        ])->layout('components.layouts.app');
    }
}
