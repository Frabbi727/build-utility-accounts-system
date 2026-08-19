<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->flatRate('3000.00')->create();
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role->value]);

        return $user;
    }

    /**
     * Creates an owner user together with the flat they own.
     *
     * @return array{0: User, 1: Flat}
     */
    private function ownerWithFlat(): array
    {
        $user = $this->userWithRole(Role::Owner);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->for($this->building)->create(['owner_id' => $owner->id]);

        return [$user, $flat];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('flats.index'))->assertRedirect(route('login'));
    }

    public function test_an_owner_cannot_open_another_flats_statement(): void
    {
        [$user] = $this->ownerWithFlat();
        $otherFlat = Flat::factory()->for($this->building)->create();

        $this->actingAs($user)
            ->get(route('flats.statement', $otherFlat))
            ->assertForbidden();
    }

    public function test_an_owner_can_open_their_own_statement(): void
    {
        [$user, $flat] = $this->ownerWithFlat();

        $this->actingAs($user)
            ->get(route('flats.statement', $flat))
            ->assertOk()
            ->assertSee($flat->number);
    }

    public function test_an_owner_cannot_reach_staff_screens(): void
    {
        [$user] = $this->ownerWithFlat();

        $this->actingAs($user)->get(route('flats.index'))->assertForbidden();
        $this->actingAs($user)->get(route('accounts.index'))->assertForbidden();
        $this->actingAs($user)->get(route('reports.trial-balance'))->assertForbidden();
        $this->actingAs($user)->get(route('billing.generate'))->assertForbidden();
        $this->actingAs($user)->get(route('payments.create'))->assertForbidden();
    }

    public function test_a_committee_member_gets_read_only_access(): void
    {
        $user = $this->userWithRole(Role::Committee);

        $this->actingAs($user)->get(route('reports.trial-balance'))->assertOk();
        $this->actingAs($user)->get(route('flats.index'))->assertOk();

        // Read-only: no billing or collection screens.
        $this->actingAs($user)->get(route('billing.generate'))->assertForbidden();
        $this->actingAs($user)->get(route('payments.create'))->assertForbidden();
    }

    public function test_an_accountant_can_reach_billing_and_collections(): void
    {
        $user = $this->userWithRole(Role::Accountant);

        $this->actingAs($user)->get(route('billing.generate'))->assertOk();
        $this->actingAs($user)->get(route('payments.create'))->assertOk();
        $this->actingAs($user)->get(route('reports.trial-balance'))->assertOk();
    }

    public function test_an_admin_can_reach_everything(): void
    {
        $user = $this->userWithRole(Role::Admin);

        foreach (['dashboard', 'flats.index', 'accounts.index', 'reports.trial-balance', 'billing.generate', 'payments.create'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }
    }

    public function test_a_staff_member_may_view_any_flat_statement(): void
    {
        $user = $this->userWithRole(Role::Accountant);
        $flat = Flat::factory()->for($this->building)->create();

        $this->actingAs($user)->get(route('flats.statement', $flat))->assertOk();
    }
}
