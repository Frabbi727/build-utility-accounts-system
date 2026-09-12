<?php

namespace App\Models;

use App\Services\Audit\AuditContext;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $module
 * @property string|null $subject_type
 * @property int|string|null $subject_id
 * @property string|null $entity_type
 * @property string|null $entity_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property list<string>|null $changed_fields
 * @property string|null $description
 * @property string|null $request_id
 * @property string|null $source
 * @property string|null $platform
 * @property string|null $app_version
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $route
 * @property string|null $http_method
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property-read User|null $user
 * @property-read Model|null $subject
 */
#[Fillable([
    'user_id',
    'action',
    'module',
    'subject_type',
    'subject_id',
    'entity_type',
    'entity_id',
    'old_values',
    'new_values',
    'changed_fields',
    'description',
    'request_id',
    'source',
    'platform',
    'app_version',
    'ip_address',
    'user_agent',
    'route',
    'http_method',
    'payload',
    'created_at',
])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if ($model->created_at === null) {
                $model->created_at = now();
            }

            $context = app(AuditContext::class);
            $model->request_id ??= $context->getRequestId();
            $model->source ??= $context->getSource();
            $model->platform ??= $context->getPlatform();
            $model->app_version ??= $context->getAppVersion();
            $model->ip_address ??= $context->getIpAddress();
            $model->user_agent ??= $context->getUserAgent();
            $model->route ??= $context->getRoute();
            $model->http_method ??= $context->getHttpMethod();
            $model->user_id ??= $context->getUserId();

            if ($model->module === null && ! empty($model->action)) {
                $action = strtolower($model->action);
                $model->module = str_contains($action, '.') ? explode('.', $action)[0] : 'system';
            }

            // Keep subject and entity columns in sync
            if ($model->entity_type === null && $model->subject_type !== null) {
                $model->entity_type = $model->subject_type;
            }
            if ($model->entity_id === null && $model->subject_id !== null) {
                $model->entity_id = (string) $model->subject_id;
            }
            if ($model->subject_type === null && $model->entity_type !== null) {
                $model->subject_type = $model->entity_type;
            }
            if ($model->subject_id === null && $model->entity_id !== null && is_numeric($model->entity_id)) {
                $model->subject_id = (int) $model->entity_id;
            }
        });

        static::updating(function (): void {
            throw new RuntimeException('Audit logs are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Audit logs are immutable and cannot be deleted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'old_values' => 'array',
            'new_values' => 'array',
            'changed_fields' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Formatted human-readable entity display name.
     */
    public function entityDisplay(): string
    {
        $type = $this->entity_type ?? $this->subject_type ?? '';
        $shortType = class_basename($type);
        $id = $this->entity_id ?? $this->subject_id ?? '';

        if ($shortType === '' && $id === '') {
            return '—';
        }

        return trim("{$shortType} #{$id}");
    }

    /**
     * Formatted module name.
     */
    public function moduleLabel(): string
    {
        if ($this->module !== null && $this->module !== '') {
            return ucfirst(str_replace('_', ' ', $this->module));
        }

        $action = $this->action;
        if (str_contains($action, '.')) {
            return ucfirst(explode('.', $action)[0]);
        }

        return 'System';
    }

    /**
     * Get created_at timestamp converted to Dhaka timezone.
     */
    public function createdAtDhaka(): ?Carbon
    {
        return $this->created_at?->copy()->timezone('Asia/Dhaka');
    }

    /**
     * Format created_at in Dhaka timezone.
     */
    public function formattedCreatedAt(string $format = 'd M Y, h:i:s A'): string
    {
        return $this->created_at ? $this->created_at->copy()->timezone('Asia/Dhaka')->format($format) : '—';
    }

    /**
     * Whether this audit log has any snapshot state or payload.
     */
    public function hasSnapshot(): bool
    {
        return ! empty($this->new_values) || ! empty($this->old_values) || ! empty($this->payload);
    }

    /**
     * Determine field names to compare in before/after snapshot.
     *
     * @return list<string>
     */
    public function getComparisonFields(): array
    {
        if (! empty($this->changed_fields)) {
            return array_values($this->changed_fields);
        }

        $keys = array_unique(array_merge(
            array_keys($this->old_values ?? []),
            array_keys($this->new_values ?? [])
        ));

        sort($keys);

        return array_values($keys);
    }
}
