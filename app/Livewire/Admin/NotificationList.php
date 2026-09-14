<?php

namespace App\Livewire\Admin;

use App\Enums\BillStatus;
use App\Enums\NotificationTriggerEvent;
use App\Enums\NotificationType;
use App\Livewire\Concerns\WithNotices;
use App\Models\Notification;
use App\Models\NotificationRule;
use App\Models\ServiceChargeBill;
use App\Models\User;
use App\Services\Notification\NotificationService;
use App\Services\Notification\TemplateParser;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read Collection<int, NotificationRule> $rules
 */
class NotificationList extends Component
{
    use WithNotices, WithPagination;

    #[Url(as: 'tab')]
    public string $activeTab = 'rules';

    public string $search = '';

    public string $type = '';

    public string $status = '';

    public ?int $userId = null;

    public string $isRead = '';

    // Rules Management state
    public bool $showEditRuleModal = false;

    public ?int $editingRuleId = null;

    public string $editingTriggerEvent = '';

    public int $editingDaysOffset = 0;

    public string $editingTitleTemplate = '';

    public string $editingBodyTemplate = '';

    public bool $editingIsActive = true;

    /**
     * @var array<int, string>
     */
    public array $supportedTokens = [];

    // On-demand Reminders state
    public bool $showRemindersConfirmModal = false;

    /**
     * @var array{bills_count: int, notifications_count: int}|null
     */
    public ?array $dryRunSummary = null;

    // Manual send modal
    public bool $showSendModal = false;

    public string $sendTitle = '';

    public string $sendBody = '';

    public string $sendTarget = 'all_residents';

    public ?int $sendUserId = null;

    // Detail modal
    public ?int $viewingId = null;

