<?php

namespace App\Models;

use App\Enums\LateFeeType;
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
 * @property int $due_day_of_month
 * @property LateFeeType $late_fee_type
 * @property string $late_fee_amount
 * @property int $late_fee_grace_days
 */
#[Fillable(['name', 'name_bn', 'address', 'due_day_of_month', 'late_fee_type', 'late_fee_amount', 'late_fee_grace_days'])]
class Building extends Model
{
    /** @use HasFactory<BuildingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_day_of_month' => 'integer',
            'late_fee_type' => LateFeeType::class,
            'late_fee_amount' => 'decimal:2',
            'late_fee_grace_days' => 'integer',
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

    /** @return HasMany<Flat, $this> */
    public function activeFlats(): HasMany
    {
        return $this->flats()->where('is_active', true);
    }

    /**
     * What this building bills every month, one head per bill line.
     *
     * @return HasMany<ChargeHead, $this>
     */
    public function chargeHeads(): HasMany
    {
        return $this->hasMany(ChargeHead::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<Staff, $this> */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
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
