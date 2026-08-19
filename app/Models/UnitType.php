<?php

namespace App\Models;

use Database\Factories\UnitTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What kind of unit a flat is — Shop, Office, Flat, Garage.
 *
 * @property int $id
 * @property int $building_id
 * @property string $name
 * @property string|null $name_bn
 * @property int $sort_order
 */
#[Fillable(['building_id', 'name', 'name_bn', 'sort_order'])]
class UnitType extends Model
{
    /** @use HasFactory<UnitTypeFactory> */
    use HasFactory;

    public $timestamps = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /** @return BelongsTo<Building, $this> */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /** @return HasMany<Flat, $this> */
    public function flats(): HasMany
    {
        return $this->hasMany(Flat::class);
    }

    /** @return BelongsToMany<ChargeHead, $this> */
    public function chargeHeads(): BelongsToMany
    {
        return $this->belongsToMany(ChargeHead::class);
    }

    /**
     * The display name in the active locale.
     */
    public function displayName(): string
    {
        return app()->getLocale() === 'bn'
            ? ($this->name_bn ?? $this->name)
            : $this->name;
    }
}
