<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $notification_id
 * @property int $user_id
 * @property int|null $user_device_id
 * @property string $status
 * @property string|null $error_message
 * @property array<string, mixed>|null $response_payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Notification $notification
 * @property-read User $user
 * @property-read UserDevice|null $userDevice
 */
#[Fillable([
    'notification_id',
    'user_id',
    'user_device_id',
    'status',
    'error_message',
    'response_payload',
])]
class NotificationLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_payload' => 'array',
        ];
    }

    /** @return BelongsTo<Notification, $this> */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<UserDevice, $this> */
    public function userDevice(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class);
    }
}
