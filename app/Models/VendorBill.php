<?php

namespace App\Models;

use App\Enums\VendorBillStatus;
use Database\Factories\VendorBillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vendor_id
 * @property int|null $building_id
 * @property string $bill_no
 * @property string|null $vendor_reference
 * @property Carbon $bill_date
 * @property Carbon $due_date
 * @property string $total_amount
 * @property VendorBillStatus $status
 * @property string $description
 */
#[Fillable([
    'vendor_id', 'building_id', 'bill_no', 'vendor_reference',
    'bill_date', 'due_date', 'total_amount', 'status', 'description',
])]
class VendorBill extends Model
{
    /** @use HasFactory<VendorBillFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'due_date' => 'date',
            'total_amount' => 'decimal:2',
            'status' => VendorBillStatus::class,
        ];
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<Building, $this> */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /** @return HasMany<VendorBillItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(VendorBillItem::class);
    }

    /** @return HasMany<VendorBillPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(VendorBillPayment::class);
    }

    /** @return MorphMany<JournalEntry, $this> */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function paidAmount(): string
    {
        return bcadd((string) $this->payments()->sum('amount'), '0', 2);
    }

    public function outstandingAmount(): string
    {
        return bcsub((string) $this->total_amount, $this->paidAmount(), 2);
    }

    /**
     * Recompute status from the payments actually recorded.
     */
    public function refreshStatus(): void
    {
        $outstanding = $this->outstandingAmount();

        $this->status = match (true) {
            bccomp($outstanding, '0', 2) <= 0 => VendorBillStatus::Paid,
            bccomp($this->paidAmount(), '0', 2) > 0 => VendorBillStatus::PartiallyPaid,
            default => VendorBillStatus::Unpaid,
        };

        $this->save();
    }
}
