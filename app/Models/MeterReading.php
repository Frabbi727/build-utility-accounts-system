<?php

namespace App\Models;

use App\Enums\ReadingStatus;
use Database\Factories\MeterReadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One month's reading on one meter.
 *
 * @property int $id
 * @property int $meter_id
 * @property int|null $utility_tariff_id
 * @property Carbon $billing_month
 * @property Carbon $reading_date
 * @property string $previous_reading
 * @property string $current_reading
 * @property string $consumption
 * @property bool $is_estimated
 * @property string|null $note
 * @property int|null $recorded_by
 * @property ReadingStatus $status
 * @property int|null $applied_to_bill_id
 */
#[Fillable([
    'meter_id', 'utility_tariff_id', 'billing_month', 'reading_date',
    'previous_reading', 'current_reading', 'consumption', 'is_estimated',
    'note', 'recorded_by', 'status', 'applied_to_bill_id',
])]
class MeterReading extends Model
{
    /** @use HasFactory<MeterReadingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_month' => 'date',
            'reading_date' => 'date',
            // Dial values and consumption are quantities (scale 3), never money.
            'previous_reading' => 'decimal:3',
            'current_reading' => 'decimal:3',
            'consumption' => 'decimal:3',
            'is_estimated' => 'boolean',
            'status' => ReadingStatus::class,
        ];
    }

    /** @return BelongsTo<Meter, $this> */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    /** @return BelongsTo<UtilityTariff, $this> */
    public function tariff(): BelongsTo
    {
        return $this->belongsTo(UtilityTariff::class, 'utility_tariff_id');
    }

    /** @return BelongsTo<ServiceChargeBill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(ServiceChargeBill::class, 'applied_to_bill_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isConfirmed(): bool
    {
        return $this->status->isBillable();
    }

    public function isApplied(): bool
    {
        return $this->applied_to_bill_id !== null;
    }

    /**
     * Whether the dials wrapped past all-nines between the two readings.
     */
    public function rolledOver(): bool
    {
        return bccomp((string) $this->current_reading, (string) $this->previous_reading, 3) < 0;
    }
}
