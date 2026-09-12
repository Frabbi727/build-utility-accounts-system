<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    private const SENSITIVE_FIELDS = [
        'password',
        'remember_token',
        'token',
        'secret',
        'api_key',
        'current_password',
        'new_password',
        'password_confirmation',
        'card_number',
        'cvv',
        'pin',
    ];

    public function __construct(private readonly AuditContext $context) {}

    /**
     * Centralized method to write an audit log entry.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  list<string>|null  $changedFields
     * @param  array<string, mixed>|null  $payload
     */
    public function record(
        string $action,
        string $module,
        ?Model $entity = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $changedFields = null,
        ?array $payload = null,
        ?int $userId = null,
    ): AuditLog {
        $userId ??= Auth::id() ?? $this->context->getUserId();

        $entityType = $entity !== null ? $entity->getMorphClass() : null;
        $entityId = $entity !== null ? (string) $entity->getKey() : null;

        $sanitizedOld = $oldValues !== null ? $this->sanitizeValues($oldValues) : null;
        $sanitizedNew = $newValues !== null ? $this->sanitizeValues($newValues) : null;

        if ($description === null) {
            $entityName = $entity !== null ? class_basename($entity)." #{$entityId}" : 'Record';
            $description = trim("{$entityName} {$action}");
        }

        return AuditLog::create([
            'user_id' => $userId,
            'action' => strtoupper($action),
            'module' => strtolower($module),
            'subject_type' => $entityType,
            'subject_id' => $entityId !== null && is_numeric($entityId) ? (int) $entityId : null,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $sanitizedOld,
            'new_values' => $sanitizedNew,
            'changed_fields' => $changedFields,
            'description' => $description,
            'request_id' => $this->context->getRequestId(),
            'source' => $this->context->getSource(),
            'platform' => $this->context->getPlatform(),
            'app_version' => $this->context->getAppVersion(),
            'ip_address' => $this->context->getIpAddress(),
            'user_agent' => $this->context->getUserAgent(),
            'route' => $this->context->getRoute(),
            'http_method' => $this->context->getHttpMethod(),
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    /**
     * Log an authentication or security event (LOGIN, LOGOUT, FAILED_LOGIN, PASSWORD_CHANGE).
     *
     * @param  array<string, mixed>|null  $details
     */
    public function logAuth(string $action, ?User $user, ?string $description = null, ?array $details = null): AuditLog
    {
        $userId = $user?->id;
        $userEmail = $user !== null ? $user->email : ((string) ($details['email'] ?? 'Unknown'));

        $desc = $description ?? match (strtoupper($action)) {
            'LOGIN' => "User {$userEmail} logged in",
            'LOGOUT' => "User {$userEmail} logged out",
            'FAILED_LOGIN' => "Failed login attempt for {$userEmail}",
            'PASSWORD_CHANGE' => "User {$userEmail} changed password",
            default => "Auth event: {$action} for {$userEmail}",
        };

        return $this->record(
            action: $action,
            module: 'auth',
            entity: $user,
            description: $desc,
            payload: $details,
            userId: $userId,
        );
    }

    /**
     * Mask sensitive values before persisting in audit log.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function sanitizeValues(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_FIELDS, true)) {
                $sanitized[$key] = '********';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeValues($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
