<?php

namespace App\Models;

use App\Enums\PeriodStatus;
use Database\Factories\AccountingPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $year
 * @property int $month
 * @property PeriodStatus $status
 * @property Carbon|null $locked_at
 * @property int|null $locked_by
 */
#[Fillable(['year', 'month', 'status', 'locked_at', 'locked_by'])]
class AccountingPeriod extends Model
{
    /** @use HasFactory<AccountingPeriodFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'status' => PeriodStatus::class,
            'locked_at' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->status === PeriodStatus::Locked;
    }

    /** @return HasMany<JournalEntry, $this> */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }
}
