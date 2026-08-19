<?php

namespace App\Models;

use Database\Factories\MeterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A physical meter. `flat_id` null means it measures the building, not a unit.
 *
 * @property int $id
 * @property int $building_id
 * @property int $utility_id
 * @property int|null $flat_id
 * @property string $meter_no
 * @property int $digits
 * @property string $initial_reading
 * @property Carbon|null $installed_on
 * @property bool $is_active
 */
#[Fillable(['building_id', 'utility_id', 'flat_id', 'meter_no', 'digits', 'initial_reading', 'installed_on', 'is_active'])]
class Meter extends Model
{
    /** @use HasFactory<MeterFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'digits' => 'integer',
            // A dial reading is a quantity, not money.
            'initial_reading' => 'decimal:3',
            'installed_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Building, $this> */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /** @return BelongsTo<Utility, $this> */
    public function utility(): BelongsTo
    {
        return $this->belongsTo(Utility::class);
    }

    /** @return BelongsTo<Flat, $this> */
    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    /** @return HasMany<MeterReading, $this> */
    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    /**
     * A common meter measures shared or whole-building load and is billed to nobody.
     * Its consumption is a denominator and a system-loss figure, not a charge.
     */
    public function isCommon(): bool
    {
        return $this->flat_id === null;
    }

    /**
     * Where the next reading counts from: the last reading taken, or the dial the meter
     * was installed at if none has been.
     */
    public function latestReadingValue(): string
    {
        $latest = $this->readings()
            ->orderByDesc('billing_month')
            ->orderByDesc('id')
            ->first();

        return bcadd((string) ($latest === null ? $this->initial_reading : $latest->current_reading), '0', 3);
    }

    public function label(): string
    {
        return $this->meter_no;
    }
}
