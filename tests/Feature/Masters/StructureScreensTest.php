<?php

namespace Tests\Feature\Masters;

use App\Enums\AccountCode;
use App\Enums\Role;
use App\Livewire\FlatList;
use App\Livewire\Masters\ChargeHeadList;
use App\Livewire\Masters\FlatChargeOverrides;
use App\Livewire\Masters\FloorList;
use App\Models\Building;
use App\Models\ChargeHead;
use App\Models\Flat;
use App\Models\FlatChargeOverride;
use App\Models\Floor;
use App\Models\User;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StructureScreensTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->create();
        app(CurrentBuilding::class)->set($this->building->id);
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role->value]);

        return $user;
    }

    private function incomeAccountId(): int
    {
        return app(JournalService::class)->account(AccountCode::ServiceChargeIncome)->id;
    }

    public function test_a_floor_can_be_created_in_the_current_building(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(FloorList::class)
            ->call('create')
            ->set('name', 'Level 3')
            ->set('level', 3)
            ->call('save')
            ->assertHasNoErrors();

        $floor = Floor::firstOrFail();
        $this->assertSame($this->building->id, $floor->building_id);
        $this->assertSame(3, $floor->level);
    }

    public function test_two_floors_cannot_share_a_level_in_one_building(): void
    {
        Floor::factory()->for($this->building)->create(['level' => 3]);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(FloorList::class)
            ->call('create')
            ->set('name', 'Another third')
            ->set('level', 3)
            ->call('save')
            ->assertHasErrors('level');

        $this->assertSame(1, Floor::count());
    }

    public function test_a_charge_head_can_be_configured(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(ChargeHeadList::class)
            ->call('create')
            ->set('name', 'Guard Salary')
            ->set('accountId', $this->incomeAccountId())
            ->set('basis', 'per_sqft')
            ->set('amount', '2.50')
            ->call('save')
            ->assertHasNoErrors();

        $head = ChargeHead::firstOrFail();
        $this->assertSame('per_sqft', $head->basis->value);
        $this->assertSame('2.50', (string) $head->amount);
        $this->assertSame($this->building->id, $head->building_id);
    }

    public function test_charge_head_names_are_unique_per_building(): void
    {
        ChargeHead::factory()->for($this->building)->create(['name' => 'Lift']);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(ChargeHeadList::class)
            ->call('create')
            ->set('name', 'Lift')
            ->set('accountId', $this->incomeAccountId())
            ->set('amount', '100')
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_a_charge_head_can_be_deactivated_from_the_list(): void
    {
        $head = ChargeHead::factory()->for($this->building)->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(ChargeHeadList::class)
            ->call('toggleActive', $head->id);

        $this->assertFalse($head->fresh()->is_active);
    }

    public function test_a_committee_member_cannot_change_charge_heads(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Committee))
            ->test(ChargeHeadList::class)
            ->assertOk()
            ->call('create')
            ->assertForbidden();
    }

    public function test_the_bulk_generator_creates_a_building_of_flats(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(FlatList::class)
            ->call('startBulk')
            ->set('fromLevel', 1)
            ->set('toLevel', 4)
            ->set('suffixes', 'A, B, C')
            ->set('bulkSizeSqft', '1100')
            ->call('generateFlats')
            ->assertHasNoErrors();

        $this->assertSame(12, Flat::count());
        $this->assertSame(4, Floor::count());
        $this->assertNotNull(Flat::where('number', '4C')->first());
        $this->assertSame('1100.00', (string) Flat::where('number', '1A')->value('size_sqft'));
    }

    public function test_the_bulk_generator_skips_flats_that_already_exist(): void
    {
        Flat::factory()->for($this->building)->create(['number' => '1A']);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(FlatList::class)
            ->call('startBulk')
            ->set('fromLevel', 1)
            ->set('toLevel', 1)
            ->set('suffixes', 'A,B')
            ->call('generateFlats')
            ->assertHasNoErrors();

        $this->assertSame(2, Flat::count());
    }

    public function test_the_bulk_generator_rejects_a_backwards_level_range(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(FlatList::class)
            ->set('fromLevel', 5)
            ->set('toLevel', 2)
            ->call('generateFlats')
            ->assertHasErrors('toLevel');

        $this->assertSame(0, Flat::count());
    }

    public function test_a_flat_can_be_created_and_deactivated(): void
    {
        $component = Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(FlatList::class)
            ->call('create')
            ->set('number', '7B')
            ->set('sizeSqft', '1350')
            ->call('save')
            ->assertHasNoErrors();

        $flat = Flat::firstOrFail();
        $this->assertSame('1350.00', (string) $flat->size_sqft);

        $component->call('toggleActive', $flat->id);
        $this->assertFalse($flat->fresh()->is_active);
    }

    public function test_the_override_screen_exempts_a_flat_from_a_head(): void
    {
        $lift = ChargeHead::factory()->for($this->building)->create(['name' => 'Lift', 'amount' => '500.00']);
        $guard = ChargeHead::factory()->for($this->building)->create(['name' => 'Guard', 'amount' => '1200.00']);
        $flat = Flat::factory()->for($this->building)->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(FlatChargeOverrides::class, ['flat' => $flat])
            ->assertSet("rows.{$lift->id}.mode", 'standard')
            ->set("rows.{$lift->id}.mode", 'exempt')
            ->set("rows.{$guard->id}.mode", 'override')
            ->set("rows.{$guard->id}.amount", '900')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(FlatChargeOverride::where('charge_head_id', $lift->id)->firstOrFail()->is_exempt);
        $this->assertSame('900.00', (string) FlatChargeOverride::where('charge_head_id', $guard->id)->value('amount'));
    }

    public function test_an_override_row_without_an_amount_is_rejected(): void
    {
        $head = ChargeHead::factory()->for($this->building)->create();
        $flat = Flat::factory()->for($this->building)->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(FlatChargeOverrides::class, ['flat' => $flat])
            ->set("rows.{$head->id}.mode", 'override')
            ->set("rows.{$head->id}.amount", '')
            ->call('save')
            ->assertHasErrors("rows.{$head->id}.amount");

        $this->assertSame(0, FlatChargeOverride::count());
    }

    public function test_returning_a_head_to_standard_removes_the_override(): void
    {
        $head = ChargeHead::factory()->for($this->building)->create();
        $flat = Flat::factory()->for($this->building)->create();
        FlatChargeOverride::factory()->create(['flat_id' => $flat->id, 'charge_head_id' => $head->id, 'amount' => '1.00']);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(FlatChargeOverrides::class, ['flat' => $flat])
            ->assertSet("rows.{$head->id}.mode", 'override')
            ->set("rows.{$head->id}.mode", 'standard')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, FlatChargeOverride::count());
    }

    public function test_an_owner_cannot_open_another_flats_override_screen(): void
    {
        $flat = Flat::factory()->for($this->building)->create();

        Livewire::actingAs($this->userWithRole(Role::Owner))
            ->test(FlatChargeOverrides::class, ['flat' => $flat])
            ->assertForbidden();
    }
}
