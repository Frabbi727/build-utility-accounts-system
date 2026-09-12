<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceApiController extends Controller
{
    /**
     * Register or update a device with FCM token and hardware info.
     *
     * POST /api/v1/devices/register
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'device_token' => ['required', 'string', 'max:500'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
            'device_model' => ['nullable', 'string', 'max:100'],
            'os_version' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        /** @var User $user */
        $user = $request->user();

        // Upsert: if device_id exists, reassign to current user and reactivate
        $device = UserDevice::updateOrCreate(
            ['device_id' => $validated['device_id']],
            [
                'user_id' => $user->id,
                'device_token' => $validated['device_token'],
                'platform' => $validated['platform'] ?? 'android',
                'device_model' => $validated['device_model'] ?? null,
                'os_version' => $validated['os_version'] ?? null,
                'app_version' => $validated['app_version'] ?? null,
                'is_active' => true,
                'last_seen_at' => now(),
            ],
        );

        return ApiResponse::success([
            'device_id' => $device->device_id,
            'platform' => $device->platform,
        ], 'Device registered successfully');
    }

    /**
     * Deactivate a device on logout.
     *
     * DELETE /api/v1/devices/{device_id}
     */
    public function destroy(Request $request, string $deviceId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $device = UserDevice::where('device_id', $deviceId)
            ->where('user_id', $user->id)
            ->first();

        if (! $device) {
            return ApiResponse::error('Device not found', 404);
        }

        $device->update(['is_active' => false]);

        return ApiResponse::success(null, 'Device deactivated');
    }
}
