<?php

namespace App\Livewire\Admin;

use App\Enums\NotificationType;
use App\Livewire\Concerns\WithNotices;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationList extends Component
{
    use WithNotices, WithPagination;

    public string $search = '';

    public string $type = '';

    public string $status = '';

    public ?int $userId = null;

    public string $isRead = '';

    // Manual send modal
    public bool $showSendModal = false;

    public string $sendTitle = '';

    public string $sendBody = '';

    public string $sendTarget = 'all_residents';

    public ?int $sendUserId = null;

    // Detail modal
    public ?int $viewingId = null;

    public bool $showDetailModal = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingUserId(): void
    {
        $this->resetPage();
    }

    public function openDetail(int $id): void
    {
        $this->viewingId = $id;
        $this->showDetailModal = true;
    }

    public function closeDetail(): void
    {
        $this->showDetailModal = false;
        $this->viewingId = null;
    }

    public function openSendModal(): void
    {
        $this->reset(['sendTitle', 'sendBody', 'sendTarget', 'sendUserId']);
        $this->showSendModal = true;
    }

    public function closeSendModal(): void
    {
        $this->showSendModal = false;
        $this->resetValidation();
    }

    public function sendNotification(): void
    {
        $this->validate([
            'sendTitle' => ['required', 'string', 'max:255'],
            'sendBody' => ['required', 'string', 'max:2000'],
            'sendTarget' => ['required', 'in:all_residents,owners,tenants,staff,single'],
            'sendUserId' => ['required_if:sendTarget,single', 'nullable', 'exists:users,id'],
        ]);

        $recipients = $this->resolveRecipients();

        if ($recipients->isEmpty()) {
            $this->addError('sendTarget', 'No users found for the selected target.');

            return;
        }

        app(NotificationService::class)->send(
            $recipients,
            NotificationType::AdminNotification,
            $this->sendTitle,
            $this->sendBody,
            ['source' => 'admin_manual'],
        );

        $this->closeSendModal();
        $this->notify(__('Notification sent successfully.'));
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveRecipients(): Collection
    {
        return match ($this->sendTarget) {
            'all_residents' => User::role(['owner', 'tenant'])->get(),
            'owners' => User::role('owner')->get(),
            'tenants' => User::role('tenant')->get(),
            'staff' => User::role(['admin', 'accountant', 'committee'])->get(),
            'single' => $this->sendUserId ? User::where('id', $this->sendUserId)->get() : collect(),
            default => collect(),
        };
    }

    public function render(): View
    {
        $this->authorize('viewAny', Notification::class);

        $query = Notification::with('user')
            ->when($this->search, fn (Builder $q) => $q->where(function (Builder $q): void {
                $q->where('title', 'ilike', "%{$this->search}%")
                    ->orWhere('body', 'ilike', "%{$this->search}%")
                    ->orWhereHas('user', fn (Builder $uq) => $uq->where('name', 'ilike', "%{$this->search}%"));
            }))
            ->when($this->type, fn (Builder $q) => $q->where('type', $this->type))
            ->when($this->status, fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->userId, fn (Builder $q) => $q->where('user_id', $this->userId))
            ->when($this->isRead !== '', fn (Builder $q) => $q->where('is_read', $this->isRead === '1'))
            ->orderByDesc('created_at');

        return view('livewire.admin.notification-list', [
            'notifications' => $query->paginate(20),
            'types' => collect(NotificationType::cases())->map(fn (NotificationType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
            'viewingNotification' => $this->viewingId ? Notification::with(['user', 'logs.userDevice'])->find($this->viewingId) : null,
        ])->layout('components.layouts.app');
    }
}
