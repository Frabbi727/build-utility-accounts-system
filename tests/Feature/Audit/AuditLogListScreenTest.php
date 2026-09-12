<?php

namespace Tests\Feature\Audit;

use App\Enums\Role;
use App\Livewire\Admin\AuditLogList;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogListScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_non_admin_cannot_access_audit_logs(): void
    {
        $resident = User::factory()->create();
        $resident->assignRole(Role::Owner->value);

        $this->actingAs($resident)
            ->get(route('admin.audit-logs'))
            ->assertForbidden();
    }

    public function test_admin_can_access_audit_logs_screen(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        AuditLog::create([
            'action' => 'CREATE',
            'module' => 'bills',
            'description' => 'Created Bill #101',
            'user_id' => $admin->id,
            'source' => 'web',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs'))
            ->assertOk()
            ->assertSee('Created Bill #101');
    }

    public function test_admin_can_filter_audit_logs_by_module_and_view_detail(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $log1 = AuditLog::create([
            'action' => 'CREATE',
            'module' => 'bills',
            'description' => 'Created Bill #101',
            'user_id' => $admin->id,
            'old_values' => null,
            'new_values' => ['total_amount' => '3500.00'],
            'changed_fields' => ['total_amount'],
        ]);

        $log2 = AuditLog::create([
            'action' => 'UPDATE',
            'module' => 'users',
            'description' => 'Updated User #5',
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(AuditLogList::class)
            ->set('module', 'bills')
            ->assertSee('Created Bill #101')
            ->assertDontSee('Updated User #5')
            ->call('openDetailModal', $log1->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('3500.00');
    }

    public function test_admin_can_cancel_modal_without_method_not_found_exception(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $log = AuditLog::create([
            'action' => 'CREATE',
            'module' => 'bills',
            'description' => 'Created Bill #101',
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(AuditLogList::class)
            ->call('openDetailModal', $log->id)
            ->assertSet('showDetailModal', true)
            ->call('cancel')
            ->assertSet('showDetailModal', false)
            ->assertSet('viewingLogId', null);
    }

    public function test_view_snapshot_from_history_modal_works_properly(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $log1 = AuditLog::create([
            'action' => 'CREATE',
            'module' => 'bills',
            'entity_type' => 'App\Models\Bill',
            'entity_id' => '101',
            'description' => 'Created Bill #101',
            'user_id' => $admin->id,
            'new_values' => ['status' => 'DRAFT', 'amount' => '2500'],
            'changed_fields' => ['status', 'amount'],
        ]);

        $log2 = AuditLog::create([
            'action' => 'UPDATE',
            'module' => 'bills',
            'entity_type' => 'App\Models\Bill',
            'entity_id' => '101',
            'description' => 'Updated Bill #101 to POSTED',
            'user_id' => $admin->id,
            'old_values' => ['status' => 'DRAFT'],
            'new_values' => ['status' => 'POSTED'],
            'changed_fields' => ['status'],
        ]);

        $this->actingAs($admin);

        Livewire::test(AuditLogList::class)
            ->call('viewEntityHistory', 'App\Models\Bill', '101')
            ->assertSet('showHistoryModal', true)
            ->assertSee('View Snapshot →')
            ->call('openDetailModal', $log2->id)
            ->assertSet('showDetailModal', true)
            ->assertSet('showHistoryModal', true)
            ->assertSee('POSTED')
            ->call('cancel')
            ->assertSet('showDetailModal', false)
            ->assertSet('showHistoryModal', true)
            ->call('cancel')
            ->assertSet('showHistoryModal', false);
    }

    public function test_snapshot_displays_before_after_changes_even_if_changed_fields_empty(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $log = AuditLog::create([
            'action' => 'UPDATE',
            'module' => 'masters',
            'description' => 'Updated Flat 101',
            'user_id' => $admin->id,
            'old_values' => ['sqft' => '1200', 'status' => 'vacant'],
            'new_values' => ['sqft' => '1250', 'status' => 'occupied'],
            'changed_fields' => null,
        ]);

        $this->actingAs($admin);

        Livewire::test(AuditLogList::class)
            ->call('openDetailModal', $log->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('1200')
            ->assertSee('1250')
            ->assertSee('vacant')
            ->assertSee('occupied');
    }

    public function test_audit_log_timestamps_display_in_dhaka_timezone(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $log = AuditLog::create([
            'action' => 'CREATE',
            'module' => 'bills',
            'description' => 'Created Bill #101',
            'user_id' => $admin->id,
            'created_at' => \Illuminate\Support\Carbon::create(2026, 9, 12, 15, 30, 0, 'Asia/Dhaka'),
        ]);

        $this->actingAs($admin);

        Livewire::test(AuditLogList::class)
            ->assertSee('03:30:00 PM')
            ->call('openDetailModal', $log->id)
            ->assertSee('03:30:00 PM')
            ->assertSee('(BST)');
    }

    public function test_snapshot_renders_empty_state_when_no_diff_or_payload(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $log = AuditLog::create([
            'action' => 'LOGIN',
            'module' => 'auth',
            'description' => 'User logged in',
            'user_id' => $admin->id,
            'old_values' => null,
            'new_values' => null,
            'payload' => null,
        ]);

        $this->actingAs($admin);

        Livewire::test(AuditLogList::class)
            ->call('openDetailModal', $log->id)
            ->assertSee('No state snapshot recorded for this audit entry.');
    }

    public function test_snapshot_renders_payload_when_only_payload_exists(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $log = AuditLog::create([
            'action' => 'ACCOUNTING.LOCK',
            'module' => 'accounting',
            'description' => 'Locked period',
            'user_id' => $admin->id,
            'old_values' => null,
            'new_values' => null,
            'payload' => ['year' => 2026, 'month' => 9, 'reason' => 'Quarterly closing'],
        ]);

        $this->actingAs($admin);

        Livewire::test(AuditLogList::class)
            ->call('openDetailModal', $log->id)
            ->assertDontSee('No state snapshot recorded for this audit entry.')
            ->assertSee('Quarterly closing')
            ->assertSee('Technical Payload / Extra Details');
    }

    public function test_snapshot_formats_special_types_properly(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $log = AuditLog::create([
            'action' => 'UPDATE',
            'module' => 'masters',
            'description' => 'Updated settings',
            'user_id' => $admin->id,
            'old_values' => [
                'is_active' => false,
                'notes' => null,
                'empty_field' => '',
                'meta' => ['tag' => 'v1'],
            ],
            'new_values' => [
                'is_active' => true,
                'notes' => 'New note',
                'empty_field' => 'Filled',
                'meta' => ['tag' => 'v2'],
            ],
            'changed_fields' => ['is_active', 'notes', 'empty_field', 'meta'],
        ]);

        $this->actingAs($admin);

        Livewire::test(AuditLogList::class)
            ->call('openDetailModal', $log->id)
            ->assertSee('false')
            ->assertSee('true')
            ->assertSee('null')
            ->assertSee('(empty string)')
            ->assertSee('New note')
            ->assertSee('&quot;tag&quot;: &quot;v1&quot;', false);
    }

    public function test_audit_logs_can_be_filtered_by_dhaka_date_range(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $logOnDate = AuditLog::create([
            'action' => 'CREATE',
            'module' => 'bills',
            'description' => 'Target Log on 2026-09-12',
            'user_id' => $admin->id,
            'created_at' => \Illuminate\Support\Carbon::create(2026, 9, 12, 10, 0, 0, 'Asia/Dhaka'),
        ]);

        $logOtherDate = AuditLog::create([
            'action' => 'CREATE',
            'module' => 'bills',
            'description' => 'Other Log on 2026-09-10',
            'user_id' => $admin->id,
            'created_at' => \Illuminate\Support\Carbon::create(2026, 9, 10, 10, 0, 0, 'Asia/Dhaka'),
        ]);

        $this->actingAs($admin);

        Livewire::test(AuditLogList::class)
            ->set('dateFrom', '2026-09-12')
            ->set('dateTo', '2026-09-12')
            ->assertSee('Target Log on 2026-09-12')
            ->assertDontSee('Other Log on 2026-09-10');
    }
}


