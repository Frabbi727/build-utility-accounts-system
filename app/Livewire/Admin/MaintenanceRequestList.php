<?php

namespace App\Livewire\Admin;

use App\Enums\AccountCode;
use App\Enums\AccountType;
use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Account;
use App\Models\Building;
use App\Models\MaintenanceRequest;
use App\Models\Vendor;
use App\Services\JournalService;
use App\Services\Maintenance\CreateVendorBillForTicket;
use App\Services\Maintenance\MaintenanceSlaService;
use App\Services\Maintenance\MaintenanceTimelineService;
use App\Services\Notification\MaintenanceNotificationService;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class MaintenanceRequestList extends Component
{
    use WithCrudModal, WithFileUploads, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $priorityFilter = '';

    public string $slaFilter = '';

    public ?int $flatId = null;

    public string $title = '';

    public string $description = '';

    public string $category = 'other';

    public string $priority = 'medium';

    public string $status = 'open';

    public ?int $assignedStaffId = null;

    public ?int $assignedVendorId = null;

    public ?string $resolutionNotes = null;

    /** @var mixed */
    public $beforePhoto = null;

    /** @var mixed */
    public $afterPhoto = null;

    // Timeline modal
    public bool $showTimelineModal = false;

    public ?int $viewingTimelineId = null;

    // Photos modal
    public bool $showPhotosModal = false;

    public ?int $viewingPhotosId = null;

    // Vendor Bill creation modal
    public bool $showBillModal = false;

    public ?int $billTicketId = null;

    public ?int $billVendorId = null;

    public string $billAmount = '';

    public ?int $billExpenseAccountId = null;

    public string $billDescription = '';

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

    public function updatingSlaFilter(): void
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
            'beforePhoto' => ['nullable', 'image', 'max:5120'],
            'afterPhoto' => ['nullable', 'image', 'max:5120'],
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
        $this->beforePhoto = null;
        $this->afterPhoto = null;
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

        $previousStatus = $record->exists ? $record->status : null;
        $previousStaffId = $record->exists ? $record->assigned_staff_id : null;
        $previousVendorId = $record->exists ? $record->assigned_vendor_id : null;
        $previousResolutionNotes = $record->exists ? $record->resolution_notes : null;

        $resolvedAt = $record->resolved_at;
        if (in_array($this->status, [MaintenanceStatus::Resolved->value, MaintenanceStatus::Closed->value], true) && $resolvedAt === null) {
            $resolvedAt = now();
        } elseif ($this->status === MaintenanceStatus::Open->value || $this->status === MaintenanceStatus::InProgress->value) {
            $resolvedAt = null;
        }

        $priorityEnum = MaintenancePriority::from($this->priority);
        $dueBy = $record->due_by;
        if ($dueBy === null || ! $record->exists || $record->priority !== $priorityEnum) {
            $dueBy = app(MaintenanceSlaService::class)->calculateDueBy($record->created_at ?? now(), $priorityEnum);
        }

        $beforePhotoPath = $record->before_photo_path;
        if ($this->beforePhoto) {
            $beforePhotoPath = $this->beforePhoto->store('maintenance-photos', 'public');
        }

        $afterPhotoPath = $record->after_photo_path;
        if ($this->afterPhoto) {
            $afterPhotoPath = $this->afterPhoto->store('maintenance-photos', 'public');
        }

        $isNew = ! $record->exists;

        $record->fill([
            'building_id' => app(CurrentBuilding::class)->getOrFail()->id,
            'flat_id' => $this->flatId,
            'user_id' => $record->user_id ?? auth()->id(),
            'title' => $this->title,
            'description' => $this->description,
            'category' => MaintenanceCategory::from($this->category),
            'priority' => $priorityEnum,
            'status' => MaintenanceStatus::from($this->status),
            'assigned_staff_id' => $this->assignedStaffId,
            'assigned_vendor_id' => $this->assignedVendorId,
            'resolution_notes' => $this->resolutionNotes,
            'resolved_at' => $resolvedAt,
            'due_by' => $dueBy,
            'before_photo_path' => $beforePhotoPath,
            'after_photo_path' => $afterPhotoPath,
        ])->save();

        $timelineService = app(MaintenanceTimelineService::class);

        if ($isNew) {
            $timelineService->record($record, 'created', "Maintenance request created with {$priorityEnum->label()} priority.");
        }

        $notificationService = app(MaintenanceNotificationService::class);

        $assignmentChanged = ($record->assigned_staff_id !== $previousStaffId && $record->assigned_staff_id !== null)
            || ($record->assigned_vendor_id !== $previousVendorId && $record->assigned_vendor_id !== null);

        if ($assignmentChanged) {
            $notificationService->notifyAssignment($record, $previousStaffId, $previousVendorId);

            $assignedName = $record->assignedStaff !== null ? $record->assignedStaff->name : ($record->assignedVendor !== null ? $record->assignedVendor->name : 'None');
            $timelineService->record($record, 'assigned', "Assigned to {$assignedName}");
        }

        $statusChanged = $previousStatus !== null && $record->status !== $previousStatus;
        $notesChanged = $previousResolutionNotes !== $record->resolution_notes && filled($record->resolution_notes);

        if ($statusChanged || $notesChanged) {
            $notificationService->notifyStatusChanged($record, $previousStatus);
            $timelineService->record($record, 'status_changed', "Status changed to {$record->status->label()}");
        }

        if ($this->beforePhoto) {
            $timelineService->record($record, 'photo_uploaded', 'Before repair photo uploaded.');
        }

        if ($this->afterPhoto) {
            $timelineService->record($record, 'photo_uploaded', 'After repair photo uploaded.');
        }

        return $record;
    }

    public function openTimeline(int $id): void
    {
        $this->viewingTimelineId = $id;
        $this->showTimelineModal = true;
    }

    public function closeTimeline(): void
    {
        $this->viewingTimelineId = null;
        $this->showTimelineModal = false;
    }

    public function openPhotos(int $id): void
    {
        $this->viewingPhotosId = $id;
        $this->showPhotosModal = true;
    }

    public function closePhotos(): void
    {
        $this->viewingPhotosId = null;
        $this->showPhotosModal = false;
    }

    public function openBillModal(int $id): void
    {
        $ticket = $this->findRecord($id);
        $this->billTicketId = $ticket->id;
        $this->billVendorId = $ticket->assigned_vendor_id;
        $this->billDescription = "Repair bill for ticket #{$ticket->id}: {$ticket->title}";
        $this->billAmount = '';

        $repairsAccount = app(JournalService::class)->account(AccountCode::RepairsMaintenance);
        $this->billExpenseAccountId = $repairsAccount->id;

        $this->showBillModal = true;
    }

    public function closeBillModal(): void
    {
        $this->billTicketId = null;
        $this->billVendorId = null;
        $this->billAmount = '';
        $this->billExpenseAccountId = null;
        $this->billDescription = '';
        $this->showBillModal = false;
    }

    public function generateVendorBill(CreateVendorBillForTicket $billService): void
    {
        $this->validate([
            'billTicketId' => ['required', 'integer'],
            'billVendorId' => ['required', 'integer', Rule::exists('vendors', 'id')],
            'billAmount' => ['required', 'numeric', 'gt:0'],
            'billExpenseAccountId' => ['required', 'integer', Rule::exists('accounts', 'id')],
            'billDescription' => ['required', 'string', 'max:255'],
        ]);

        $ticket = $this->findRecord((int) $this->billTicketId);
        $vendor = Vendor::findOrFail((int) $this->billVendorId);

        $billService->handle(
            $ticket,
            $vendor,
            $this->billAmount,
            (int) $this->billExpenseAccountId,
            $this->billDescription
        );

        $this->closeBillModal();
        session()->flash('success', __('maintenance.bill_created_success'));
    }

    public function render(): View
    {
        $this->authorize('viewAny', MaintenanceRequest::class);

        $building = $this->building();
        $requests = $building === null
            ? new LengthAwarePaginator([], 0, 15)
            : $building->maintenanceRequests()
                ->with(['flat', 'user', 'assignedStaff', 'assignedVendor', 'vendorBills', 'activities.user'])
                ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
                ->when($this->priorityFilter !== '', fn ($q) => $q->where('priority', $this->priorityFilter))
                ->when($this->slaFilter === 'overdue', fn ($q) => $q->whereIn('status', [MaintenanceStatus::Open, MaintenanceStatus::InProgress])->whereNotNull('due_by')->where('due_by', '<', now()))
                ->when($this->slaFilter === 'due_soon', fn ($q) => $q->whereIn('status', [MaintenanceStatus::Open, MaintenanceStatus::InProgress])->whereNotNull('due_by')->where('due_by', '>=', now())->where('due_by', '<=', now()->addHours(4)))
                ->when($this->slaFilter === 'on_track', fn ($q) => $q->whereIn('status', [MaintenanceStatus::Open, MaintenanceStatus::InProgress])->where(fn ($sub) => $sub->whereNull('due_by')->orWhere('due_by', '>', now()->addHours(4))))
                ->when($this->slaFilter === 'resolved', fn ($q) => $q->whereIn('status', [MaintenanceStatus::Resolved, MaintenanceStatus::Closed]))
                ->when(filled($this->search), function ($q) {
                    $term = '%'.addcslashes(trim($this->search), '%_').'%';
                    $q->where(function ($sub) use ($term) {
                        $sub->where('title', 'ilike', $term)
                            ->orWhere('description', 'ilike', $term)
                            ->orWhereHas('flat', fn ($f) => $f->where('number', 'ilike', $term));
                    });
                })
                ->latest('created_at')
                ->paginate(15);

        $timelineRecord = $this->viewingTimelineId ? MaintenanceRequest::with('activities.user')->find($this->viewingTimelineId) : null;
        $photosRecord = $this->viewingPhotosId ? MaintenanceRequest::find($this->viewingPhotosId) : null;
        $expenseAccounts = Account::where('type', AccountType::Expense)->where('is_postable', true)->orderBy('code')->get();

        return view('livewire.admin.maintenance-request-list', [
            'building' => $building,
            'requests' => $requests,
            'flats' => $building ? $building->flats()->orderBy('number')->get() : collect(),
            'staffMembers' => $building ? $building->staff()->orderBy('name')->get() : collect(),
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->get(),
            'categories' => MaintenanceCategory::cases(),
            'priorities' => MaintenancePriority::cases(),
            'statuses' => MaintenanceStatus::cases(),
            'timelineRecord' => $timelineRecord,
            'photosRecord' => $photosRecord,
            'expenseAccounts' => $expenseAccounts,
        ])->layout('components.layouts.app');
    }
}
