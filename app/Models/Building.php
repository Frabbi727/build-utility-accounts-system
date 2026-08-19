<?php

namespace App\Models;

use Database\Factories\BuildingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $name_bn
 * @property string|null $address
 * @property string $service_charge_mode
 * @property string $service_charge_rate
 * @property int $due_day_of_month
 */
#[Fillable(['name', 'name_bn', 'address', 'service_charge_mode', 'service_charge_rate', 'due_day_of_month'])]
class Building extends Model
{
    /** @use HasFactory<BuildingFactory> */
    use HasFactory;

    public const MODE_FLAT_RATE = 'flat_rate';

    public const MODE_PER_SQFT = 'per_sqft';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_charge_rate' => 'decimal:2',
            'due_day_of_month' => 'integer',
        ];
    }

    /** @return HasMany<Floor, $this> */
    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class);
    }

    /** @return HasMany<Flat, $this> */
    public function flats(): HasMany
    {
        return $this->hasMany(Flat::class);
    }

    /**
     * The monthly service charge for a flat under this building's charging model.
     */
    public function serviceChargeFor(Flat $flat): string
    {
        if ($this->service_charge_mode === self::MODE_PER_SQFT) {
            return bcmul((string) $this->service_charge_rate, (string) $flat->size_sqft, 2);
        }

        return bcadd((string) $this->service_charge_rate, '0', 2);
    }
}
