<?php

namespace App\Http\Controllers\Api\V1\Resident;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Flat;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestActivity;
use App\Models\User;
use App\Services\Maintenance\MaintenanceSlaService;
use App\Services\Maintenance\MaintenanceTimelineService;
use App\Services\Notification\MaintenanceNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class MaintenanceRequestApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $flats = $this->getResidentFlats($user);

        if ($flats->isEmpty()) {
            return ApiResponse::error('No flat linked to this resident account.', 404);
        }

        $flatId = $request->filled('flat_id')
            ? $request->integer('flat_id')
            : $flats->first()->id;

        $flat = $flats->firstWhere('id', $flatId);

        if ($flat === null) {
            return ApiResponse::error('You are not authorized to access this flat.', 403);
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 50);

        /** @var LengthAwarePaginator<int, MaintenanceRequest> $paginator */
        $paginator = MaintenanceRequest::where('flat_id', $flat->id)
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('category'), function (Builder $query) use ($request): void {
                $query->where('category', $request->string('category')->toString());
            })
            ->when($request->filled('priority'), function (Builder $query) use ($request): void {
                $query->where('priority', $request->string('priority')->toString());
            })
            ->latest('created_at')
            ->paginate($perPage);

        /** @var LengthAwarePaginator<array-key, mixed> $transformed */
        $transformed = $paginator->through(fn (MaintenanceRequest $m): array => [
            'id' => $m->id,
            'title' => $m->title,
            'description' => $m->description,
            'category' => $m->category->value,
            'priority' => $m->priority->value,
            'status' => $m->status->value,
            'due_by' => $m->due_by?->toIso8601String(),
            'sla_status' => $m->slaStatus(),
            'is_overdue' => $m->isOverdue(),
            'hours_remaining' => $m->hoursRemaining(),
            'cost' => $m->cost,
            'rating' => $m->rating,
            'rating_comment' => $m->rating_comment,
            'before_photo_url' => $m->before_photo_path ? asset('storage/'.$m->before_photo_path) : null,
            'after_photo_url' => $m->after_photo_path ? asset('storage/'.$m->after_photo_path) : null,
            'assigned_staff' => $m->assignedStaff !== null ? $m->assignedStaff->name : null,
            'resolution_notes' => $m->resolution_notes,
            'resolved_at' => $m->resolved_at?->toIso8601String(),
            'created_at' => $m->created_at->toIso8601String(),
        ]);

        return ApiResponse::paginated($transformed, 'Maintenance requests retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'flat_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category' => ['required', Rule::enum(MaintenanceCategory::class)],
            'priority' => ['required', Rule::enum(MaintenancePriority::class)],
            'before_photo' => ['nullable', 'image', 'max:5120'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $flats = $this->getResidentFlats($user);
        $flat = $flats->firstWhere('id', (int) $validated['flat_id']);

        if ($flat === null) {
            return ApiResponse::error('You are not authorized to submit maintenance requests for this flat.', 403);
        }

        $priority = MaintenancePriority::from($validated['priority']);
        $dueBy = app(MaintenanceSlaService::class)->calculateDueBy(now(), $priority);

        $beforePhotoPath = null;
        if ($request->hasFile('before_photo')) {
            $beforePhotoPath = $request->file('before_photo')?->store('maintenance-photos', 'public');
        }

        $ticket = MaintenanceRequest::create([
            'building_id' => $flat->building_id,
            'flat_id' => $flat->id,
            'user_id' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'category' => MaintenanceCategory::from($validated['category']),
            'priority' => $priority,
            'status' => MaintenanceStatus::Open,
            'due_by' => $dueBy,
            'before_photo_path' => $beforePhotoPath,
        ]);

        app(MaintenanceTimelineService::class)->record(
            $ticket,
            'created',
            "Resident submitted maintenance request for Flat {$flat->number}",
            ['priority' => $priority->value],
            $user
        );

        app(MaintenanceNotificationService::class)->notifyTicketCreated($ticket);

        return ApiResponse::success([
            'id' => $ticket->id,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'category' => $ticket->category->value,
            'priority' => $ticket->priority->value,
            'status' => $ticket->status->value,
            'due_by' => $ticket->due_by?->toIso8601String(),
            'sla_status' => $ticket->slaStatus(),
            'before_photo_url' => $ticket->before_photo_path ? asset('storage/'.$ticket->before_photo_path) : null,
            'created_at' => $ticket->created_at->toIso8601String(),
        ], 'Maintenance request submitted successfully', 201);
    }

    public function show(Request $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $flats = $this->getResidentFlats($user);

        if (! $flats->contains('id', $maintenanceRequest->flat_id)) {
            return ApiResponse::error('You are not authorized to access this maintenance request.', 403);
        }

        $maintenanceRequest->loadMissing(['assignedStaff', 'assignedVendor', 'activities.user']);

        return ApiResponse::success([
            'id' => $maintenanceRequest->id,
            'title' => $maintenanceRequest->title,
            'description' => $maintenanceRequest->description,
            'category' => $maintenanceRequest->category->value,
            'priority' => $maintenanceRequest->priority->value,
            'status' => $maintenanceRequest->status->value,
            'due_by' => $maintenanceRequest->due_by?->toIso8601String(),
            'sla_status' => $maintenanceRequest->slaStatus(),
            'is_overdue' => $maintenanceRequest->isOverdue(),
            'hours_remaining' => $maintenanceRequest->hoursRemaining(),
            'cost' => $maintenanceRequest->cost,
            'rating' => $maintenanceRequest->rating,
            'rating_comment' => $maintenanceRequest->rating_comment,
            'before_photo_url' => $maintenanceRequest->before_photo_path ? asset('storage/'.$maintenanceRequest->before_photo_path) : null,
            'after_photo_url' => $maintenanceRequest->after_photo_path ? asset('storage/'.$maintenanceRequest->after_photo_path) : null,
            'assigned_staff' => $maintenanceRequest->assignedStaff !== null ? $maintenanceRequest->assignedStaff->name : null,
            'assigned_vendor' => $maintenanceRequest->assignedVendor !== null ? $maintenanceRequest->assignedVendor->name : null,
            'resolution_notes' => $maintenanceRequest->resolution_notes,
            'resolved_at' => $maintenanceRequest->resolved_at?->toIso8601String(),
            'activities' => $maintenanceRequest->activities->map(fn (MaintenanceRequestActivity $a): array => [
                'id' => $a->id,
                'type' => $a->type,
                'description' => $a->description,
                'user' => $a->user?->name,
                'created_at' => $a->created_at->toIso8601String(),
            ]),
            'created_at' => $maintenanceRequest->created_at->toIso8601String(),
        ], 'Maintenance request details retrieved successfully');
    }

    public function rate(Request $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $flats = $this->getResidentFlats($user);

        if (! $flats->contains('id', $maintenanceRequest->flat_id)) {
            return ApiResponse::error('You are not authorized to rate this maintenance request.', 403);
        }

        if (! $maintenanceRequest->status->isClosed()) {
            return ApiResponse::error('You can only rate maintenance requests that have been resolved or closed.', 422);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'rating_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $maintenanceRequest->rating = (int) $validated['rating'];
        $maintenanceRequest->rating_comment = $validated['rating_comment'] ?? null;
        $maintenanceRequest->save();

        app(MaintenanceTimelineService::class)->record(
            $maintenanceRequest,
            'rated',
            "Resident submitted rating of {$maintenanceRequest->rating}/5 stars.",
            ['rating' => $maintenanceRequest->rating, 'comment' => $maintenanceRequest->rating_comment],
            $user
        );

        return ApiResponse::success([
            'id' => $maintenanceRequest->id,
            'rating' => $maintenanceRequest->rating,
            'rating_comment' => $maintenanceRequest->rating_comment,
        ], 'Thank you for your rating and feedback!');
    }

    /**
     * @return Collection<int, Flat>
     */
    private function getResidentFlats(User $user): Collection
    {
        $flats = collect();

        if ($user->owner) {
            $flats = $user->owner->flats()->with(['building', 'floor'])->orderBy('number')->get();
        }

        if ($user->tenant?->flat) {
            $tenantFlat = $user->tenant->flat;
            $tenantFlat->loadMissing(['building', 'floor']);
            if (! $flats->contains('id', $tenantFlat->id)) {
                $flats->push($tenantFlat);
            }
        }

        return $flats;
    }
}
