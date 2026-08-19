<?php

namespace App\Models;

use Database\Factories\UtilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A metered thing the building sells on to its units — electricity, gas, water.
 *
 * @property int $id
 * @property int $building_id
 * @property int $account_id
 * @property string $name
 * @property string|null $name_bn
 * @property string $unit_label
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['building_id', 'account_id', 'name', 'name_bn', 'unit_label', 'sort_order', 'is_active'])]
class Utility extends Model
{
    /** @use HasFactory<UtilityFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Building, $this> */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return HasMany<Meter, $this> */
    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }

    /** @return HasMany<UtilityTariff, $this> */
    public function tariffs(): HasMany
    {
        return $this->hasMany(UtilityTariff::class);
    }

    /**
     * The tariff in force on a date, or null if the utility had no rate set then.
     *
     * Only ever called when a reading is confirmed — from that point the tariff is
     * stamped on the reading, because a reading confirmed in July may not be billed
     * until September, by which time this would resolve to a different answer.
     */
    public function tariffOn(Carbon $date): ?UtilityTariff
    {
        return $this->tariffs()
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();
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
