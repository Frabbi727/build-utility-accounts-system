<?php

namespace App\Services\Utilities;

use App\Models\Building;
use App\Models\Flat;
use App\Models\FlatUtilityConfig;
use App\Models\Meter;
use App\Models\Utility;
use App\Support\MeterGenerationPreviewData;
use App\Support\MeterGenerationResultData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateUtilityMeters
{
    /**
     * @param  list<int>  $flatIds
     * @param  list<int>  $utilityIds
     * @param  array<string, bool>  $flatUtilityOverrides
     */
    public function preview(
        Building $building,
        array $flatIds,
        array $utilityIds,
        array $flatUtilityOverrides = [],
    ): MeterGenerationPreviewData {
        $flats = Flat::where('building_id', $building->id)
            ->whereIn('id', $flatIds)
            ->orderBy('number')
            ->get();

        $utilities = Utility::where('building_id', $building->id)
            ->whereIn('id', $utilityIds)
            ->orderBy('sort_order')
            ->get();

        $existingMeters = Meter::where('building_id', $building->id)
            ->whereIn('flat_id', $flats->pluck('id'))
            ->whereIn('utility_id', $utilities->pluck('id'))
            ->get();

        $savedConfigs = FlatUtilityConfig::whereIn('flat_id', $flats->pluck('id'))
            ->whereIn('utility_id', $utilities->pluck('id'))
            ->get()
            ->keyBy(fn (FlatUtilityConfig $c): string => "{$c->flat_id}-{$c->utility_id}");

        $eligibleCount = 0;
        $skippedCount = 0;
        $excludedCount = 0;
        $skippedDetails = [];
        $inactiveWarnings = [];
        $utilityCounts = [];

        foreach ($utilities as $utility) {
            $utilityCounts[$utility->id] = 0;

            foreach ($flats as $flat) {
                $overrideKey = "{$flat->id}-{$utility->id}";
                $isEnabled = $flatUtilityOverrides[$overrideKey] ?? ($savedConfigs->get($overrideKey)->is_enabled ?? true);

                if (! $isEnabled) {
                    $excludedCount++;
                    $skippedDetails[] = [
                        'flat_id' => $flat->id,
                        'flat_number' => $flat->number,
                        'utility_id' => $utility->id,
                        'utility_name' => $utility->displayName(),
                        'reason' => 'Excluded by configuration',
                    ];

                    continue;
                }

                $existing = $existingMeters->first(
                    fn (Meter $m): bool => $m->flat_id === $flat->id && $m->utility_id === $utility->id
                );

                if ($existing !== null) {
                    $skippedCount++;
                    if (! $existing->is_active) {
                        $inactiveWarnings[] = [
                            'flat_id' => $flat->id,
                            'flat_number' => $flat->number,
                            'utility_id' => $utility->id,
                            'utility_name' => $utility->displayName(),
                            'meter_no' => $existing->meter_no,
                        ];
                    }
                    $skippedDetails[] = [
                        'flat_id' => $flat->id,
                        'flat_number' => $flat->number,
                        'utility_id' => $utility->id,
                        'utility_name' => $utility->displayName(),
                        'reason' => $existing->is_active ? 'Active meter already exists' : 'Inactive meter already exists',
                    ];

                    continue;
                }

                $eligibleCount++;
                $utilityCounts[$utility->id]++;
            }
        }

        return new MeterGenerationPreviewData(
            totalFlatsCount: $flats->count(),
            eligibleMetersCount: $eligibleCount,
            skippedMetersCount: $skippedCount,
            excludedFlatsCount: $excludedCount,
            skippedDetails: $skippedDetails,
            inactiveWarnings: $inactiveWarnings,
            utilityCounts: $utilityCounts,
        );
    }

    /**
     * @param  list<int>  $flatIds
     * @param  list<int>  $utilityIds
     * @param  array<int, string>  $utilityPrefixes
     * @param  array<string, bool>  $flatUtilityOverrides
     */
    public function handle(
        Building $building,
        array $flatIds,
        array $utilityIds,
        array $utilityPrefixes = [],
        array $flatUtilityOverrides = [],
        bool $persistOverrides = true,
    ): MeterGenerationResultData {
        return DB::transaction(function () use (
            $building,
            $flatIds,
            $utilityIds,
            $utilityPrefixes,
            $flatUtilityOverrides,
            $persistOverrides,
        ): MeterGenerationResultData {
            $flats = Flat::where('building_id', $building->id)
                ->whereIn('id', $flatIds)
                ->orderBy('number')
                ->get();

            $utilities = Utility::where('building_id', $building->id)
                ->whereIn('id', $utilityIds)
                ->orderBy('sort_order')
                ->get();

            $existingMeters = Meter::where('building_id', $building->id)
                ->whereIn('flat_id', $flats->pluck('id'))
                ->whereIn('utility_id', $utilities->pluck('id'))
                ->get();

            $savedConfigs = FlatUtilityConfig::whereIn('flat_id', $flats->pluck('id'))
                ->whereIn('utility_id', $utilities->pluck('id'))
                ->get()
                ->keyBy(fn (FlatUtilityConfig $c): string => "{$c->flat_id}-{$c->utility_id}");

            $createdCount = 0;
            $skippedCount = 0;
            $excludedCount = 0;
            $errors = [];

            foreach ($utilities as $utility) {
                $prefix = trim($utilityPrefixes[$utility->id] ?? strtoupper(Str::limit($utility->name, 4, '')));
                if ($prefix === '') {
                    $prefix = strtoupper(Str::limit($utility->name, 4, ''));
                }

                foreach ($flats as $flat) {
                    $overrideKey = "{$flat->id}-{$utility->id}";
                    $isEnabled = $flatUtilityOverrides[$overrideKey] ?? ($savedConfigs->get($overrideKey)->is_enabled ?? true);

                    if ($persistOverrides && isset($flatUtilityOverrides[$overrideKey])) {
                        FlatUtilityConfig::updateOrCreate(
                            ['flat_id' => $flat->id, 'utility_id' => $utility->id],
                            ['is_enabled' => $isEnabled],
                        );
                    }

                    if (! $isEnabled) {
                        $excludedCount++;

                        continue;
                    }

                    $existing = $existingMeters->first(
                        fn (Meter $m): bool => $m->flat_id === $flat->id && $m->utility_id === $utility->id
                    );

                    if ($existing !== null) {
                        $skippedCount++;

                        continue;
                    }

                    $baseMeterNo = "{$prefix}-{$flat->number}";
                    $meterNo = $baseMeterNo;
                    $attempts = 0;
                    while (Meter::where('utility_id', $utility->id)->where('meter_no', $meterNo)->exists() && $attempts < 5) {
                        $meterNo = "{$baseMeterNo}-".Str::upper(Str::random(3));
                        $attempts++;
                    }

                    Meter::create([
                        'building_id' => $building->id,
                        'utility_id' => $utility->id,
                        'flat_id' => $flat->id,
                        'meter_no' => $meterNo,
                        'digits' => 6,
                        'initial_reading' => '0',
                        'installed_on' => now()->toDateString(),
                        'is_active' => true,
                    ]);

                    $createdCount++;
                }
            }

            return new MeterGenerationResultData(
                flatsConsidered: $flats->count(),
                metersCreated: $createdCount,
                metersSkipped: $skippedCount,
                flatsExcluded: $excludedCount,
                errors: $errors,
            );
        });
    }
}
