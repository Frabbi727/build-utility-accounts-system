<?php

namespace App\Livewire\Utilities;

use App\Enums\ReadingStatus;
use App\Exceptions\ReadingNotConfirmableException;
use App\Livewire\Concerns\WithConfirmation;
use App\Livewire\Concerns\WithNotices;
use App\Models\Building;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Utility;
use App\Services\Billing\ConfirmReading;
use App\Services\Billing\MeterConsumption;
use App\Services\Billing\MeterReadingAnomalyDetector;
use App\Services\Billing\MeterReadingCsvService;
use App\Support\CurrentBuilding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A month's readings for every active meter, entered as one sheet.
 *
 * Supports bulk CSV export & import, real-time consumption spike anomaly indicators,
 * and meter dial photo evidence attachments.
 */
class ReadingSheet extends Component
{
    use WithConfirmation;
    use WithFileUploads;
    use WithNotices;

    public string $month = '';

    public ?int $utilityId = null;

    /**
     * Row state keyed by meter id.
     *
     * @var array<int, array{current: string, date: string, estimated: bool, note: string}>
     */
    public array $rows = [];

    /** @var UploadedFile|null */
    public $csvFile = null;

    public bool $showImportModal = false;

    /** @var list<string> */
    public array $importErrors = [];

    /** @var UploadedFile|null */
    public $readingPhoto = null;

    public ?int $photoMeterId = null;

    public ?string $viewingPhotoUrl = null;

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

    public function exportCsv(): StreamedResponse
    {
        $this->authorize('viewAny', MeterReading::class);

        $building = Building::findOrFail(app(CurrentBuilding::class)->id());
        $csv = app(MeterReadingCsvService::class)->export($building, $this->billingMonth(), $this->utilityId);
        $filename = "meter-readings-{$this->month}.csv";

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function openImportModal(): void
    {
        $this->csvFile = null;
        $this->importErrors = [];
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->csvFile = null;
        $this->importErrors = [];
    }

    public function importCsv(): void
    {
        $this->authorize('create', MeterReading::class);

        $this->validate([
            'csvFile' => ['required', 'file', 'max:2048'],
        ]);

        $building = Building::findOrFail(app(CurrentBuilding::class)->id());
        $content = (string) file_get_contents($this->csvFile->getRealPath());

        $result = app(MeterReadingCsvService::class)->import(
            $building,
            $this->billingMonth(),
            $content,
            Auth::id(),
        );

        $this->importErrors = $result['errors'];

        if ($result['saved'] > 0) {
            $this->notify(__('utilities.readings_imported', ['count' => $result['saved']]));
            if (empty($result['errors'])) {
                $this->closeImportModal();
            }
        }

        $this->loadRows();
    }

    public function openPhotoModal(int $meterId): void
    {
        $this->photoMeterId = $meterId;
        $this->readingPhoto = null;
    }

    public function closePhotoModal(): void
    {
        $this->photoMeterId = null;
        $this->readingPhoto = null;
    }

    public function savePhoto(): void
    {
        $this->authorize('create', MeterReading::class);

        $this->validate([
            'readingPhoto' => ['required', 'image', 'max:5120'],
        ]);

        if ($this->photoMeterId !== null) {
            $path = $this->readingPhoto->store('meter_readings', 'public');
            $reading = MeterReading::where('meter_id', $this->photoMeterId)
                ->whereDate('billing_month', $this->billingMonth())
                ->first();

            if ($reading !== null) {
                $reading->update(['image_path' => $path]);
            } else {
                // If row has not been saved yet, create a baseline reading record with photo
                $meter = Meter::findOrFail($this->photoMeterId);
                $row = $this->rows[$meter->id] ?? null;
                $current = $row !== null && trim((string) $row['current']) !== '' ? $row['current'] : $this->previousFor($meter);
                $previous = $this->previousFor($meter);
                $consumption = app(MeterConsumption::class)->between($meter, $previous, $current);

                MeterReading::create([
                    'meter_id' => $meter->id,
                    'billing_month' => $this->billingMonth(),
                    'reading_date' => $row['date'] ?? now()->toDateString(),
                    'previous_reading' => $previous,
                    'current_reading' => $current,
                    'consumption' => $consumption,
                    'is_estimated' => (bool) ($row['estimated'] ?? false),
                    'note' => $row['note'] ?? null,
                    'image_path' => $path,
                    'recorded_by' => Auth::id(),
                    'status' => ReadingStatus::Draft,
                ]);
            }

            $this->notify(__('utilities.photo_uploaded'));
            $this->closePhotoModal();
            $this->loadRows();
        }
    }

    public function viewPhoto(string $imagePath): void
    {
        $this->viewingPhotoUrl = Storage::disk('public')->url($imagePath);
    }

    public function closePhotoView(): void
    {
        $this->viewingPhotoUrl = null;
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
            $this->notify(__('utilities.readings_saved'));
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

        $this->notify(__('utilities.readings_confirmed', ['count' => 1]));
    }

    /**
     * @return list<string>
     */
    protected function confirmableActions(): array
    {
        return ['confirmAll', 'revert'];
    }

    /**
     * How many readings confirmAll would settle — the number the dialog puts in front of
     * the operator before they confirm a whole month in one click.
     */
    public function unconfirmedCount(): int
    {
        return $this->readingsForMonth()->reject(fn (MeterReading $r): bool => $r->isConfirmed())->count();
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
            $this->notify(__('utilities.readings_confirmed', ['count' => $confirmed]));
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
        $billingMonth = $this->billingMonth();
        $detector = app(MeterReadingAnomalyDetector::class);

        $previousMap = [];
        $anomalies = [];

        foreach ($meters as $meter) {
            $prev = $this->previousFor($meter);
            $previousMap[$meter->id] = $prev;

            $currentRow = $this->rows[$meter->id] ?? null;
            $currentVal = $currentRow !== null ? $currentRow['current'] : null;

            $anomalies[$meter->id] = $detector->detect($meter, $currentVal, $billingMonth, $prev);
        }

        return view('livewire.utilities.reading-sheet', [
            'meters' => $meters,
            'readings' => $readings,
            'previous' => $previousMap,
            'anomalies' => $anomalies,
            'utilities' => Utility::where('building_id', app(CurrentBuilding::class)->id())
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'pendingCount' => $readings->filter(fn (MeterReading $r): bool => $r->status === ReadingStatus::Draft)->count(),
        ])->layout('components.layouts.app');
    }
}
