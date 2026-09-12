<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $module = '';

    public string $action = '';

    public ?int $userId = null;

    public string $entityType = '';

    public string $entityId = '';

    public string $source = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $requestId = '';

    public ?int $viewingLogId = null;

    public bool $showDetailModal = false;

    public ?string $historyEntityType = null;

    public ?string $historyEntityId = null;

    public bool $showHistoryModal = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingModule(): void
    {
        $this->resetPage();
    }

    public function updatingAction(): void
    {
        $this->resetPage();
    }

    public function updatingUserId(): void
    {
        $this->resetPage();
    }

    public function updatingEntityType(): void
    {
        $this->resetPage();
    }

    public function updatingEntityId(): void
    {
        $this->resetPage();
    }

    public function updatingSource(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function updatingRequestId(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search',
            'module',
            'action',
            'userId',
            'entityType',
            'entityId',
            'source',
            'dateFrom',
            'dateTo',
            'requestId',
        ]);
        $this->resetPage();
    }

    public function openDetailModal(int $id): void
    {
        $this->viewingLogId = $id;
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->viewingLogId = null;
    }

    public function viewEntityHistory(string $entityType, string $entityId): void
    {
        $this->historyEntityType = $entityType;
        $this->historyEntityId = $entityId;
        $this->showHistoryModal = true;
    }

    public function closeHistoryModal(): void
    {
        $this->showHistoryModal = false;
        $this->historyEntityType = null;
        $this->historyEntityId = null;
    }

    public function cancel(): void
    {
        if ($this->showDetailModal) {
            $this->closeDetailModal();
            return;
        }

        if ($this->showHistoryModal) {
            $this->closeHistoryModal();
        }
    }

    public function filterByRequestId(string $reqId): void
    {
        $this->closeDetailModal();
        $this->closeHistoryModal();
        $this->requestId = $reqId;
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('viewAny', User::class);

        $query = AuditLog::query()
            ->with('user')
            ->when($this->module !== '', fn (Builder $q) => $q->where('module', $this->module))
            ->when($this->action !== '', fn (Builder $q) => $q->where('action', $this->action))
            ->when($this->userId !== null, fn (Builder $q) => $q->where('user_id', $this->userId))
            ->when($this->entityType !== '', fn (Builder $q) => $q->where(function (Builder $sub): void {
                $sub->where('entity_type', $this->entityType)
                    ->orWhere('subject_type', $this->entityType);
            }))
            ->when($this->entityId !== '', fn (Builder $q) => $q->where(function (Builder $sub): void {
                $sub->where('entity_id', $this->entityId)
                    ->orWhere('subject_id', is_numeric($this->entityId) ? (int) $this->entityId : -1);
            }))
            ->when($this->source !== '', fn (Builder $q) => $q->where(function (Builder $sub): void {
                $sub->where('source', $this->source)
                    ->orWhere('platform', $this->source);
            }))
            ->when($this->requestId !== '', fn (Builder $q) => $q->where('request_id', $this->requestId))
            ->when($this->dateFrom !== '', function (Builder $q): void {
                $start = \Illuminate\Support\Carbon::parse($this->dateFrom, 'Asia/Dhaka')->startOfDay();
                $q->where('created_at', '>=', $start);
            })
            ->when($this->dateTo !== '', function (Builder $q): void {
                $end = \Illuminate\Support\Carbon::parse($this->dateTo, 'Asia/Dhaka')->endOfDay();
                $q->where('created_at', '<=', $end);
            })
            ->when($this->search !== '', function (Builder $q): void {
                $term = '%'.trim($this->search).'%';
                $q->where(function (Builder $sub) use ($term): void {
                    $sub->where('description', 'ilike', $term)
                        ->orWhere('action', 'ilike', $term)
                        ->orWhere('request_id', 'ilike', $term)
                        ->orWhere('entity_id', 'ilike', $term)
                        ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'ilike', $term)->orWhere('email', 'ilike', $term));
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $logs = $query->paginate(25);

        $viewingLog = $this->viewingLogId !== null
            ? AuditLog::with('user')->find($this->viewingLogId)
            : null;

        $historyLogs = ($this->historyEntityType !== null && $this->historyEntityId !== null)
            ? AuditLog::query()
                ->with('user')
                ->where(function (Builder $q): void {
                    $q->where(function (Builder $sub): void {
                        $sub->where('entity_type', $this->historyEntityType)
                            ->where('entity_id', $this->historyEntityId);
                    })->orWhere(function (Builder $sub): void {
                        $sub->where('subject_type', $this->historyEntityType)
                            ->where('subject_id', is_numeric($this->historyEntityId) ? (int) $this->historyEntityId : -1);
                    });
                })
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
            : collect();

        $modules = AuditLog::query()->whereNotNull('module')->distinct()->pluck('module')->sort()->values();
        $actions = AuditLog::query()->whereNotNull('action')->distinct()->pluck('action')->sort()->values();
        $sources = AuditLog::query()->whereNotNull('source')->distinct()->pluck('source')->sort()->values();
        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);

        return view('livewire.admin.audit-log-list', [
            'logs' => $logs,
            'viewingLog' => $viewingLog,
            'historyLogs' => $historyLogs,
            'modules' => $modules,
            'actions' => $actions,
            'sources' => $sources,
            'users' => $users,
        ])->layout('components.layouts.app');
    }
}
