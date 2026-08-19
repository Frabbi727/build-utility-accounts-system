<?php

namespace Tests\Feature\Setup;

use App\Enums\Role;
use App\Livewire\Setup\Wizard;
use App\Models\Building;
use App\Models\ChargeHead;
use App\Models\Flat;
use App\Models\Floor;
use App\Models\JournalEntry;
use App\Models\Owner;
use App\Models\User;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role->value]);

        return $user;
    }

    public function test_an_admin_walks_from_an_empty_database_to_a_billable_building(): void
    {
        $component = Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(Wizard::class)
            ->assertSet('step', 1)
            ->set('buildingName', 'Shapla Tower')
            ->set('address', 'Dhanmondi, Dhaka')
            ->set('dueDayOfMonth', 10)
            ->call('saveBuilding')
            ->assertHasNoErrors()
            ->assertSet('step', 2);

        $building = Building::firstOrFail();
        $this->assertSame($building->id, app(CurrentBuilding::class)->id());

        $component->set('fromLevel', 1)
            ->set('toLevel', 3)
            ->set('suffixes', 'A,B')
            ->set('defaultSizeSqft', '1150')
            ->call('saveFlats')
            ->assertHasNoErrors()
            ->assertSet('step', 3);

        $this->assertSame(6, Flat::count());
        $this->assertSame(3, Floor::count());

        $component->set('ownerName', 'Rahim Uddin')
            ->set('ownerPhone', '01711000000')
            ->call('addOwner')
            ->assertHasNoErrors()
            ->assertSet('ownerName', '');

        $this->assertSame(1, Owner::count());

        $component->call('goToStep', 4)
            ->call('addSuggestedHeads');

        $this->assertSame(5, ChargeHead::count());
        $this->assertSame($building->id, ChargeHead::first()->building_id);

        $component->call('goToStep', 5)
            ->set('openingAsOf', '2026-01-01')
            ->set('cash', '5000')
            ->set('bank', '95000')
            ->call('saveOpeningBalances')
            ->assertHasNoErrors()
            ->assertSet('step', 6);

        $entry = JournalEntry::with('lines')->firstOrFail();
        $this->assertSame(
            bcadd((string) $entry->lines->sum('debit'), '0', 2),
            bcadd((string) $entry->lines->sum('credit'), '0', 2),
        );
    }

    public function test_the_wizard_resumes_at_the_flats_step_when_a_building_exists(): void
    {
        $building = Building::factory()->create(['name' => 'Existing Tower']);
        app(CurrentBuilding::class)->set($building->id);

        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(Wizard::class)
            ->assertSet('step', 2)
            ->assertSet('buildingName', 'Existing Tower')
            ->assertSet('buildingId', $building->id);
    }

    public function test_it_cannot_be_skipped_past_the_building_step_before_a_building_exists(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(Wizard::class)
            ->call('goToStep', 5)
            ->assertSet('step', 1);
    }

    public function test_a_custom_charge_head_can_be_added_in_the_wizard(): void
    {
        $building = Building::factory()->create();
        app(CurrentBuilding::class)->set($building->id);

        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(Wizard::class)
            ->call('goToStep', 4)
            ->set('headName', 'Generator Fuel')
            ->set('headBasis', 'equal_share')
            ->set('headAmount', '6000')
            ->call('addHead')
            ->assertHasNoErrors()
            ->assertSet('headName', '');

        $head = ChargeHead::firstWhere('name', 'Generator Fuel');
        $this->assertSame('equal_share', $head->basis->value);
        $this->assertSame('6000.00', (string) $head->amount);
    }

    public function test_the_building_step_validates_its_input(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(Wizard::class)
            ->set('buildingName', '')
            ->set('dueDayOfMonth', 31)
            ->call('saveBuilding')
            ->assertHasErrors(['buildingName', 'dueDayOfMonth'])
            ->assertSet('step', 1);

        $this->assertSame(0, Building::count());
    }

    public function test_only_an_admin_can_open_the_wizard(): void
    {
        foreach ([Role::Accountant, Role::Committee, Role::Owner] as $role) {
            Livewire::actingAs($this->userWithRole($role))
                ->test(Wizard::class)
                ->assertForbidden();
        }
    }

    public function test_an_admin_on_an_empty_install_is_redirected_to_the_wizard(): void
    {
        $this->actingAs($this->userWithRole(Role::Admin))
            ->get(route('dashboard'))
            ->assertRedirect(route('setup'));
    }

    public function test_the_dashboard_loads_normally_once_a_building_exists(): void
    {
        Building::factory()->create();

        $this->actingAs($this->userWithRole(Role::Admin))
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_a_non_admin_is_not_redirected_to_the_wizard(): void
    {
        $this->actingAs($this->userWithRole(Role::Committee))
            ->get(route('dashboard'))
            ->assertOk();
    }
}