    public bool $showDetailModal = false;

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['rules', 'history', 'broadcast'], true) ? $tab : 'rules';
        $this->resetPage();
    }

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

    public function toggleRuleActive(int $ruleId): void
    {
        $rule = NotificationRule::findOrFail($ruleId);
        $buildingId = app(CurrentBuilding::class)->id();

        if ($rule->building_id !== null && $rule->building_id !== $buildingId) {
            abort(403);
        }

        if ($buildingId !== null && $rule->building_id === null) {
            $buildingRule = NotificationRule::firstOrNew([
                'building_id' => $buildingId,
                'trigger_event' => $rule->trigger_event,
            ]);
            $buildingRule->days_offset = $rule->days_offset;
            $buildingRule->title_template = $rule->title_template;
            $buildingRule->body_template = $rule->body_template;
            $buildingRule->channels = $rule->channels ?? ['push', 'in_app'];
            $buildingRule->is_active = ! $rule->is_active;
            $buildingRule->save();
        } else {
            $rule->update(['is_active' => ! $rule->is_active]);
        }

        $this->notify(__('Notification rule updated.'));
    }

    public function openEditRuleModal(int $ruleId): void
    {
        $rule = NotificationRule::findOrFail($ruleId);
        $buildingId = app(CurrentBuilding::class)->id();

        if ($rule->building_id !== null && $rule->building_id !== $buildingId) {
            abort(403);
        }

        $this->editingRuleId = $rule->id;

        $event = $rule->trigger_event;

        $this->editingTriggerEvent = $event->value;
        $this->editingDaysOffset = $rule->days_offset;
        $this->editingTitleTemplate = $rule->title_template;
        $this->editingBodyTemplate = $rule->body_template;
        $this->editingIsActive = (bool) $rule->is_active;
        $this->supportedTokens = $event->supportedTokens();
        $this->showEditRuleModal = true;
    }

    public function closeEditRuleModal(): void
    {
        $this->showEditRuleModal = false;
        $this->editingRuleId = null;
        $this->resetValidation();
    }

    public function saveRule(): void
    {
        $this->validate([
            'editingDaysOffset' => ['required', 'integer'],
            'editingTitleTemplate' => ['required', 'string', 'max:255'],
            'editingBodyTemplate' => ['required', 'string', 'max:2000'],
            'editingIsActive' => ['boolean'],
        ]);

        $rule = NotificationRule::findOrFail($this->editingRuleId);
        $buildingId = app(CurrentBuilding::class)->id();

        if ($rule->building_id !== null && $rule->building_id !== $buildingId) {
            abort(403);
        }

        if ($buildingId !== null && $rule->building_id === null) {
            $targetRule = NotificationRule::firstOrNew([
                'building_id' => $buildingId,
                'trigger_event' => $rule->trigger_event,
            ]);
            $targetRule->channels = $rule->channels ?? ['push', 'in_app'];
        } else {
            $targetRule = $rule;
        }

        $targetRule->days_offset = (int) $this->editingDaysOffset;
        $targetRule->title_template = $this->editingTitleTemplate;
        $targetRule->body_template = $this->editingBodyTemplate;
        $targetRule->is_active = (bool) $this->editingIsActive;
        $targetRule->save();

        $this->closeEditRuleModal();
        $this->notify(__('Notification rule updated successfully.'));
    }

    public function getPreviewTitleProperty(): string
    {
        return TemplateParser::render($this->editingTitleTemplate, $this->getMockSampleData());
    }

    public function getPreviewBodyProperty(): string
    {
        return TemplateParser::render($this->editingBodyTemplate, $this->getMockSampleData());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getMockSampleData(): array
    {
        return [
            'resident_name' => 'Rahim Ahmed',
            'flat_number' => '4A',
            'amount' => '2500.00',
            'due_date' => now()->addDays(3),
            'billing_month' => now(),
            'ticket_id' => 101,
            'ticket_title' => 'Water Tap Leakage',
            'ticket_status' => 'In Progress',
            'assigned_to' => 'Karim Ali',
            'resolution_notes' => 'Replaced pipe connector',
            'building_name' => 'Green View Residency',
        ];
    }

    public function previewReminders(): void
    {
        $buildingId = app(CurrentBuilding::class)->id();
        $targetDate = now()->startOfDay();
        $targetDateString = $targetDate->toDateString();

        $billTriggerEvents = [
            NotificationTriggerEvent::BillDueUpcoming,
            NotificationTriggerEvent::BillDueToday,
            NotificationTriggerEvent::BillOverdue,
        ];

        $allRules = NotificationRule::query()
            ->whereIn('trigger_event', $billTriggerEvents)
            ->when($buildingId, function (Builder $q) use ($buildingId) {
                $q->where('building_id', $buildingId)->orWhereNull('building_id');
            }, function (Builder $q) {
                $q->whereNull('building_id');
            })
            ->get();

        $evaluatedBillIds = [];
        $notificationsCount = 0;

        foreach ($billTriggerEvents as $event) {
            $buildingRule = $buildingId ? $allRules->first(fn (NotificationRule $r) => $r->trigger_event === $event && $r->building_id === $buildingId) : null;
            $rule = $buildingRule ?? $allRules->first(fn (NotificationRule $r) => $r->trigger_event === $event && $r->building_id === null);

            if (! $rule || ! $rule->is_active) {
                continue;
            }

            $targetDueDate = $targetDate->copy()->subDays($rule->days_offset)->toDateString();

            $billsQuery = ServiceChargeBill::query()
                ->whereIn('status', [BillStatus::Unpaid, BillStatus::PartiallyPaid])
                ->whereDate('due_date', $targetDueDate)
                ->with(['flat.owner.user', 'flat.tenants.user']);

            if ($buildingId) {
                $billsQuery->whereHas('flat', fn (Builder $q) => $q->where('building_id', $buildingId));
            }

            $bills = $billsQuery->get();

            foreach ($bills as $bill) {
                $evaluatedBillIds[$bill->id] = true;
                $flat = $bill->flat;
                if (! $flat) {
                    continue;
                }

                $recipients = collect();
                if ($flat->owner?->user) {
                    $recipients->push($flat->owner->user);
                }
                foreach ($flat->tenants as $tenant) {
                    if ($tenant->user) {
                        $leaseStarted = $tenant->lease_started_on === null || $tenant->lease_started_on->toDateString() <= $targetDateString;
                        $leaseEnded = $tenant->lease_ended_on === null || $tenant->lease_ended_on->toDateString() >= $targetDateString;
                        if ($leaseStarted && $leaseEnded) {
                            $recipients->push($tenant->user);
                        }
                    }
                }

                $notificationsCount += $recipients->unique('id')->count();
            }
        }

        $this->dryRunSummary = [
            'bills_count' => count($evaluatedBillIds),
            'notifications_count' => $notificationsCount,
        ];
        $this->showRemindersConfirmModal = true;
    }

    public function closeRemindersConfirmModal(): void
    {
        $this->showRemindersConfirmModal = false;
        $this->dryRunSummary = null;
    }

    public function sendRemindersNow(): void
    {
        $buildingId = app(CurrentBuilding::class)->id();
        $params = [];
        if ($buildingId) {
            $params['--building'] = (string) $buildingId;
        }

        Artisan::call('notifications:send-bill-reminders', $params);
        $this->showRemindersConfirmModal = false;
        $this->dryRunSummary = null;
        $this->notify(__('Reminders dispatched successfully.'));
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
        $buildingId = app(CurrentBuilding::class)->id();

        if ($buildingId === null) {
            return match ($this->sendTarget) {
                'all_residents' => User::role(['owner', 'tenant'])->get(),
                'owners' => User::role('owner')->get(),
                'tenants' => User::role('tenant')->get(),
                'staff' => User::role(['admin', 'accountant', 'committee'])->get(),
                'single' => $this->sendUserId ? User::where('id', $this->sendUserId)->get() : collect(),
                default => collect(),
            };
        }

        return match ($this->sendTarget) {
            'all_residents' => User::role(['owner', 'tenant'])
                ->where(function (Builder $q) use ($buildingId) {
                    $q->whereHas('owner.flats', fn (Builder $fq) => $fq->where('building_id', $buildingId))
                        ->orWhereHas('tenant.flat', fn (Builder $fq) => $fq->where('building_id', $buildingId));
                })->get(),
            'owners' => User::role('owner')
                ->whereHas('owner.flats', fn (Builder $fq) => $fq->where('building_id', $buildingId))
                ->get(),
            'tenants' => User::role('tenant')
                ->whereHas('tenant.flat', fn (Builder $fq) => $fq->where('building_id', $buildingId))
                ->get(),
            'staff' => User::role(['admin', 'accountant', 'committee'])->get(),
            'single' => $this->sendUserId ? User::where('id', $this->sendUserId)->get() : collect(),
            default => collect(),
        };
    }

    /**
     * @return Collection<int, NotificationRule>
     */
    public function getRulesProperty(): Collection
    {
        $buildingId = app(CurrentBuilding::class)->id();
        $rules = NotificationRule::query()
            ->when($buildingId, function (Builder $q) use ($buildingId) {
                $q->where('building_id', $buildingId)->orWhereNull('building_id');
            }, function (Builder $q) {
                $q->whereNull('building_id');
            })
            ->get();

        return collect(NotificationTriggerEvent::cases())->map(function (NotificationTriggerEvent $event) use ($rules, $buildingId) {
            if ($buildingId) {
                $buildingRule = $rules->first(fn (NotificationRule $r) => $r->trigger_event === $event && $r->building_id === $buildingId);
                if ($buildingRule) {
                    return $buildingRule;
                }
            }

            $globalRule = $rules->first(fn (NotificationRule $r) => $r->trigger_event === $event && $r->building_id === null);
            if ($globalRule) {
                return $globalRule;
            }

            return NotificationRule::create([
                'building_id' => null,
                'trigger_event' => $event,
                'days_offset' => $event->defaultDaysOffset(),
                'title_template' => $event->label(),
                'body_template' => $event->description(),
                'is_active' => true,
                'channels' => ['push', 'in_app'],
            ]);
        });
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
            'rules' => $this->rules,
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
