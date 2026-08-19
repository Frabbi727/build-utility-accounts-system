<?php

namespace App\Models;

use App\Enums\ChargeBasis;
use Database\Factories\ChargeHeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One billable component of a building's monthly service charge — guard salary,
 * lift maintenance, water, cleaning, and so on. A generated bill carries one
 * bill_item per active head, credited to that head's own income account.
 *
 * @property int $id
 * @property int $building_id
 * @property int $account_id
 * @property string $name
 * @property string|null $name_bn
 * @property ChargeBasis $basis
 * @property string $amount
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['building_id', 'account_id', 'name', 'name_bn', 'basis', 'amount', 'sort_order', 'is_active'])]
class ChargeHead extends Model
{
    /** @use HasFactory<ChargeHeadFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'basis' => ChargeBasis::class,
            'amount' => 'decimal:2',
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

    /** @return HasMany<FlatChargeOverride, $this> */
    public function overrides(): HasMany
    {
        return $this->hasMany(FlatChargeOverride::class);
    }

    /**
     * The unit types this head applies to. **Empty means every unit** — that is what keeps
     * a building that has never heard of unit types billing exactly as it always did.
     *
     * @return BelongsToMany<UnitType, $this>
     */
    public function unitTypes(): BelongsToMany
    {
        return $this->belongsToMany(UnitType::class);
    }

    /**
     * Whether this head is charged to a given unit.
     */
    public function appliesTo(Flat $flat): bool
    {
        $restrictedTo = $this->relationLoaded('unitTypes')
            ? $this->unitTypes->modelKeys()
            : $this->unitTypes()->pluck('unit_types.id')->all();

        if ($restrictedTo === []) {
            return true;
        }

        // A flat with no type is not "every type" — a head restricted to shops must not
        // reach a unit nobody has classified yet.
        return $flat->unit_type_id !== null && in_array($flat->unit_type_id, $restrictedTo, true);
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
