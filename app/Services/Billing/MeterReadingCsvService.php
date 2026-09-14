<?php

namespace App\Services\Billing;

use App\Enums\ReadingStatus;
use App\Models\Building;
use App\Models\Meter;
use App\Models\MeterReading;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service for exporting and importing monthly meter reading sheets via CSV.
 */
class MeterReadingCsvService
{
    public function __construct(
        private readonly MeterConsumption $consumption,
    ) {}

    public function export(Building $building, Carbon $month, ?int $utilityId = null): string
    {
        $billingMonth = $month->copy()->startOfMonth();
        $defaultDate = $billingMonth->copy()->endOfMonth()->toDateString();

        $meters = Meter::query()
            ->where('building_id', $building->id)
            ->where('is_active', true)
            ->when($utilityId !== null, fn ($query) => $query->where('utility_id', $utilityId))
            ->with(['utility', 'flat'])
            ->orderBy('utility_id')
            ->orderBy('meter_no')
            ->get();

        $readings = MeterReading::query()
            ->whereIn('meter_id', $meters->modelKeys())
            ->whereDate('billing_month', $billingMonth)
            ->get()
            ->keyBy('meter_id');

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            throw new \RuntimeException('Failed to open temporary memory stream.');
        }

        // CSV Header
        fputcsv($output, [
            'meter_id',
            'flat_number',
            'utility',
            'meter_no',
            'previous_reading',
            'current_reading',
            'reading_date',
            'is_estimated',
            'note',
        ]);

        foreach ($meters as $meter) {
            $reading = $readings->get($meter->id);
            $previous = $this->previousFor($meter, $billingMonth);

            fputcsv($output, [
                $meter->id,
                $meter->flat !== null ? $meter->flat->number : 'Common',
                $meter->utility->name,
                $meter->meter_no,
                $previous,
                $reading !== null ? (string) $reading->current_reading : '',
                $reading !== null ? $reading->reading_date->toDateString() : $defaultDate,
                $reading !== null && $reading->is_estimated ? 'yes' : 'no',
                $reading !== null ? (string) $reading->note : '',
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv !== false ? $csv : '';
    }

    /**
     * @return array{saved: int, skipped: int, errors: list<string>}
     */
    public function import(Building $building, Carbon $month, string $csvContent, ?int $userId = null): array
    {
        $billingMonth = $month->copy()->startOfMonth();
        $lines = preg_split("/\r\n|\n|\r/", trim($csvContent));

        if ($lines === false || empty($lines)) {
            return ['saved' => 0, 'skipped' => 0, 'errors' => ['CSV file is empty.']];
        }

        $header = str_getcsv(array_shift($lines));
        $headerMap = array_flip(array_map('trim', $header));

        if (! isset($headerMap['meter_id']) || ! isset($headerMap['current_reading'])) {
            return ['saved' => 0, 'skipped' => 0, 'errors' => ['Invalid CSV headers. "meter_id" and "current_reading" are required.']];
        }

        $saved = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($building, $billingMonth, $lines, $headerMap, $userId, &$saved, &$skipped, &$errors): void {
            foreach ($lines as $index => $line) {
                if (trim($line) === '') {
                    continue;
                }

                $row = str_getcsv($line);
                $rowNumber = $index + 2; // account for header line

                $meterId = (int) ($row[$headerMap['meter_id']] ?? 0);
                $currentRaw = trim((string) ($row[$headerMap['current_reading']] ?? ''));

                if ($currentRaw === '') {
                    $skipped++;

                    continue;
                }

                if (! is_numeric($currentRaw)) {
                    $errors[] = "Row {$rowNumber}: Current reading must be numeric.";
                    $skipped++;

                    continue;
                }

                $meter = Meter::where('id', $meterId)
                    ->where('building_id', $building->id)
                    ->where('is_active', true)
                    ->first();

                if ($meter === null) {
                    $errors[] = "Row {$rowNumber}: Meter ID #{$meterId} not found in this building.";
                    $skipped++;

                    continue;
                }

                $existing = MeterReading::where('meter_id', $meter->id)
                    ->whereDate('billing_month', $billingMonth)
                    ->first();

                if ($existing?->isApplied()) {
                    $errors[] = "Row {$rowNumber}: Meter #{$meter->meter_no} reading is already applied to a bill and cannot be modified.";
                    $skipped++;

                    continue;
                }

                if ($this->consumption->isImplausible($meter, $currentRaw)) {
                    $errors[] = "Row {$rowNumber}: Reading {$currentRaw} exceeds {$meter->digits} digits limit for meter #{$meter->meter_no}.";
                    $skipped++;

                    continue;
                }

                $previous = $this->previousFor($meter, $billingMonth);
                $current = bcadd($currentRaw, '0', 3);
                $readingDate = isset($headerMap['reading_date']) && trim((string) ($row[$headerMap['reading_date']] ?? '')) !== ''
                    ? Carbon::parse(trim((string) $row[$headerMap['reading_date']]))->toDateString()
                    : $billingMonth->copy()->endOfMonth()->toDateString();

                $isEstimated = isset($headerMap['is_estimated'])
                    && in_array(strtolower(trim((string) ($row[$headerMap['is_estimated']] ?? ''))), ['yes', '1', 'true'], true);

                $note = isset($headerMap['note']) && trim((string) ($row[$headerMap['note']] ?? '')) !== ''
                    ? trim((string) $row[$headerMap['note']])
                    : null;

                MeterReading::updateOrCreate(
                    ['meter_id' => $meter->id, 'billing_month' => $billingMonth],
                    [
                        'reading_date' => $readingDate,
                        'previous_reading' => $previous,
                        'current_reading' => $current,
                        'consumption' => $this->consumption->between($meter, $previous, $current),
                        'is_estimated' => $isEstimated,
                        'note' => $note,
                        'recorded_by' => $userId,
                        'status' => ReadingStatus::Draft,
                    ],
                );

                $saved++;
            }
        });

        return [
            'saved' => $saved,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function previousFor(Meter $meter, Carbon $billingMonth): string
    {
        $earlier = $meter->readings()
            ->whereDate('billing_month', '<', $billingMonth)
            ->orderByDesc('billing_month')
            ->orderByDesc('id')
            ->first();

        return bcadd((string) ($earlier === null ? $meter->initial_reading : $earlier->current_reading), '0', 3);
    }
}
