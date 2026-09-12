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
}
