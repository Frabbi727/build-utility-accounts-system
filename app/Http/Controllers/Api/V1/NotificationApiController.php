<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationApiController extends Controller
{
    /**
     * Get paginated notification list for the authenticated user.
     *
     * GET /api/v1/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_read' => ['nullable', 'in:0,1,true,false'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $query = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at');

        if (isset($validated['is_read'])) {
            $isRead = filter_var($validated['is_read'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_read', $isRead);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = $query->paginate($perPage);

        return ApiResponse::paginated(
            $paginator->through(fn (Notification $n) => NotificationResource::make($n)),
            'Notifications retrieved',
        );
    }

    /**
     * Get the unread notification count.
     *
     * GET /api/v1/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $count = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return ApiResponse::success(['unread_count' => $count], 'Unread count retrieved');
    }

    /**
     * Mark a single notification as read.
     *
     * PATCH /api/v1/notifications/{id}/read
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $notification) {
            return ApiResponse::error('Notification not found', 404);
        }

        if (! $notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return ApiResponse::success(
            NotificationResource::make($notification->refresh()),
            'Notification marked as read',
        );
    }

    /**
     * Mark all notifications as read for the authenticated user.
     *
     * PATCH /api/v1/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $count = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return ApiResponse::success(['updated_count' => $count], 'All notifications marked as read');
    }
}
