<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentSubmissionStatus;
use Database\Factories\PaymentSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $building_id
 * @property int $flat_id
 * @property int $user_id
 * @property string $amount
 * @property PaymentMethod $payment_method
 * @property string $reference_number
 * @property Carbon $payment_date
 * @property string|null $slip_path
 * @property string|null $resident_notes
 * @property PaymentSubmissionStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property int|null $payment_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'building_id',
    'flat_id',
    'user_id',
    'amount',
    'payment_method',
    'reference_number',
    'payment_date',
    'slip_path',
    'resident_notes',
    'status',
    'reviewed_by',
    'reviewed_at',
    'rejection_reason',
    'payment_id',
])]
class PaymentSubmission extends Model
{
    /** @use HasFactory<PaymentSubmissionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'status' => PaymentSubmissionStatus::class,
            'payment_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Building, $this> */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /** @return BelongsTo<Flat, $this> */
    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
