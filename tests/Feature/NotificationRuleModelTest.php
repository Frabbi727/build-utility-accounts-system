<?php

namespace Tests\Feature;

use App\Enums\NotificationTriggerEvent;
use App\Enums\NotificationType;
use App\Models\Building;
use App\Models\NotificationRule;
use Database\Seeders\NotificationRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationRuleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_notification_rules_with_factory(): void
    {
        $building = Building::factory()->create();

        $globalRule = NotificationRule::factory()->create([
            'building_id' => null,
            'trigger_event' => NotificationTriggerEvent::BillDueUpcoming,
            'days_offset' => -3,
            'title_template' => 'Payment Reminder: Bill for Flat {flat_number}',
            'body_template' => 'Hello {resident_name}, bill due on {due_date}.',
            'is_active' => true,
            'channels' => ['push', 'in_app'],
        ]);

        $this->assertDatabaseHas('notification_rules', [
            'id' => $globalRule->id,
            'building_id' => null,
            'trigger_event' => 'bill_due_upcoming',
            'days_offset' => -3,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(NotificationTriggerEvent::class, $globalRule->trigger_event);
        $this->assertSame(NotificationTriggerEvent::BillDueUpcoming, $globalRule->trigger_event);
        $this->assertIsInt($globalRule->days_offset);
        $this->assertIsBool($globalRule->is_active);
        $this->assertIsArray($globalRule->channels);
        $this->assertNull($globalRule->building);

        $buildingRule = NotificationRule::factory()->forBuilding($building)->create([
            'trigger_event' => NotificationTriggerEvent::MaintenanceStatusChanged,
            'days_offset' => 0,
        ]);

        $this->assertNotNull($buildingRule->building);
        $this->assertSame($building->id, $buildingRule->building->id);
    }

    public function test_active_and_building_scopes_filter_correctly(): void
    {
        $buildingA = Building::factory()->create();
        $buildingB = Building::factory()->create();

        $activeGlobal = NotificationRule::factory()->create([
            'building_id' => null,
            'is_active' => true,
            'trigger_event' => NotificationTriggerEvent::BillDueUpcoming,
        ]);

        $inactiveGlobal = NotificationRule::factory()->inactive()->create([
            'building_id' => null,
            'trigger_event' => NotificationTriggerEvent::BillDueToday,
        ]);

        $activeBuildingA = NotificationRule::factory()->forBuilding($buildingA)->create([
            'is_active' => true,
            'trigger_event' => NotificationTriggerEvent::BillOverdue,
        ]);

        $activeBuildingB = NotificationRule::factory()->forBuilding($buildingB)->create([
            'is_active' => true,
            'trigger_event' => NotificationTriggerEvent::MaintenanceAssigned,
        ]);

        // Active scope
        $activeRules = NotificationRule::query()->active()->get();
        $this->assertTrue($activeRules->contains('id', $activeGlobal->id));
        $this->assertTrue($activeRules->contains('id', $activeBuildingA->id));
        $this->assertTrue($activeRules->contains('id', $activeBuildingB->id));
        $this->assertFalse($activeRules->contains('id', $inactiveGlobal->id));

        // For building null scope (only global rules)
        $globalRules = NotificationRule::query()->forBuilding(null)->get();
        $this->assertTrue($globalRules->contains('id', $activeGlobal->id));
        $this->assertTrue($globalRules->contains('id', $inactiveGlobal->id));
        $this->assertFalse($globalRules->contains('id', $activeBuildingA->id));
        $this->assertFalse($globalRules->contains('id', $activeBuildingB->id));

        // For building A scope (Building A rules + Global rules fallback)
        $rulesForA = NotificationRule::query()->forBuilding($buildingA->id)->get();
        $this->assertTrue($rulesForA->contains('id', $activeGlobal->id));
        $this->assertTrue($rulesForA->contains('id', $activeBuildingA->id));
        $this->assertFalse($rulesForA->contains('id', $activeBuildingB->id));
    }

    public function test_notification_trigger_event_enum_helpers(): void
    {
        $cases = NotificationTriggerEvent::cases();
        $this->assertCount(6, $cases);

        $this->assertSame('bill_due_upcoming', NotificationTriggerEvent::BillDueUpcoming->value);
        $this->assertSame('bill_due_today', NotificationTriggerEvent::BillDueToday->value);
        $this->assertSame('bill_overdue', NotificationTriggerEvent::BillOverdue->value);
        $this->assertSame('maintenance_status_changed', NotificationTriggerEvent::MaintenanceStatusChanged->value);
        $this->assertSame('maintenance_assigned', NotificationTriggerEvent::MaintenanceAssigned->value);
        $this->assertSame('maintenance_created', NotificationTriggerEvent::MaintenanceCreated->value);

        $this->assertSame('Bill Due Upcoming', NotificationTriggerEvent::BillDueUpcoming->label());
        $this->assertNotEmpty(NotificationTriggerEvent::BillDueUpcoming->description());
        $this->assertContains('{resident_name}', NotificationTriggerEvent::BillDueUpcoming->supportedTokens());
        $this->assertContains('{due_date}', NotificationTriggerEvent::BillDueUpcoming->supportedTokens());

        $this->assertContains('{ticket_id}', NotificationTriggerEvent::MaintenanceStatusChanged->supportedTokens());
        $this->assertContains('{ticket_status}', NotificationTriggerEvent::MaintenanceStatusChanged->supportedTokens());

        $this->assertTrue(NotificationTriggerEvent::BillDueUpcoming->isBillEvent());
        $this->assertFalse(NotificationTriggerEvent::BillDueUpcoming->isMaintenanceEvent());
        $this->assertTrue(NotificationTriggerEvent::MaintenanceStatusChanged->isMaintenanceEvent());
        $this->assertFalse(NotificationTriggerEvent::MaintenanceStatusChanged->isBillEvent());
    }

    public function test_notification_type_enum_has_new_cases_and_screens(): void
    {
        $this->assertSame('BILL_DUE_REMINDER', NotificationType::BillDueReminder->value);
        $this->assertSame('BILL_OVERDUE', NotificationType::BillOverdue->value);
        $this->assertSame('MAINTENANCE_ASSIGNED', NotificationType::MaintenanceAssigned->value);
        $this->assertSame('MAINTENANCE_CREATED', NotificationType::MaintenanceCreated->value);

        $this->assertSame('bills', NotificationType::BillDueReminder->screen());
        $this->assertSame('bills', NotificationType::BillOverdue->screen());
        $this->assertSame('maintenance', NotificationType::MaintenanceAssigned->screen());
        $this->assertSame('maintenance', NotificationType::MaintenanceCreated->screen());
    }

    public function test_notification_rule_seeder_seeds_default_rules(): void
    {
        $this->seed(NotificationRuleSeeder::class);

        $this->assertDatabaseCount('notification_rules', 5);

        $this->assertDatabaseHas('notification_rules', [
            'building_id' => null,
            'trigger_event' => 'bill_due_upcoming',
            'days_offset' => -3,
            'title_template' => 'Payment Reminder: Bill for Flat {flat_number}',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('notification_rules', [
            'building_id' => null,
            'trigger_event' => 'bill_due_today',
            'days_offset' => 0,
            'title_template' => 'Bill Due Today: Flat {flat_number}',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('notification_rules', [
            'building_id' => null,
            'trigger_event' => 'bill_overdue',
            'days_offset' => 2,
            'title_template' => 'Overdue Payment Notice: Flat {flat_number}',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('notification_rules', [
            'building_id' => null,
            'trigger_event' => 'maintenance_status_changed',
            'days_offset' => 0,
            'title_template' => 'Maintenance Ticket Updated: #{ticket_id}',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('notification_rules', [
            'building_id' => null,
            'trigger_event' => 'maintenance_assigned',
            'days_offset' => 0,
            'title_template' => 'Technician Assigned: #{ticket_id}',
            'is_active' => true,
        ]);

        // Verify idempotency: running seeder again does not create duplicates
        $this->seed(NotificationRuleSeeder::class);
        $this->assertDatabaseCount('notification_rules', 5);
    }
}
