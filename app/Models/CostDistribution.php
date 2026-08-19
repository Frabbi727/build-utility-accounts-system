<?php

namespace App\Models;

use App\Enums\DistributionBasis;
use App\Enums\DistributionStatus;
use Database\Factories\CostDistributionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A one-off cost spread across units.
 *
 * @property int $id
 * @property int $building_id
 * @property int $recovery_account_id
 * @property int|null $source_expense_id
 * @property int|null $utility_id
 * @property string $title
 * @property string|null $description
 * @property string $total_amount
 * @property DistributionBasis $basis
 * @property Carbon $billing_month
 * @property DistributionStatus $status
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 */
#[Fillable([
    'building_id', 'recovery_account_id', 'source_expense_id', 'utility_id',
    'title', 'description', 'total_amount', 'basis', 'billing_month',
    'status', 'created_by', 'approved_by', 'approved_at',
])]
class CostDistribution extends Model
{
    /** @use HasFactory<CostDistributionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'basis' => DistributionBasis::class,
            'billing_month' => 'date',
            'status' => DistributionStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Building, $this> */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /**
     * The **income** account this recovery is credited to — never the account the source
     * expense was debited to.
     *
     * @return BelongsTo<Account, $this>
     */
    public function recoveryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'recovery_account_id');
    }

    /**
     * Provenance only. Never consulted when posting.
     *
     * @return BelongsTo<Expense, $this>
     */
    public function sourceExpense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'source_expense_id');
    }

    /** @return BelongsTo<Utility, $this> */
    public function utility(): BelongsTo
    {
        return $this->belongsTo(Utility::class);
    }

    /** @return HasMany<CostDistributionLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(CostDistributionLine::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status->isBillable();
    }

    /**
     * Whether any of this distribution's lines have reached a bill.
     */
    public function isBilled(): bool
    {
        return $this->lines()->whereNotNull('applied_to_bill_id')->exists();
    }

    /**
     * The sum of the frozen lines, which must equal `total_amount` exactly.
     */
    public function allocatedAmount(): string
    {
        return bcadd((string) ($this->lines()->sum('amount') ?: '0'), '0', 2);
    }
}
