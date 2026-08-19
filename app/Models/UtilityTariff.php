<?php

namespace App\Models;

use Database\Factories\UtilityTariffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A utility's rate card from a given date. Superseded, never edited.
 *
 * @property int $id
 * @property int $utility_id
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $fixed_charge
 */
#[Fillable(['utility_id', 'effective_from', 'effective_to', 'fixed_charge'])]
class UtilityTariff extends Model
{
    /** @use HasFactory<UtilityTariffFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'fixed_charge' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Utility, $this> */
    public function utility(): BelongsTo
    {
        return $this->belongsTo(Utility::class);
    }

    /**
     * Ordered so the slab walk in TariffSlabCalculator can drain consumption in one pass.
     *
     * @return HasMany<UtilityTariffSlab, $this>
     */
    public function slabs(): HasMany
    {
        return $this->hasMany(UtilityTariffSlab::class)->orderBy('from_unit');
    }

    /** @return HasMany<MeterReading, $this> */
    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    /**
     * A tariff that has priced a confirmed reading is history and must not change, or
     * the bill it produced stops being reproducible.
     */
    public function isInUse(): bool
    {
        return $this->readings()->exists();
    }
}
