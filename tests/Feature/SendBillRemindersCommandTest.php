<?php

namespace Tests\Feature;

use App\Enums\BillStatus;
use App\Enums\LateFeeType;
use App\Enums\NotificationTriggerEvent;
use App\Enums\NotificationType;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Notification;
use App\Models\NotificationRule;
use App\Models\Owner;
use App\Models\ServiceChargeBill;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\NotificationRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SendBillRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_upcoming_due_reminders(): void
    {
        $this->seed(NotificationRuleSeeder::class);

        $today = Carbon::parse('2026-09-10');
        $dueDate = Carbon::parse('2026-09-13'); // 3 days ahead (-3 days offset)

        $building = Building::factory()->create(['name' => 'Rose Villa']);
        $ownerUser = User::factory()->create(['name' => 'Alice Owner']);
        $owner = Owner::factory()->create(['user_id' => $ownerUser->id, 'name' => 'Alice Owner']);

        $tenantUser = User::factory()->create(['name' => 'Bob Tenant']);

        $flat = Flat::factory()->create([
            'building_id' => $building->id,
            'owner_id' => $owner->id,
            'number' => '401',
        ]);

        Tenant::factory()->create([
            'flat_id' => $flat->id,
            'user_id' => $tenantUser->id,
            'name' => 'Bob Tenant',
            'lease_started_on' => '2026-01-01',
            'lease_ended_on' => '2026-12-31',
        ]);

        $bill = ServiceChargeBill::factory()->create([
            'flat_id' => $flat->id,
            'bill_no' => 'BILL-2026-09-401',
            'billing_month' => '2026-09-01',
            'due_date' => $dueDate,
            'total_amount' => '3500.00',
            'status' => BillStatus::Unpaid,
        ]);

        $this->artisan('notifications:send-bill-reminders', [
            '--date' => $today->toDateString(),
        ])->assertSuccessful();

        $this->assertDatabaseCount('notifications', 2);

        $rule = NotificationRule::where('trigger_event', NotificationTriggerEvent::BillDueUpcoming)->firstOrFail();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $ownerUser->id,
            'type' => NotificationType::BillDueReminder->value,
            'title' => 'Payment Reminder: Bill for Flat 401',
            'reference_type' => 'service_charge_bill',
            'reference_id' => $bill->id,
            'notification_key' => "bill_reminder:{$bill->id}:{$rule->id}:2026-09-10:{$ownerUser->id}",
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $tenantUser->id,
            'type' => NotificationType::BillDueReminder->value,
            'title' => 'Payment Reminder: Bill for Flat 401',
            'reference_type' => 'service_charge_bill',
            'reference_id' => $bill->id,
            'notification_key' => "bill_reminder:{$bill->id}:{$rule->id}:2026-09-10:{$tenantUser->id}",
        ]);
    }

    public function test_it_sends_due_today_reminders(): void
    {
        $this->seed(NotificationRuleSeeder::class);

        $today = Carbon::parse('2026-09-10');

        $building = Building::factory()->create();
        $user = User::factory()->create(['name' => 'Charlie Resident']);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create([
            'building_id' => $building->id,
            'owner_id' => $owner->id,
            'number' => '502',
        ]);

        $bill = ServiceChargeBill::factory()->create([
            'flat_id' => $flat->id,
            'billing_month' => '2026-09-01',
            'due_date' => $today,
            'total_amount' => '4000.00',
            'status' => BillStatus::Unpaid,
        ]);

        $this->artisan('notifications:send-bill-reminders', [
            '--date' => $today->toDateString(),
        ])->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => NotificationType::BillDueReminder->value,
            'title' => 'Bill Due Today: Flat 502',
            'reference_type' => 'service_charge_bill',
            'reference_id' => $bill->id,
        ]);
    }

    public function test_it_sends_overdue_reminders(): void
    {
        $this->seed(NotificationRuleSeeder::class);

        $today = Carbon::parse('2026-09-10');
        $dueDate = Carbon::parse('2026-09-08'); // 2 days overdue (+2 days offset)

        $building = Building::factory()->create();
        $user = User::factory()->create(['name' => 'Overdue Resident']);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create([
            'building_id' => $building->id,
            'owner_id' => $owner->id,
            'number' => '101',
        ]);

        $bill = ServiceChargeBill::factory()->create([
            'flat_id' => $flat->id,
            'billing_month' => '2026-09-01',
            'due_date' => $dueDate,
            'total_amount' => '5000.00',
            'status' => BillStatus::Unpaid,
        ]);

        $this->artisan('notifications:send-bill-reminders', [
            '--date' => $today->toDateString(),
        ])->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => NotificationType::BillOverdue->value,
            'title' => 'Overdue Payment Notice: Flat 101',
            'reference_type' => 'service_charge_bill',
            'reference_id' => $bill->id,
        ]);
    }

    public function test_it_excludes_paid_bills_and_includes_partially_paid_bills(): void
    {
        $this->seed(NotificationRuleSeeder::class);

        $today = Carbon::parse('2026-09-10');
        $dueDate = Carbon::parse('2026-09-13'); // -3 days offset

        $building = Building::factory()->create();

        // Paid bill
        $userPaid = User::factory()->create();
        $ownerPaid = Owner::factory()->create(['user_id' => $userPaid->id]);
        $flatPaid = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $ownerPaid->id, 'number' => '201']);
        ServiceChargeBill::factory()->create([
            'flat_id' => $flatPaid->id,
            'due_date' => $dueDate,
            'status' => BillStatus::Paid,
        ]);

        // Partially Paid bill
        $userPartial = User::factory()->create();
        $ownerPartial = Owner::factory()->create(['user_id' => $userPartial->id]);
        $flatPartial = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $ownerPartial->id, 'number' => '202']);
        $partialBill = ServiceChargeBill::factory()->create([
            'flat_id' => $flatPartial->id,
            'due_date' => $dueDate,
            'status' => BillStatus::PartiallyPaid,
        ]);

        $this->artisan('notifications:send-bill-reminders', [
            '--date' => $today->toDateString(),
        ])->assertSuccessful();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $userPartial->id,
            'reference_id' => $partialBill->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $userPaid->id,
        ]);
    }

    public function test_dry_run_mode_does_not_create_notifications(): void
    {
        $this->seed(NotificationRuleSeeder::class);

        $today = Carbon::parse('2026-09-10');
        $dueDate = Carbon::parse('2026-09-13');

        $building = Building::factory()->create();
        $user = User::factory()->create();
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);
        ServiceChargeBill::factory()->create([
            'flat_id' => $flat->id,
            'due_date' => $dueDate,
            'status' => BillStatus::Unpaid,
        ]);

        $this->artisan('notifications:send-bill-reminders', [
            '--date' => $today->toDateString(),
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('[DRY RUN]')
            ->assertSuccessful();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_it_is_idempotent_when_run_twice_on_same_day(): void
    {
        $this->seed(NotificationRuleSeeder::class);

        $today = Carbon::parse('2026-09-10');
        $dueDate = Carbon::parse('2026-09-13');

        $building = Building::factory()->create();
        $user = User::factory()->create();
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);
        $bill = ServiceChargeBill::factory()->create([
            'flat_id' => $flat->id,
            'due_date' => $dueDate,
            'status' => BillStatus::Unpaid,
        ]);

        // First run
        $this->artisan('notifications:send-bill-reminders', [
            '--date' => $today->toDateString(),
        ])->assertSuccessful();

        $this->assertDatabaseCount('notifications', 1);

        // Second run on same day
        $this->artisan('notifications:send-bill-reminders', [
            '--date' => $today->toDateString(),
        ])->assertSuccessful();

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_it_filters_by_building_option(): void
    {
        $this->seed(NotificationRuleSeeder::class);

        $today = Carbon::parse('2026-09-10');
        $dueDate = Carbon::parse('2026-09-13');

        $buildingA = Building::factory()->create(['name' => 'Tower A']);
        $buildingB = Building::factory()->create(['name' => 'Tower B']);

        $userA = User::factory()->create();
        $ownerA = Owner::factory()->create(['user_id' => $userA->id]);
        $flatA = Flat::factory()->create(['building_id' => $buildingA->id, 'owner_id' => $ownerA->id]);
        $billA = ServiceChargeBill::factory()->create([
            'flat_id' => $flatA->id,
            'due_date' => $dueDate,
            'status' => BillStatus::Unpaid,
        ]);

        $userB = User::factory()->create();
        $ownerB = Owner::factory()->create(['user_id' => $userB->id]);
        $flatB = Flat::factory()->create(['building_id' => $buildingB->id, 'owner_id' => $ownerB->id]);
        ServiceChargeBill::factory()->create([
            'flat_id' => $flatB->id,
            'due_date' => $dueDate,
            'status' => BillStatus::Unpaid,
        ]);

        // Run filtering only for building A
        $this->artisan('notifications:send-bill-reminders', [
            '--date' => $today->toDateString(),
            '--building' => $buildingA->id,
        ])->assertSuccessful();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $userA->id,
            'reference_id' => $billA->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $userB->id,
        ]);
    }

    public function test_building_specific_rule_overrides_global_rule(): void
    {
        $today = Carbon::parse('2026-09-10');

        $buildingA = Building::factory()->create(['name' => 'Tower A']);
        $buildingB = Building::factory()->create(['name' => 'Tower B']);

        // Global rule: 3 days before due date (offset -3)
        NotificationRule::factory()->create([
            'building_id' => null,
            'trigger_event' => NotificationTriggerEvent::BillDueUpcoming,
            'days_offset' => -3,
            'title_template' => 'Global Reminder: Flat {flat_number}',
            'body_template' => 'Global body {resident_name}',
            'is_active' => true,
        ]);

        // Building A specific override: 5 days before due date (offset -5)
        NotificationRule::factory()->create([
            'building_id' => $buildingA->id,
            'trigger_event' => NotificationTriggerEvent::BillDueUpcoming,
            'days_offset' => -5,
            'title_template' => 'Tower A Custom: Flat {flat_number}',
            'body_template' => 'Tower A body {resident_name}',
            'is_active' => true,
        ]);

        // Bill in Building A due in 5 days (2026-09-15) -> Should match Building A rule!
        $userA1 = User::factory()->create();
        $ownerA1 = Owner::factory()->create(['user_id' => $userA1->id]);
        $flatA1 = Flat::factory()->create(['building_id' => $buildingA->id, 'owner_id' => $ownerA1->id, 'number' => 'A-101']);
        ServiceChargeBill::factory()->create([
            'flat_id' => $flatA1->id,
            'due_date' => '2026-09-15',
            'status' => BillStatus::Unpaid,
        ]);

        // Bill in Building A due in 3 days (2026-09-13) -> Should NOT match (override replaced global)
        $userA2 = User::factory()->create();
        $ownerA2 = Owner::factory()->create(['user_id' => $userA2->id]);
        $flatA2 = Flat::factory()->create(['building_id' => $buildingA->id, 'owner_id' => $ownerA2->id, 'number' => 'A-102']);
        ServiceChargeBill::factory()->create([
            'flat_id' => $flatA2->id,
            'due_date' => '2026-09-13',
            'status' => BillStatus::Unpaid,
        ]);

        // Bill in Building B due in 3 days (2026-09-13) -> Should match Global rule!
        $userB = User::factory()->create();
        $ownerB = Owner::factory()->create(['user_id' => $userB->id]);
        $flatB = Flat::factory()->create(['building_id' => $buildingB->id, 'owner_id' => $ownerB->id, 'number' => 'B-201']);
        ServiceChargeBill::factory()->create([
            'flat_id' => $flatB->id,
            'due_date' => '2026-09-13',
            'status' => BillStatus::Unpaid,
        ]);

        $this->artisan('notifications:send-bill-reminders', [
            '--date' => $today->toDateString(),
        ])->assertSuccessful();

        $this->assertDatabaseCount('notifications', 2);

        // Building A custom notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $userA1->id,
            'title' => 'Tower A Custom: Flat A-101',
        ]);

        // Building A 3-day bill did NOT receive notification
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $userA2->id,
        ]);

        // Building B received global notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $userB->id,
            'title' => 'Global Reminder: Flat B-201',
        ]);
    }

    public function test_inactive_rules_and_expired_tenants_are_ignored(): void
    {
        $today = Carbon::parse('2026-09-10');

        $building = Building::factory()->create();

        // Inactive rule
        NotificationRule::factory()->create([
            'building_id' => null,
            'trigger_event' => NotificationTriggerEvent::BillDueUpcoming,
            'days_offset' => -3,
            'is_active' => false,
        ]);

        $user = User::factory()->create();
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);

        // Expired tenant
        $expiredTenantUser = User::factory()->create();
        Tenant::factory()->create([
            'flat_id' => $flat->id,
            'user_id' => $expiredTenantUser->id,
            'lease_started_on' => '2025-01-01',
            'lease_ended_on' => '2026-09-01', // Ended before 2026-09-10
        ]);

        ServiceChargeBill::factory()->create([
            'flat_id' => $flat->id,
            'due_date' => '2026-09-13',
            'status' => BillStatus::Unpaid,
        ]);

        $this->artisan('notifications:send-bill-reminders', [
            '--date' => $today->toDateString(),
        ])->assertSuccessful();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_it_renders_late_fee_tokens_in_overdue_reminders(): void
    {
        $today = Carbon::parse('2026-09-15');
        $dueDate = Carbon::parse('2026-09-10'); // 5 days overdue

        $building = Building::factory()->create([
            'late_fee_type' => LateFeeType::Fixed,
            'late_fee_amount' => '150.00',
        ]);

        NotificationRule::factory()->create([
            'building_id' => $building->id,
            'trigger_event' => NotificationTriggerEvent::BillOverdue,
            'days_offset' => 5,
            'title_template' => 'Bill Overdue for {flat_number}',
            'body_template' => 'Your bill is {days_overdue} days overdue. Late fee of {late_fee} BDT applies.',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id, 'number' => '101']);

        ServiceChargeBill::factory()->create([
            'flat_id' => $flat->id,
            'due_date' => $dueDate,
            'total_amount' => '2000.00',
            'status' => BillStatus::Unpaid,
        ]);

        $this->artisan('notifications:send-bill-reminders', [
            '--date' => $today->toDateString(),
        ])->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'title' => 'Bill Overdue for 101',
            'body' => 'Your bill is 5 days overdue. Late fee of 150.00 BDT applies.',
        ]);
    }
}
