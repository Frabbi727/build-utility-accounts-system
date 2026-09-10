<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Models\UserDeviceToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): int
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, UserDeviceToken> $tokens */
        $tokens = UserDeviceToken::where('user_id', $user->id)->get();

        if ($tokens->isEmpty()) {
            return 0;
        }

        $fcmKey = config('services.fcm.key');

        if (! $fcmKey) {
            Log::info("FCM push notification skipped (no server key configured) for User #{$user->id}: {$title} - {$body}");

            return 0;
        }

        $sentCount = 0;

        foreach ($tokens as $deviceToken) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'key='.$fcmKey,
                    'Content-Type' => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $deviceToken->token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'sound' => 'default',
                    ],
                    'data' => $data,
                ]);

                if ($response->successful()) {
                    $sentCount++;
                } elseif ($response->status() === 404 || str_contains($response->body(), 'UNREGISTERED') || str_contains($response->body(), 'NotRegistered')) {
                    $deviceToken->delete();
                } else {
                    Log::warning("FCM notification failed for token {$deviceToken->token}: {$response->body()}");
                }
            } catch (\Throwable $e) {
                Log::error("Exception sending FCM notification: {$e->getMessage()}");
            }
        }

        return $sentCount;
    }

    /**
     * @param  Collection<int, User>|array<int, User>  $users
     * @param  array<string, mixed>  $data
     */
    public function sendToUsers(Collection|array $users, string $title, string $body, array $data = []): int
    {
        $totalSent = 0;

        foreach ($users as $user) {
            $totalSent += $this->sendToUser($user, $title, $body, $data);
        }

        return $totalSent;
    }
}
