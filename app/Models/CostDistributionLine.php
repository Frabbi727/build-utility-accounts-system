<?php

namespace App\Models;

use Database\Factories\CostDistributionLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One unit's share of a distributed cost.
 *
 * @property int $id
 * @property int $cost_distribution_id
 * @property int $flat_id
 * @property string $weight
 * @property string $amount
 * @property int|null $applied_to_bill_id
 */
#[Fillable(['cost_distribution_id', 'flat_id', 'weight', 'amount', 'applied_to_bill_id'])]
class CostDistributionLine extends Model
{
    /** @use HasFactory<CostDistributionLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // A weight is a ratio, not money — it carries more precision.
            'weight' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<CostDistribution, $this> */
    public function distribution(): BelongsTo
    {
        return $this->belongsTo(CostDistribution::class, 'cost_distribution_id');
    }

    /** @return BelongsTo<Flat, $this> */
    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    /** @return BelongsTo<ServiceChargeBill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(ServiceChargeBill::class, 'applied_to_bill_id');
    }

    public function isApplied(): bool
    {
        return $this->applied_to_bill_id !== null;
    }
}
