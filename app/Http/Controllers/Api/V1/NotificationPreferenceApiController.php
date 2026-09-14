<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceApiController extends Controller
{
    private const CHANNELS = ['push', 'in_app', 'sms', 'email'];

    private const CATEGORIES = ['bills', 'payments', 'maintenance', 'notices'];

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $stored = $user->notificationPreferences()->get();

        $matrix = [];

        foreach (self::CATEGORIES as $category) {
            foreach (self::CHANNELS as $channel) {
                $pref = $stored->where('category', $category)->where('channel', $channel)->first();
                $matrix[] = [
                    'category' => $category,
                    'channel' => $channel,
                    'is_enabled' => $pref !== null ? $pref->is_enabled : true,
                ];
            }
        }

        return ApiResponse::success([
            'preferences' => $matrix,
            'available_channels' => self::CHANNELS,
            'available_categories' => self::CATEGORIES,
        ], 'Notification preferences retrieved successfully');
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*.channel' => ['required', 'string', 'in:push,in_app,sms,email'],
            'preferences.*.category' => ['required', 'string', 'in:bills,payments,maintenance,notices'],
            'preferences.*.is_enabled' => ['required', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        foreach ($validated['preferences'] as $item) {
            UserNotificationPreference::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'channel' => $item['channel'],
                    'category' => $item['category'],
                ],
                [
                    'is_enabled' => $item['is_enabled'],
                ]
            );
        }

        return ApiResponse::success(null, 'Notification preferences updated successfully');
    }
}
