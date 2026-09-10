<?php

namespace App\Livewire\Admin;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Jobs\SendResidentPushNotificationJob;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Building;
use App\Models\MaintenanceRequest;
use App\Models\Vendor;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class MaintenanceRequestList extends Component
{
    use WithCrudModal, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $priorityFilter = '';

    public ?int $flatId = null;

    public string $title = '';

    public string $description = '';

    public string $category = 'other';

    public string $priority = 'medium';

    public string $status = 'open';

    public ?int $assignedStaffId = null;

    public ?int $assignedVendorId = null;

    public ?string $resolutionNotes = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPriorityFilter(): void
    {
        $this->resetPage();
    }

    private function building(): ?Building
    {
        return app(CurrentBuilding::class)->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formRules(): array
    {
        return [
            'flatId' => [
                'required', 'integer',
                Rule::exists('flats', 'id')->where('building_id', app(CurrentBuilding::class)->id()),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category' => ['required', Rule::enum(MaintenanceCategory::class)],
            'priority' => ['required', Rule::enum(MaintenancePriority::class)],
            'status' => ['required', Rule::enum(MaintenanceStatus::class)],
            'assignedStaffId' => [
                'nullable', 'integer',
                Rule::exists('staff', 'id')->where('building_id', app(CurrentBuilding::class)->id()),
            ],
            'assignedVendorId' => ['nullable', 'integer', Rule::exists('vendors', 'id')],
            'resolutionNotes' => ['nullable', 'string'],
        ];
    }

    /**
     * @param  MaintenanceRequest|null  $record
     */
    protected function fillForm(?Model $record): void
    {
        $this->flatId = $record?->flat_id;
        $this->title = $record->title ?? '';
        $this->description = $record->description ?? '';
        $this->category = $record?->category->value ?? MaintenanceCategory::Other->value;
        $this->priority = $record?->priority->value ?? MaintenancePriority::Medium->value;
        $this->status = $record?->status->value ?? MaintenanceStatus::Open->value;
        $this->assignedStaffId = $record?->assigned_staff_id;
        $this->assignedVendorId = $record?->assigned_vendor_id;
        $this->resolutionNotes = $record?->resolution_notes;
    }

    protected function findRecord(int $id): MaintenanceRequest
    {
        return MaintenanceRequest::where('building_id', app(CurrentBuilding::class)->id())->findOrFail($id);
    }

    protected function authorizeAction(string $ability, ?Model $record = null): void
    {
        $this->authorize($ability, $record ?? MaintenanceRequest::class);
    }

    protected function persist(): MaintenanceRequest
    {
        $record = $this->editingId === null ? new MaintenanceRequest : $this->findRecord($this->editingId);

        $resolvedAt = $record->resolved_at;
        if (in_array($this->status, [MaintenanceStatus::Resolved->value, MaintenanceStatus::Closed->value], true) && $resolvedAt === null) {
            $resolvedAt = now();
        } elseif ($this->status === MaintenanceStatus::Open->value || $this->status === MaintenanceStatus::InProgress->value) {
            $resolvedAt = null;
        }

        $record->fill([
            'building_id' => app(CurrentBuilding::class)->getOrFail()->id,
            'flat_id' => $this->flatId,
            'user_id' => $record->user_id ?? auth()->id(),
            'title' => $this->title,
            'description' => $this->description,
            'category' => MaintenanceCategory::from($this->category),
            'priority' => MaintenancePriority::from($this->priority),
            'status' => MaintenanceStatus::from($this->status),
            'assigned_staff_id' => $this->assignedStaffId,
            'assigned_vendor_id' => $this->assignedVendorId,
            'resolution_notes' => $this->resolutionNotes,
            'resolved_at' => $resolvedAt,
        ])->save();

        if ($record->user_id) {
            SendResidentPushNotificationJob::dispatch(
                [$record->user_id],
                'Maintenance Ticket Updated: '.$record->title,
                "Status: {$record->status->label()}".($record->resolution_notes ? " - {$record->resolution_notes}" : ''),
                ['type' => 'ticket_updated', 'ticket_id' => $record->id]
            );
        }

        return $record;
    }

    public function render(): View
    {
        $this->authorize('viewAny', MaintenanceRequest::class);

        $building = $this->building();
        $requests = $building === null
            ? new LengthAwarePaginator([], 0, 15)
            : $building->maintenanceRequests()
                ->with(['flat', 'user', 'assignedStaff', 'assignedVendor'])
                ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
                ->when($this->priorityFilter !== '', fn ($q) => $q->where('priority', $this->priorityFilter))
                ->when($this->search !== '', fn ($q) => $q->where(function ($sub) {
                    $sub->where('title', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%")
                        ->orWhereHas('flat', fn ($f) => $f->where('number', 'like', "%{$this->search}%"));
                }))
                ->latest('created_at')
                ->paginate(15);

        return view('livewire.admin.maintenance-request-list', [
            'building' => $building,
            'requests' => $requests,
            'flats' => $building ? $building->flats()->orderBy('number')->get() : collect(),
            'staffMembers' => $building ? $building->staff()->orderBy('name')->get() : collect(),
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->get(),
            'categories' => MaintenanceCategory::cases(),
            'priorities' => MaintenancePriority::cases(),
            'statuses' => MaintenanceStatus::cases(),
        ])->layout('components.layouts.app');
    }
}
