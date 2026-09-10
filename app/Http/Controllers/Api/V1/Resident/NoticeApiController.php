<?php

namespace App\Http\Controllers\Api\V1\Resident;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class NoticeApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $buildingIds = $this->getResidentBuildingIds($user);

        if ($buildingIds->isEmpty()) {
            return ApiResponse::error('No building linked to this resident account.', 404);
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 50);

        /** @var LengthAwarePaginator<int, Notice> $paginator */
        $paginator = Notice::whereIn('building_id', $buildingIds)
            ->active()
            ->when($request->filled('type'), function (Builder $query) use ($request): void {
                $query->where('type', $request->string('type')->toString());
            })
            ->orderByDesc('is_pinned')
            ->latest('published_at')
            ->paginate($perPage);

        /** @var LengthAwarePaginator<array-key, mixed> $transformed */
        $transformed = $paginator->through(fn (Notice $n): array => [
            'id' => $n->id,
            'title' => $n->title,
            'content' => $n->content,
            'type' => $n->type->value,
            'is_pinned' => $n->is_pinned,
            'published_at' => $n->published_at->toIso8601String(),
            'expires_at' => $n->expires_at?->toIso8601String(),
        ]);

        return ApiResponse::paginated($transformed, 'Notices retrieved successfully');
    }

    public function show(Request $request, Notice $notice): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $buildingIds = $this->getResidentBuildingIds($user);

        if (! $buildingIds->contains($notice->building_id)) {
            return ApiResponse::error('You are not authorized to view this notice.', 403);
        }

        return ApiResponse::success([
            'id' => $notice->id,
            'title' => $notice->title,
            'content' => $notice->content,
            'type' => $notice->type->value,
            'is_pinned' => $notice->is_pinned,
            'published_at' => $notice->published_at->toIso8601String(),
            'expires_at' => $notice->expires_at?->toIso8601String(),
        ], 'Notice details retrieved successfully');
    }

    /**
     * @return Collection<int, int>
     */
    private function getResidentBuildingIds(User $user): Collection
    {
        $buildingIds = collect();

        if ($user->owner) {
            $buildingIds = $user->owner->flats()->pluck('building_id');
        }

        if ($user->tenant?->flat) {
            $buildingIds->push($user->tenant->flat->building_id);
        }

        return $buildingIds->unique()->values();
    }
}
