<?php

namespace Tests\Feature;

use App\Enums\BillStatus;
use App\Enums\NotificationTriggerEvent;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Jobs\SendPushNotificationJob;
use App\Livewire\Admin\NotificationList;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Notification;
use App\Models\NotificationRule;
use App\Models\Owner;
use App\Models\ServiceChargeBill;
use App\Models\User;
use App\Support\CurrentBuilding;
use Database\Seeders\NotificationRuleSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AdminNotificationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Building $building;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(NotificationRuleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Admin->value);

        $this->building = Building::factory()->create(['name' => 'Rose Villa']);
        app(CurrentBuilding::class)->set($this->building->id);
    }

    public function test_can_switch_tabs(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(NotificationList::class)
            ->assertSet('activeTab', 'rules')
            ->call('setTab', 'history')
            ->assertSet('activeTab', 'history')
            ->call('setTab', 'broadcast')
            ->assertSet('activeTab', 'broadcast')
            ->call('setTab', 'rules')
            ->assertSet('activeTab', 'rules');
    }

    public function test_can_toggle_rule_active(): void
    {
        $this->actingAs($this->admin);

        $rule = NotificationRule::where('trigger_event', NotificationTriggerEvent::BillDueToday)->firstOrFail();
        $this->assertTrue($rule->is_active);

        // Toggling in building context creates a building override or toggles
        Livewire::test(NotificationList::class)
            ->call('toggleRuleActive', $rule->id);

        $buildingRule = NotificationRule::where('building_id', $this->building->id)
            ->where('trigger_event', NotificationTriggerEvent::BillDueToday)
            ->first();

        $this->assertNotNull($buildingRule);
        $this->assertFalse($buildingRule->is_active);

        // Toggling again activates the building rule
        Livewire::test(NotificationList::class)
            ->call('toggleRuleActive', $buildingRule->id);

        $this->assertTrue($buildingRule->fresh()->is_active);
    }

    public function test_open_edit_rule_modal_and_save_with_validation(): void
    {
        $this->actingAs($this->admin);

        $rule = NotificationRule::where('trigger_event', NotificationTriggerEvent::BillDueUpcoming)->firstOrFail();

        $test = Livewire::test(NotificationList::class)
            ->call('openEditRuleModal', $rule->id)
            ->assertSet('showEditRuleModal', true)
            ->assertSet('editingRuleId', $rule->id)
            ->assertSet('editingTriggerEvent', NotificationTriggerEvent::BillDueUpcoming->value)
            ->assertSet('editingDaysOffset', $rule->days_offset)
            ->assertSet('editingTitleTemplate', $rule->title_template)
            ->assertSet('editingBodyTemplate', $rule->body_template);

        $this->assertCount(count(NotificationTriggerEvent::BillDueUpcoming->supportedTokens()), $test->get('supportedTokens'));

        // Validation failure
        $test->set('editingTitleTemplate', '')
            ->set('editingBodyTemplate', '')
            ->call('saveRule')
            ->assertHasErrors(['editingTitleTemplate', 'editingBodyTemplate']);

        // Successful save (creates building override since editing global rule while building is active)
        $test->set('editingDaysOffset', -5)
            ->set('editingTitleTemplate', 'Custom Reminder: {flat_number}')
            ->set('editingBodyTemplate', 'Dear {resident_name}, please pay {amount} by {due_date}.')
            ->set('editingIsActive', true)
            ->call('saveRule')
            ->assertHasNoErrors()
            ->assertSet('showEditRuleModal', false);

        $this->assertDatabaseHas('notification_rules', [
            'building_id' => $this->building->id,
            'trigger_event' => NotificationTriggerEvent::BillDueUpcoming->value,
            'days_offset' => -5,
            'title_template' => 'Custom Reminder: {flat_number}',
            'body_template' => 'Dear {resident_name}, please pay {amount} by {due_date}.',
            'is_active' => true,
        ]);
    }

    public function test_live_preview_renders_tokens(): void
    {
        $this->actingAs($this->admin);

        $rule = NotificationRule::where('trigger_event', NotificationTriggerEvent::BillDueToday)->firstOrFail();

        $component = Livewire::test(NotificationList::class)
            ->call('openEditRuleModal', $rule->id)
            ->set('editingTitleTemplate', 'Urgent: {flat_number} - {resident_name}')
            ->set('editingBodyTemplate', 'Amount due: {amount} for {building_name}');

        $previewTitle = $component->get('previewTitle');
        $previewBody = $component->get('previewBody');

        $this->assertStringContainsString('4A', $previewTitle);
        $this->assertStringContainsString('Rahim Ahmed', $previewTitle);
        $this->assertStringContainsString('2,500.00', $previewBody);
        $this->assertStringContainsString('Green View Residency', $previewBody);
    }

    public function test_preview_reminders_evaluates_eligible_bills(): void
    {
        $this->actingAs($this->admin);

        // Create an owner and bill due today in this building
        $ownerUser = User::factory()->create(['name' => 'Karim Resident']);
        $ownerUser->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $ownerUser->id]);
        $flat = Flat::factory()->create(['building_id' => $this->building->id, 'owner_id' => $owner->id, 'number' => '2B']);

        $bill = ServiceChargeBill::factory()->create([
            'flat_id' => $flat->id,
            'status' => BillStatus::Unpaid,
            'due_date' => now()->toDateString(),
            'total_amount' => 3000,
        ]);

        Livewire::test(NotificationList::class)
            ->call('previewReminders')
            ->assertSet('showRemindersConfirmModal', true)
            ->assertSet('dryRunSummary.bills_count', 1)
            ->assertSet('dryRunSummary.notifications_count', 1);
    }

    public function test_send_reminders_now_triggers_artisan_command(): void
    {
        Artisan::spy();
        $this->actingAs($this->admin);

        Livewire::test(NotificationList::class)
            ->call('sendRemindersNow')
            ->assertSet('showRemindersConfirmModal', false);

        Artisan::shouldHaveReceived('call')
            ->with('notifications:send-bill-reminders', ['--building' => (string) $this->building->id]);
    }

    public function test_manual_broadcast_from_broadcast_tab(): void
    {
        Queue::fake();
        $this->actingAs($this->admin);

        $resident = User::factory()->create();
        $resident->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $resident->id]);
        Flat::factory()->create(['building_id' => $this->building->id, 'owner_id' => $owner->id]);

        Livewire::test(NotificationList::class)
            ->call('setTab', 'broadcast')
            ->set('sendTarget', 'owners')
            ->set('sendTitle', 'Notice of Power Outage')
            ->set('sendBody', 'Power will be interrupted for maintenance from 2 PM to 4 PM.')
            ->call('sendNotification')
            ->assertHasNoErrors()
            ->assertSet('showBroadcastConfirmModal', true)
            ->assertSet('broadcastSummary.recipients_count', 1)
            ->call('confirmSendBroadcast')
            ->assertSet('showBroadcastConfirmModal', false);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $resident->id,
            'title' => 'Notice of Power Outage',
            'type' => NotificationType::AdminNotification->value,
        ]);

        Queue::assertPushed(SendPushNotificationJob::class);
    }

    public function test_viewing_notification_logs_and_filters_in_history_tab(): void
    {
        $this->actingAs($this->admin);

        $n1 = Notification::factory()->create([
            'user_id' => $this->admin->id,
            'title' => 'First Notification Log',
            'type' => NotificationType::BillDueReminder->value,
            'status' => 'sent',
        ]);

        Livewire::test(NotificationList::class)
            ->call('setTab', 'history')
            ->assertSee('First Notification Log')
            ->call('openDetail', $n1->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('First Notification Log')
            ->call('closeDetail')
            ->assertSet('showDetailModal', false);
    }
}
