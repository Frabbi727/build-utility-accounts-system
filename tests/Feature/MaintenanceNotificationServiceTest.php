<?php

namespace Tests\Feature;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\NotificationTriggerEvent;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Livewire\Admin\MaintenanceRequestList;
use App\Models\Building;
use App\Models\Flat;
use App\Models\MaintenanceRequest;
use App\Models\Notification;
use App\Models\NotificationRule;
use App\Models\Owner;
use App\Models\Staff;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Notification\MaintenanceNotificationService;
use App\Support\CurrentBuilding;
use Database\Seeders\NotificationRuleSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(NotificationRuleSeeder::class);
    }

    public function test_status_change_triggers_notification_with_dynamic_tokens_and_resolution_notes(): void
    {
        $building = Building::factory()->create(['name' => 'Green Tower']);
        $resident = User::factory()->create(['name' => 'John Resident']);
        $resident->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $resident->id, 'name' => 'John Resident']);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id, 'number' => '4B']);

        $ticket = MaintenanceRequest::factory()->create([
            'building_id' => $building->id,
            'flat_id' => $flat->id,
            'user_id' => $resident->id,
            'title' => 'Fix leaking pipe',
            'status' => MaintenanceStatus::Open,
        ]);

        $ticket->status = MaintenanceStatus::Resolved;
        $ticket->resolution_notes = 'Replaced worn rubber seal.';
        $ticket->save();

        $service = app(MaintenanceNotificationService::class);
        $notifications = $service->notifyStatusChanged($ticket, MaintenanceStatus::Open);

        $this->assertCount(1, $notifications);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $resident->id,
            'type' => NotificationType::MaintenanceUpdated->value,
            'reference_type' => 'maintenance_request',
            'reference_id' => $ticket->id,
        ]);

        $saved = Notification::where('user_id', $resident->id)->firstOrFail();
        $this->assertSame("Maintenance Ticket Updated: #{$ticket->id}", $saved->title);
        $this->assertStringContainsString('Fix leaking pipe', $saved->body);
        $this->assertStringContainsString('Resolved', $saved->body);
        $this->assertStringContainsString('Replaced worn rubber seal.', $saved->body);
        $this->assertSame('ticket_updated', $saved->data['type']);
        $this->assertSame('maintenance', $saved->data['screen']);
        $this->assertSame($ticket->id, $saved->data['ticket_id']);
    }

    public function test_staff_and_vendor_assignment_triggers_assignment_notification(): void
    {
        $building = Building::factory()->create(['name' => 'Sunset Apartments']);
        $resident = User::factory()->create(['name' => 'Jane Resident']);
        $resident->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $resident->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id, 'number' => '2A']);

        $staff = Staff::factory()->create(['building_id' => $building->id, 'name' => 'Rahim Technician']);

        $ticket = MaintenanceRequest::factory()->create([
            'building_id' => $building->id,
            'flat_id' => $flat->id,
            'user_id' => $resident->id,
            'title' => 'Electrical spark in fuse box',
            'assigned_staff_id' => $staff->id,
        ]);

        $service = app(MaintenanceNotificationService::class);
        $notifications = $service->notifyAssignment($ticket);

        $this->assertCount(1, $notifications);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $resident->id,
            'type' => NotificationType::MaintenanceAssigned->value,
            'reference_type' => 'maintenance_request',
            'reference_id' => $ticket->id,
        ]);

        $saved = Notification::where('user_id', $resident->id)->firstOrFail();
        $this->assertSame("Technician Assigned: #{$ticket->id}", $saved->title);
        $this->assertStringContainsString('Rahim Technician', $saved->body);
        $this->assertStringContainsString('Electrical spark in fuse box', $saved->body);
        $this->assertSame('ticket_assigned', $saved->data['type']);
        $this->assertSame('maintenance', $saved->data['screen']);
    }

    public function test_resident_ticket_creation_triggers_notification_to_building_staff_and_admin(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->assignRole(Role::Admin->value);

        $committee = User::factory()->create(['name' => 'Committee User']);
        $committee->assignRole(Role::Committee->value);

        $building = Building::factory()->create();
        $resident = User::factory()->create(['name' => 'Resident Sarah']);
        $resident->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $resident->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id, 'number' => '5C']);

        $response = $this->actingAs($resident, 'sanctum')->postJson('/api/v1/resident/maintenance-requests', [
            'flat_id' => $flat->id,
            'title' => 'Elevator button stuck',
            'description' => 'The button for floor 5 does not light up.',
            'category' => MaintenanceCategory::Electrical->value,
            'priority' => MaintenancePriority::High->value,
        ]);

        $response->assertStatus(201);
        $ticketId = $response->json('data.id');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => NotificationType::MaintenanceCreated->value,
            'reference_type' => 'maintenance_request',
            'reference_id' => $ticketId,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $committee->id,
            'type' => NotificationType::MaintenanceCreated->value,
            'reference_type' => 'maintenance_request',
            'reference_id' => $ticketId,
        ]);

        $adminNotification = Notification::where('user_id', $admin->id)->firstOrFail();
        $this->assertSame("New Maintenance Request: #{$ticketId}", $adminNotification->title);
        $this->assertStringContainsString('Elevator button stuck', $adminNotification->body);
        $this->assertStringContainsString('5C', $adminNotification->body);
        $this->assertSame('ticket_created', $adminNotification->data['type']);
        $this->assertSame('maintenance', $adminNotification->data['screen']);
    }

    public function test_admin_livewire_status_change_dispatches_notification(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $building = Building::factory()->create();
        app(CurrentBuilding::class)->set($building->id);

        $resident = User::factory()->create(['name' => 'Tenant Alex']);
        $resident->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $resident->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);

        $ticket = MaintenanceRequest::factory()->create([
            'building_id' => $building->id,
            'flat_id' => $flat->id,
            'user_id' => $resident->id,
            'title' => 'Broken window handle',
            'status' => MaintenanceStatus::Open,
        ]);

        Livewire::actingAs($admin)
            ->test(MaintenanceRequestList::class)
            ->call('edit', $ticket->id)
            ->set('status', MaintenanceStatus::InProgress->value)
            ->set('resolutionNotes', 'Parts ordered.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $resident->id,
            'type' => NotificationType::MaintenanceUpdated->value,
            'reference_type' => 'maintenance_request',
            'reference_id' => $ticket->id,
        ]);

        $notification = Notification::where('user_id', $resident->id)->firstOrFail();
        $this->assertStringContainsString('In Progress', $notification->body);
        $this->assertStringContainsString('Parts ordered.', $notification->body);
    }

    public function test_admin_livewire_assignment_change_dispatches_assignment_notification(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $building = Building::factory()->create();
        app(CurrentBuilding::class)->set($building->id);

        $resident = User::factory()->create();
        $resident->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $resident->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);

        $vendor = Vendor::factory()->create(['name' => 'Fast Plumbing Co.']);

        $ticket = MaintenanceRequest::factory()->create([
            'building_id' => $building->id,
            'flat_id' => $flat->id,
            'user_id' => $resident->id,
            'title' => 'Clogged main drain',
            'status' => MaintenanceStatus::Open,
            'assigned_vendor_id' => null,
        ]);

        Livewire::actingAs($admin)
            ->test(MaintenanceRequestList::class)
            ->call('edit', $ticket->id)
            ->set('assignedVendorId', $vendor->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $resident->id,
            'type' => NotificationType::MaintenanceAssigned->value,
            'reference_type' => 'maintenance_request',
            'reference_id' => $ticket->id,
        ]);

        $notification = Notification::where('user_id', $resident->id)->firstOrFail();
        $this->assertStringContainsString('Fast Plumbing Co.', $notification->body);
    }

    public function test_custom_building_notification_rule_overrides_global_template(): void
    {
        $building = Building::factory()->create(['name' => 'Palm Residence']);
        $resident = User::factory()->create(['name' => 'Michael']);
        $resident->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $resident->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id, 'number' => '302']);

        // Custom building-specific rule
        NotificationRule::create([
            'building_id' => $building->id,
            'trigger_event' => NotificationTriggerEvent::MaintenanceStatusChanged,
            'days_offset' => 0,
            'title_template' => '[{building_name}] Status Update: {ticket_title}',
            'body_template' => 'Flat {flat_number}: Status changed to {ticket_status}. Notes: {resolution_notes}',
            'is_active' => true,
            'channels' => ['push', 'in_app'],
        ]);

        $ticket = MaintenanceRequest::factory()->create([
            'building_id' => $building->id,
            'flat_id' => $flat->id,
            'user_id' => $resident->id,
            'title' => 'AC unit cleaning',
            'status' => MaintenanceStatus::Resolved,
            'resolution_notes' => 'Filters cleaned and freon refilled.',
        ]);

        $service = app(MaintenanceNotificationService::class);
        $service->notifyStatusChanged($ticket, MaintenanceStatus::InProgress);

        $notification = Notification::where('user_id', $resident->id)->firstOrFail();
        $this->assertSame('[Palm Residence] Status Update: AC unit cleaning', $notification->title);
        $this->assertSame('Flat 302: Status changed to Resolved. Notes: Filters cleaned and freon refilled.', $notification->body);
    }

    public function test_inactive_rule_prevents_notification_dispatch(): void
    {
        $building = Building::factory()->create();
        $resident = User::factory()->create();
        $resident->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $resident->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);

        // Disable MaintenanceStatusChanged globally
        NotificationRule::where('trigger_event', NotificationTriggerEvent::MaintenanceStatusChanged)->update(['is_active' => false]);

        $ticket = MaintenanceRequest::factory()->create([
            'building_id' => $building->id,
            'flat_id' => $flat->id,
            'user_id' => $resident->id,
            'status' => MaintenanceStatus::Resolved,
        ]);

        $service = app(MaintenanceNotificationService::class);
        $notifications = $service->notifyStatusChanged($ticket, MaintenanceStatus::Open);

        $this->assertCount(0, $notifications);
        $this->assertDatabaseCount('notifications', 0);
    }
}
