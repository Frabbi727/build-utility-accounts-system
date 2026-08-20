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

    public function test_it_prints_the_bill_with_its_charges(): void
    {
        $this->actingAs($this->userWithRole(Role::Accountant))
            ->get(route('bills.print', $this->bill))
            ->assertSuccessful()
            ->assertSee($this->bill->bill_no)
            ->assertSee('A-4')
            ->assertSee($this->building->name)
            ->assertSee(__('billing.total_payable_now'))
            ->assertSee('3,000.00');
    }

    public function test_it_shows_previous_dues_brought_forward(): void
    {
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-07-01'));

        $july = ServiceChargeBill::where('flat_id', $this->flat->id)
            ->whereDate('billing_month', '2026-07-01')
            ->firstOrFail();

        $this->actingAs($this->userWithRole(Role::Accountant))
            ->get(route('bills.print', $july))
            ->assertSuccessful()
            ->assertSee(__('billing.previous_dues'))
            ->assertSee($this->bill->bill_no)
            ->assertSee('6,000.00');
    }

    public function test_a_bill_with_no_arrears_omits_the_brought_forward_block(): void
    {
        $this->actingAs($this->userWithRole(Role::Accountant))
            ->get(route('bills.print', $this->bill))
            ->assertSuccessful()
            ->assertDontSee(__('billing.previous_dues'));
    }

    public function test_an_owner_may_print_their_own_bill_but_not_anothers(): void
    {
        $user = $this->userWithRole(Role::Owner);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $this->flat->update(['owner_id' => $owner->id]);

        $this->actingAs($user)->get(route('bills.print', $this->bill))->assertSuccessful();

        $other = Flat::factory()->for($this->building)->create();
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));
        $otherBill = ServiceChargeBill::where('flat_id', $other->id)->firstOrFail();

        $this->actingAs($user)->get(route('bills.print', $otherBill))->assertForbidden();
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get(route('bills.print', $this->bill))->assertRedirect(route('login'));
    }

    public function test_it_renders_in_bangla(): void
    {
        $this->actingAs($this->userWithRole(Role::Accountant))
            ->withSession(['locale' => 'bn'])
            ->get(route('bills.print', $this->bill))
            ->assertSuccessful()
            ->assertSee(__('billing.total_payable_now', [], 'bn'));
    }
}
