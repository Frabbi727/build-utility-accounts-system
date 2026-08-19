<?php

namespace App\Models;

use Database\Factories\FlatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $building_id
 * @property int|null $floor_id
 * @property int|null $owner_id
 * @property int|null $unit_type_id
 * @property string $number
 * @property string $size_sqft
 * @property bool $is_active
 */
#[Fillable(['building_id', 'floor_id', 'owner_id', 'unit_type_id', 'number', 'size_sqft', 'is_active'])]
class Flat extends Model
{
    /** @use HasFactory<FlatFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_sqft' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Building, $this> */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /** @return BelongsTo<Floor, $this> */
    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    /** @return BelongsTo<Owner, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    /**
     * What kind of unit this is. Null when the building never needed the distinction.
     *
     * @return BelongsTo<UnitType, $this>
     */
    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }

    /**
     * Charge heads this flat pays differently, or not at all.
     *
     * @return HasMany<FlatChargeOverride, $this>
     */
    public function chargeOverrides(): HasMany
    {
        return $this->hasMany(FlatChargeOverride::class);
    }

    /** @return HasMany<Tenant, $this> */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /** @return HasMany<ServiceChargeBill, $this> */
    public function serviceChargeBills(): HasMany
    {
        return $this->hasMany(ServiceChargeBill::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<JournalLine, $this> */
    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
