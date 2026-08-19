<?php

namespace App\Models;

use Database\Factories\BillItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $service_charge_bill_id
 * @property int $account_id
 * @property string $description
 * @property string $amount
 * @property string|null $quantity
 * @property string|null $unit_rate
 * @property string|null $unit_label
 * @property string|null $source_type
 * @property int|null $source_id
 */
#[Fillable([
    'service_charge_bill_id', 'account_id', 'description', 'amount',
    'quantity', 'unit_rate', 'unit_label', 'source_type', 'source_id',
])]
class BillItem extends Model
{
    /** @use HasFactory<BillItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            // Neither of these is money: a quantity is scale 3, a rate scale 4.
            'quantity' => 'decimal:3',
            'unit_rate' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<ServiceChargeBill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(ServiceChargeBill::class, 'service_charge_bill_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Whatever produced this line — a charge head, an ad-hoc charge, a meter reading,
     * a distributed cost. Null on lines created before the column existed.
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Whether this line has a quantity and rate worth printing beside the amount.
     */
    public function isMetered(): bool
    {
        return $this->quantity !== null && $this->unit_rate !== null;
    }
}
