<?php

namespace App\Models;

use Database\Factories\UtilityTariffSlabFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One band of a progressive tariff. `to_unit` null means unbounded.
 *
 * @property int $id
 * @property int $utility_tariff_id
 * @property string $from_unit
 * @property string|null $to_unit
 * @property string $rate_per_unit
 */
#[Fillable(['utility_tariff_id', 'from_unit', 'to_unit', 'rate_per_unit'])]
class UtilityTariffSlab extends Model
{
    /** @use HasFactory<UtilityTariffSlabFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Bounds are quantities (scale 3), the rate needs more precision than money.
            'from_unit' => 'decimal:3',
            'to_unit' => 'decimal:3',
            'rate_per_unit' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<UtilityTariff, $this> */
    public function tariff(): BelongsTo
    {
        return $this->belongsTo(UtilityTariff::class, 'utility_tariff_id');
    }

    /**
     * How many units this slab can absorb, or null if it is the unbounded last one.
     */
    public function width(): ?string
    {
        return $this->to_unit === null
            ? null
            : bcsub((string) $this->to_unit, (string) $this->from_unit, 3);
    }
}
