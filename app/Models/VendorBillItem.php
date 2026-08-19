<?php

namespace App\Models;

use Database\Factories\VendorBillItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $vendor_bill_id
 * @property int $account_id
 * @property string $description
 * @property string $amount
 */
#[Fillable(['vendor_bill_id', 'account_id', 'description', 'amount'])]
class VendorBillItem extends Model
{
    /** @use HasFactory<VendorBillItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<VendorBill, $this> */
    public function vendorBill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
