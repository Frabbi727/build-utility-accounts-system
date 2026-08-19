<?php

namespace App\Models;

use Database\Factories\FlatChargeOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A flat that pays something other than the charge head's computed amount, or
 * nothing at all.
 *
 * @property int $id
 * @property int $flat_id
 * @property int $charge_head_id
 * @property string|null $amount
 * @property bool $is_exempt
 */
#[Fillable(['flat_id', 'charge_head_id', 'amount', 'is_exempt'])]
class FlatChargeOverride extends Model
{
    /** @use HasFactory<FlatChargeOverrideFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_exempt' => 'boolean',
        ];
    }

    /** @return BelongsTo<Flat, $this> */
    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    /** @return BelongsTo<ChargeHead, $this> */
    public function chargeHead(): BelongsTo
    {
        return $this->belongsTo(ChargeHead::class);
    }
}
