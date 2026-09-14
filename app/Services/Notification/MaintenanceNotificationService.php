<?php

namespace App\Services\Notification;

use App\Enums\MaintenanceStatus;
use App\Enums\NotificationTriggerEvent;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Models\MaintenanceRequest;
use App\Models\Notification;
use App\Models\NotificationRule;
use App\Models\User;
use Illuminate\Support\Collection;

class MaintenanceNotificationService
{
    public function __construct(
        protected NotificationService $notificationService,
        protected TemplateParser $templateParser,
    ) {}

    /**
     * Notify resident when ticket status or resolution notes change.
     *
     * @return Collection<int, Notification>
     */
    public function notifyStatusChanged(MaintenanceRequest $ticket, ?MaintenanceStatus $previousStatus = null): Collection
    {
        $ticket->loadMissing(['user', 'flat.building', 'assignedStaff', 'assignedVendor', 'building']);

        if (! $ticket->user) {
            return collect();
        }

        $rule = $this->resolveRule(NotificationTriggerEvent::MaintenanceStatusChanged, $ticket->building_id);

        if ($rule === false) {
            // Rule is explicitly disabled
            return collect();
        }

        $tokens = $this->buildTokenData($ticket);

        $titleTemplate = $rule !== null ? $rule->title_template : 'Maintenance Ticket Updated: #{ticket_id}';
        $bodyTemplate = $rule !== null ? $rule->body_template : 'Your ticket "{ticket_title}" status changed to {ticket_status}. {resolution_notes}';

        $title = $this->templateParser->parse($titleTemplate, $tokens);
        $body = $this->templateParser->parse($bodyTemplate, $tokens);

        $data = [
            'type' => 'ticket_updated',
            'ticket_id' => $ticket->id,
            'status' => $ticket->status->value,
            'screen' => NotificationType::MaintenanceUpdated->screen(),
        ];

        return $this->notificationService->send(
            $ticket->user,
            NotificationType::MaintenanceUpdated,
            $title,
            $body,
            $data,
            'maintenance_request',
            $ticket->id,
        );
    }

    /**
     * Notify resident and assigned staff when technician/vendor/staff is assigned.
     *
     * @return Collection<int, Notification>
     */
    public function notifyAssignment(
        MaintenanceRequest $ticket,
        ?int $previousStaffId = null,
        ?int $previousVendorId = null,
    ): Collection {
        $ticket->loadMissing(['user', 'flat.building', 'assignedStaff', 'assignedVendor', 'building']);

        $rule = $this->resolveRule(NotificationTriggerEvent::MaintenanceAssigned, $ticket->building_id);

        if ($rule === false) {
            // Rule is explicitly disabled
            return collect();
        }

        $tokens = $this->buildTokenData($ticket);
        if ($tokens['assigned_to'] === '') {
            $tokens['assigned_to'] = 'Technician';
        }

        $titleTemplate = $rule !== null ? $rule->title_template : 'Technician Assigned: #{ticket_id}';
        $bodyTemplate = $rule !== null ? $rule->body_template : 'Technician {assigned_to} has been assigned to your ticket "{ticket_title}".';

        $title = $this->templateParser->parse($titleTemplate, $tokens);
        $body = $this->templateParser->parse($bodyTemplate, $tokens);

        $data = [
            'type' => 'ticket_assigned',
            'ticket_id' => $ticket->id,
            'screen' => NotificationType::MaintenanceAssigned->screen(),
        ];

        /** @var Collection<int, Notification> $notifications */
        $notifications = collect();

        if ($ticket->user) {
            $sent = $this->notificationService->send(
                $ticket->user,
                NotificationType::MaintenanceAssigned,
                $title,
                $body,
                $data,
                'maintenance_request',
                $ticket->id,
            );
            $notifications = $notifications->merge($sent);
        }

        return $notifications;
    }

