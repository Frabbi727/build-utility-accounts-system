<?php

namespace Tests\Feature\Billing;

use App\Enums\Role;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\ServiceChargeBill;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillPrintTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    private ServiceChargeBill $bill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->flatRate('3000.00')->create(['due_day_of_month' => 10]);
        $this->flat = Flat::factory()->for($this->building)->create(['number' => 'A-4']);

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-06-01'));

        $this->bill = ServiceChargeBill::where('flat_id', $this->flat->id)->firstOrFail();
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role->value]);

        return $user;
    }

    public function test_the_owning_owner_may_read_their_own_bill(): void
    {
        $user = $this->userWithRole(Role::Owner);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $this->flat->update(['owner_id' => $owner->id]);

        $this->assertTrue($user->can('view', $this->bill->fresh()));
    }

    public function test_another_owner_may_not_read_it(): void
    {
        $user = $this->userWithRole(Role::Owner);
        Owner::factory()->create(['user_id' => $user->id]);

        $this->assertFalse($user->can('view', $this->bill));
    }

    public function test_staff_may_read_any_bill(): void
    {
        $this->assertTrue($this->userWithRole(Role::Committee)->can('view', $this->bill));
    }
}
