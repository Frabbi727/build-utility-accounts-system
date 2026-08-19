<?php

namespace App\Services\Masters;

use App\Models\Building;
use App\Models\Flat;
use App\Models\Floor;
use Illuminate\Support\Facades\DB;

/**
 * Creates a whole building of flats in one go.
 *
 * Onboarding a 40-flat building one form at a time is the slowest part of setting
 * this system up, so the operator names a level range and the flat letters on each
 * level and gets the lot. Existing flat numbers are left alone, which makes a repeat
 * run safe — it only fills in what is missing.
 */
class GenerateFlats
{
    /**
     * @param  list<string>  $suffixes  flat letters on each level, e.g. ['A', 'B']
     * @return int the number of flats actually created
     */
    public function handle(
        Building $building,
        int $fromLevel,
        int $toLevel,
        array $suffixes,
        string $defaultSizeSqft = '0',
        bool $createMissingFloors = true,
    ): int {
        $existing = $building->flats()->pluck('number')->flip();

        return DB::transaction(function () use ($building, $fromLevel, $toLevel, $suffixes, $defaultSizeSqft, $createMissingFloors, $existing): int {
            $created = 0;

            foreach (range($fromLevel, $toLevel) as $level) {
                $floor = $createMissingFloors
                    ? Floor::firstOrCreate(
                        ['building_id' => $building->id, 'level' => $level],
                        ['name' => __('masters.level').' '.$level],
                    )
                    : $building->floors()->where('level', $level)->first();

                foreach ($suffixes as $suffix) {
                    $number = $level.$suffix;

                    if ($existing->has($number)) {
                        continue;
                    }

                    Flat::create([
                        'building_id' => $building->id,
                        'floor_id' => $floor?->id,
                        'owner_id' => null,
                        'number' => $number,
                        'size_sqft' => $defaultSizeSqft,
                        'is_active' => true,
                    ]);

                    $created++;
                }
            }

            return $created;
        });
    }

    /**
     * Parse the comma-separated letters an operator typed into a clean list.
     *
     * @return list<string>
     */
    public function parseSuffixes(string $input): array
    {
        return collect(explode(',', $input))
            ->map(fn (string $part): string => trim($part))
            ->filter(fn (string $part): bool => $part !== '')
            ->unique()
            ->values()
            ->all();
    }
}