    /**
     * Notify building staff/admins when a resident creates a new maintenance ticket.
     *
     * @return Collection<int, Notification>
     */
    public function notifyTicketCreated(MaintenanceRequest $ticket): Collection
    {
        $ticket->loadMissing(['user', 'flat.building', 'assignedStaff', 'assignedVendor', 'building']);

        $staffRoles = array_unique(array_merge(Role::staff(), ['admin', 'staff']));
        $recipients = User::whereHas('roles', fn ($q) => $q->whereIn('name', $staffRoles))->get();

        if ($recipients->isEmpty()) {
            return collect();
        }

        $rule = $this->resolveRule(NotificationTriggerEvent::MaintenanceCreated, $ticket->building_id);

        if ($rule === false) {
            // Rule is explicitly disabled
            return collect();
        }

        $tokens = $this->buildTokenData($ticket);

        $titleTemplate = $rule !== null ? $rule->title_template : 'New Maintenance Request: #{ticket_id}';
        $bodyTemplate = $rule !== null ? $rule->body_template : 'A new maintenance request "{ticket_title}" has been submitted for Flat {flat_number}.';

        $title = $this->templateParser->parse($titleTemplate, $tokens);
        $body = $this->templateParser->parse($bodyTemplate, $tokens);

        $data = [
            'type' => 'ticket_created',
            'ticket_id' => $ticket->id,
            'screen' => NotificationType::MaintenanceCreated->screen(),
        ];

        return $this->notificationService->send(
            $recipients,
            NotificationType::MaintenanceCreated,
            $title,
            $body,
            $data,
            'maintenance_request',
            $ticket->id,
        );
    }

    /**
     * Resolve active NotificationRule with building-specific override and global fallback.
     *
     * Returns:
     * - NotificationRule: If an active rule is found.
     * - false: If a rule was found for building or globally but is disabled (is_active = false).
     * - null: If no rule exists in the database (use code fallback).
     */
    protected function resolveRule(NotificationTriggerEvent $event, ?int $buildingId): NotificationRule|false|null
    {
        $rules = NotificationRule::query()
            ->where('trigger_event', $event)
            ->where(function ($q) use ($buildingId): void {
                if ($buildingId !== null) {
                    $q->where('building_id', $buildingId)->orWhereNull('building_id');
                } else {
                    $q->whereNull('building_id');
                }
            })
            ->get();

        if ($rules->isEmpty()) {
            return null;
        }

        // Check building-specific rule first
        if ($buildingId !== null) {
            $buildingRule = $rules->firstWhere('building_id', $buildingId);
            if ($buildingRule !== null) {
                return $buildingRule->is_active ? $buildingRule : false;
            }
        }

        // Check global rule
        $globalRule = $rules->firstWhere('building_id', null);
        if ($globalRule !== null) {
            return $globalRule->is_active ? $globalRule : false;
        }

        return null;
    }

    /**
     * Build token dictionary for template interpolation.
     *
     * @return array<string, mixed>
     */
    protected function buildTokenData(MaintenanceRequest $ticket): array
    {
        $assignedTo = $ticket->assignedStaff !== null
            ? $ticket->assignedStaff->name
            : ($ticket->assignedVendor !== null ? $ticket->assignedVendor->name : '');

        $buildingName = $ticket->building !== null
            ? $ticket->building->name
            : ($ticket->flat !== null && $ticket->flat->building !== null ? $ticket->flat->building->name : '');

        return [
            'resident_name' => $ticket->user !== null ? $ticket->user->name : 'Resident',
            'flat_number' => $ticket->flat !== null ? $ticket->flat->number : '',
            'ticket_id' => (string) $ticket->id,
            'ticket_title' => $ticket->title,
            'ticket_status' => $ticket->status->label(),
            'assigned_to' => $assignedTo,
            'resolution_notes' => $ticket->resolution_notes ?? '',
            'building_name' => $buildingName,
        ];
    }
}
