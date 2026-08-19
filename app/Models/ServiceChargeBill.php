<?php

namespace App\Models;

use App\Enums\BillStatus;
use Database\Factories\ServiceChargeBillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $flat_id
 * @property string $bill_no
 * @property Carbon $billing_month
 * @property Carbon $due_date
 * @property string $total_amount
 * @property BillStatus $status
 */
#[Fillable(['flat_id', 'bill_no', 'billing_month', 'due_date', 'total_amount', 'status'])]
class ServiceChargeBill extends Model
{
    /** @use HasFactory<ServiceChargeBillFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_month' => 'date',
            'due_date' => 'date',
            'total_amount' => 'decimal:2',
            'status' => BillStatus::class,
        ];
    }

    /** @return BelongsTo<Flat, $this> */
    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    /** @return HasMany<BillItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** @return MorphMany<JournalEntry, $this> */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    /**
     * Total allocated against this bill so far.
     */
    public function allocatedAmount(): string
    {
        return bcadd((string) $this->allocations()->sum('amount'), '0', 2);
    }

    /**
     * Amount still owed on this bill.
     */
    public function outstandingAmount(): string
    {
        return bcsub((string) $this->total_amount, $this->allocatedAmount(), 2);
    }

    /**
     * Recompute status from the allocations actually recorded, never from a cached figure.
     */
    public function refreshStatus(): void
    {
        $outstanding = $this->outstandingAmount();

        $this->status = match (true) {
            bccomp($outstanding, '0', 2) <= 0 => BillStatus::Paid,
            bccomp($this->allocatedAmount(), '0', 2) > 0 => BillStatus::PartiallyPaid,
            default => BillStatus::Unpaid,
        };

        $this->save();
    }
}
