<?php

namespace App\Models;

use App\Enums\NotificationTriggerEvent;
use Database\Factories\NotificationRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $building_id
 * @property NotificationTriggerEvent $trigger_event
 * @property int $days_offset
 * @property string $title_template
 * @property string $body_template
 * @property bool $is_active
 * @property array<string> $channels
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Building|null $building
 */
#[Fillable(['building_id', 'trigger_event', 'days_offset', 'title_template', 'body_template', 'is_active', 'channels'])]
class NotificationRule extends Model
{
    /** @use HasFactory<NotificationRuleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger_event' => NotificationTriggerEvent::class,
            'days_offset' => 'integer',
            'is_active' => 'boolean',
            'channels' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Building, $this>
     */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /**
     * @param  Builder<NotificationRule>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<NotificationRule>  $query
     */
    public function scopeForBuilding(Builder $query, ?int $buildingId): void
    {
        if ($buildingId === null) {
            $query->whereNull('building_id');
        } else {
            $query->where(function (Builder $q) use ($buildingId): void {
                $q->where('building_id', $buildingId)
                    ->orWhereNull('building_id');
            });
        }
    }
}
