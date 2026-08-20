<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Building;
use App\Models\ChargeHead;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\User;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every screen, fetched over HTTP as each role.
 *
 * Component tests render a component in isolation; this exercises the layout, the
 * navigation builder and the building switcher around it, which is where a broken
 * route name or a missing translation key actually shows up.
 */
class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->create();
        ChargeHead::factory()->for($this->building)->create();
        Flat::factory()->for($this->building)->create();

        app(CurrentBuilding::class)->set($this->building->id);
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role->value]);

        return $user;
    }

    // bills.print and payments.receipt need a bound model, so their auth is covered
    // by BillPrintTest and ReceiptTest rather than by this name-only matrix.

    /**
     * @return list<string>
     */
    private function staffRoutes(): array
    {
        return [
            'dashboard', 'flats.index', 'buildings.index', 'floors.index', 'charge-heads.index',
            'owners.index', 'tenants.index', 'vendors.index', 'staff.index',
            'accounts.index', 'expenses.index', 'vendor-bills.index',
            'ad-hoc-charges.index', 'unit-types.index', 'shared-costs.index',
            'utilities.index', 'meters.index', 'tariffs.index', 'readings.index',
            'reports.index', 'reports.owner-dues', 'reports.collections', 'bills.print-month', 'reports.expenses',
            'reports.cash-book', 'reports.income-expenditure', 'reports.balance-sheet',
            'reports.trial-balance',
        ];
    }

    public function test_an_admin_can_load_every_screen(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        $routes = [
            ...$this->staffRoutes(),
            'billing.generate', 'payments.create',
            'accounting.opening-balances', 'accounting.periods', 'users.index', 'setup',
        ];

        foreach ($routes as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk("Route {$name} did not load.");
        }
    }

    public function test_an_admin_can_load_the_per_flat_screens(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $flat = Flat::firstOrFail();

        $this->actingAs($admin)->get(route('flats.statement', $flat))->assertOk();
        $this->actingAs($admin)->get(route('flats.charges', $flat))->assertOk();
    }

    public function test_an_accountant_can_load_every_screen_except_the_admin_ones(): void
    {
        $accountant = $this->userWithRole(Role::Accountant);

        foreach ([...$this->staffRoutes(), 'billing.generate', 'payments.create'] as $name) {
            $this->actingAs($accountant)->get(route($name))->assertOk("Route {$name} did not load.");
        }

        foreach (['accounting.opening-balances', 'accounting.periods', 'users.index'] as $name) {
            $this->actingAs($accountant)->get(route($name))->assertForbidden("Route {$name} should be admin only.");
        }
    }

    public function test_a_committee_member_reads_but_cannot_reach_the_money_screens(): void
    {
        $committee = $this->userWithRole(Role::Committee);

        foreach ($this->staffRoutes() as $name) {
            $this->actingAs($committee)->get(route($name))->assertOk("Route {$name} did not load.");
        }

        foreach (['billing.generate', 'payments.create', 'users.index'] as $name) {
            $this->actingAs($committee)->get(route($name))->assertForbidden("Route {$name} should be closed.");
        }
    }

    public function test_an_owner_reaches_only_their_own_dashboard_and_statement(): void
    {
        $user = $this->userWithRole(Role::Owner);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $ownFlat = Flat::factory()->for($this->building)->create(['owner_id' => $owner->id]);
        $otherFlat = Flat::factory()->for($this->building)->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('flats.statement', $ownFlat))->assertOk();
        $this->actingAs($user)->get(route('flats.statement', $otherFlat))->assertForbidden();

        foreach (['flats.index', 'owners.index', 'accounts.index', 'reports.index'] as $name) {
            $this->actingAs($user)->get(route($name))->assertForbidden("Route {$name} should be closed to owners.");
        }
    }

    public function test_a_guest_is_sent_to_the_login_screen(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('flats.index'))->assertRedirect(route('login'));
    }

    public function test_the_building_switcher_changes_the_working_building(): void
    {
        $other = Building::factory()->create(['name' => 'Second Tower']);

        $this->actingAs($this->userWithRole(Role::Admin))
            ->post(route('buildings.switch'), ['building_id' => $other->id])
            ->assertRedirect();

        $this->assertSame($other->id, session('current_building_id'));
    }

    public function test_the_switcher_ignores_a_building_that_does_not_exist(): void
    {
        $this->actingAs($this->userWithRole(Role::Admin))
            ->post(route('buildings.switch'), ['building_id' => 999999]);

        $this->assertNotSame(999999, session('current_building_id'));
    }

    public function test_every_screen_also_renders_in_bangla(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        $this->actingAs($admin)->post(route('locale.switch'), ['locale' => 'bn']);

        foreach ([...$this->staffRoutes(), 'billing.generate', 'accounting.opening-balances', 'users.index', 'setup'] as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk("Route {$name} did not render in Bangla.");
        }
    }
}
