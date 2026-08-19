<?php

namespace App\Support;

use App\Models\Building;

/**
 * The building the operator is currently working in.
 *
 * Operational screens (flats, floors, charge heads, staff, bill generation) are
 * scoped by this. Financial reports are not: journal lines carry only a flat
 * dimension, so expenses and vendor bills have no building to filter on.
 */
class CurrentBuilding
{
    private const SESSION_KEY = 'current_building_id';

    private ?Building $resolved = null;

    /**
     * The active building, or null when none exists yet (a fresh install).
     */
    public function get(): ?Building
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $id = session(self::SESSION_KEY);

        $building = $id !== null ? Building::find($id) : null;

        return $this->resolved = $building ?? Building::orderBy('name')->first();
    }

    public function id(): ?int
    {
        return $this->get()?->id;
    }

    /**
     * The active building, failing loudly when a screen needs one and none exists.
     */
    public function getOrFail(): Building
    {
        $building = $this->get();

        abort_if($building === null, 404, 'No building has been created yet.');

        return $building;
    }

    public function set(int $buildingId): void
    {
        session()->put(self::SESSION_KEY, $buildingId);

        $this->resolved = null;
    }

    public function exists(): bool
    {
        return $this->get() !== null;
    }
}
