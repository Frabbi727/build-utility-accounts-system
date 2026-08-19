<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\VendorBillPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vendor_bill_id
 * @property string $voucher_no
 * @property string $amount
 * @property PaymentMethod $method
 * @property Carbon $paid_on
 * @property string|null $reference
 * @property int|null $paid_by
 */
#[Fillable(['vendor_bill_id', 'voucher_no', 'amount', 'method', 'paid_on', 'reference', 'paid_by'])]
class VendorBillPayment extends Model
{
    /** @use HasFactory<VendorBillPaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'paid_on' => 'date',
        ];
    }

    /** @return BelongsTo<VendorBill, $this> */
    public function vendorBill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class);
    }

    /** @return MorphMany<JournalEntry, $this> */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }
}
