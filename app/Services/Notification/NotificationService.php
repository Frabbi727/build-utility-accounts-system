<?php

namespace App\Services\Notification;

use App\Enums\NotificationType;
use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Create notification records for recipients and dispatch push delivery.
     *
     * @param  User|EloquentCollection<int, User>|Collection<int, User>|array<int, User|int>  $recipients
     * @param  array<string, mixed>  $data
     * @return Collection<int, Notification>
     */
    public function send(
        User|EloquentCollection|Collection|array $recipients,
        NotificationType $type,
        string $title,
        string $body,
        array $data = [],
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notificationKey = null,
    ): Collection {
        $users = $this->resolveUsers($recipients);

        if ($users->isEmpty()) {
            return collect();
        }

        $notifications = collect();

        foreach ($users as $user) {
            $notification = $this->createNotification(
                $user,
                $type,
                $title,
                $body,
                $data,
                $referenceType,
                $referenceId,
                $notificationKey ? "{$notificationKey}:{$user->id}" : null,
            );

            if ($notification) {
                $notifications->push($notification);
            }
        }

        // Dispatch push delivery job after the current DB transaction commits
        if ($notifications->isNotEmpty()) {
            SendPushNotificationJob::dispatch(
                $notifications->pluck('id')->all(),
            )->afterCommit();
        }

        return $notifications;
    }

    /**
     * Create a single notification record with idempotency check.
     *
     * @param  array<string, mixed>  $data
     */
    private function createNotification(
        User $user,
        NotificationType $type,
        string $title,
        string $body,
        array $data,
        ?string $referenceType,
        ?int $referenceId,
        ?string $notificationKey,
    ): ?Notification {
        // Idempotency: skip if this notification_key already exists
        if ($notificationKey) {
            $existing = Notification::where('notification_key', $notificationKey)->first();
            if ($existing) {
                return null;
            }
        }

        return Notification::create([
            'user_id' => $user->id,
            'type' => $type->value,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notification_key' => $notificationKey,
            'is_read' => false,
            'status' => 'pending',
        ]);
    }

    /**
     * Resolve the recipients parameter into a Collection of User models.
     *
     * @param  User|EloquentCollection<int, User>|Collection<int, User>|array<int, User|int>  $recipients
     * @return Collection<int, User>
     */
    private function resolveUsers(User|EloquentCollection|Collection|array $recipients): Collection
    {
        if ($recipients instanceof User) {
            return collect([$recipients]);
        }

        if (is_array($recipients)) {
            $recipients = collect($recipients);
        }

        // If the collection contains IDs (integers) rather than User models, load them
        if ($recipients->isNotEmpty() && is_int($recipients->first())) {
            return User::whereIn('id', $recipients->all())->get();
        }

        return $recipients instanceof EloquentCollection
            ? collect($recipients->all())
            : $recipients;
    }
}
