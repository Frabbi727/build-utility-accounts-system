<?php

namespace App\Models;

use Database\Factories\AdHocChargeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $flat_id
 * @property int $account_id
 * @property string $description
 * @property string $amount
 * @property Carbon $effective_month
 * @property int|null $applied_to_bill_id
 */
#[Fillable(['flat_id', 'account_id', 'description', 'amount', 'effective_month', 'applied_to_bill_id'])]
class AdHocCharge extends Model
{
    /** @use HasFactory<AdHocChargeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_month' => 'date',
        ];
    }

    /** @return BelongsTo<Flat, $this> */
    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
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
