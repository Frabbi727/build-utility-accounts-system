<?php

namespace App\Models;

use App\Enums\NoticeType;
use App\Models\Concerns\Auditable;
use Database\Factories\NoticeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $building_id
 * @property int|null $created_by
 * @property string $title
 * @property string $content
 * @property NoticeType $type
 * @property bool $is_pinned
 * @property Carbon $published_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['building_id', 'created_by', 'title', 'content', 'type', 'is_pinned', 'published_at', 'expires_at'])]
class Notice extends Model
{
    use Auditable;

    /** @use HasFactory<NoticeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NoticeType::class,
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Building, $this> */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<Notice>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('published_at', '<=', now())
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    /**
     * @param  Builder<Notice>  $query
     */
    public function scopePinned(Builder $query): void
    {
        $query->where('is_pinned', true);
    }
}
