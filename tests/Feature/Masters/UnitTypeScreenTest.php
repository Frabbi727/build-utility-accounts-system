<?php

namespace Tests\Feature\Masters;

use App\Enums\AccountCode;
use App\Enums\Role;
use App\Livewire\FlatList;
use App\Livewire\Masters\ChargeHeadList;
use App\Livewire\Masters\UnitTypeList;
use App\Models\Building;
use App\Models\ChargeHead;
use App\Models\Flat;
use App\Models\UnitType;
use App\Models\User;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnitTypeScreenTest extends TestCase
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

    public function test_an_accountant_can_add_a_unit_type(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(UnitTypeList::class)
            ->call('create')
            ->set('name', 'Shop')
            ->set('nameBn', 'দোকান')
            ->call('save')
            ->assertHasNoErrors();

        $type = UnitType::firstOrFail();

        $this->assertSame('Shop', $type->name);
        $this->assertSame($this->building->id, $type->building_id);
    }

    public function test_a_committee_member_cannot_add_a_unit_type(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Committee))
            ->test(UnitTypeList::class)
            ->call('create')
            ->assertForbidden();
    }

    public function test_a_unit_type_in_use_cannot_be_deleted(): void
    {
        $type = UnitType::factory()->shop()->create(['building_id' => $this->building->id]);
        Flat::factory()->for($this->building)->create(['unit_type_id' => $type->id]);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(UnitTypeList::class)
            ->call('delete', $type->id)
            ->assertSee($type->displayName());

        // Refused before the delete is attempted: on Postgres a constraint violation
        // would poison the transaction and leave nothing to re-render, so the screen
        // has to survive the refusal and still list the type.
        $this->assertSame(1, UnitType::count());
        $this->assertSame(1, Flat::whereNotNull('unit_type_id')->count());
    }

    public function test_an_unused_unit_type_can_be_deleted(): void
    {
        $type = UnitType::factory()->shop()->create(['building_id' => $this->building->id]);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(UnitTypeList::class)
            ->call('delete', $type->id);

        $this->assertSame(0, UnitType::count());
    }

    public function test_a_flat_can_be_given_a_unit_type(): void
    {
        $type = UnitType::factory()->shop()->create(['building_id' => $this->building->id]);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(FlatList::class)
            ->call('create')
            ->set('number', 'S-1')
            ->set('sizeSqft', '450')
            ->set('unitTypeId', $type->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($type->id, Flat::firstOrFail()->unit_type_id);
    }

    public function test_a_flat_cannot_take_another_buildings_unit_type(): void
    {
        $foreign = UnitType::factory()->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(FlatList::class)
            ->call('create')
            ->set('number', 'S-1')
            ->set('sizeSqft', '450')
            ->set('unitTypeId', $foreign->id)
            ->call('save')
            ->assertHasErrors('unitTypeId');

        $this->assertSame(0, Flat::count());
    }

    public function test_a_charge_head_can_be_restricted_to_unit_types(): void
    {
        $shop = UnitType::factory()->shop()->create(['building_id' => $this->building->id]);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(ChargeHeadList::class)
            ->call('create')
            ->set('name', 'Shutter Cleaning')
            ->set('accountId', app(JournalService::class)->account(AccountCode::ServiceChargeIncome)->id)
            ->set('basis', 'per_flat')
            ->set('amount', '500')
            ->set('unitTypeIds', [$shop->id])
            ->call('save')
            ->assertHasNoErrors();

        $head = ChargeHead::with('unitTypes')->firstOrFail();

        $this->assertSame([$shop->id], $head->unitTypes->modelKeys());
    }

    public function test_clearing_the_restriction_restores_applies_to_every_unit(): void
    {
        $shop = UnitType::factory()->shop()->create(['building_id' => $this->building->id]);
        $head = ChargeHead::factory()->create([
            'building_id' => $this->building->id,
            'account_id' => app(JournalService::class)->account(AccountCode::ServiceChargeIncome)->id,
            'name' => 'Shutter Cleaning',
        ]);
        $head->unitTypes()->attach($shop);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(ChargeHeadList::class)
            ->call('edit', $head->id)
            ->assertSet('unitTypeIds', [$shop->id])
            ->set('unitTypeIds', [])
            ->call('save')
            ->assertHasNoErrors();

        // An empty set is meaningful, not a no-op.
        $this->assertCount(0, $head->refresh()->unitTypes);
        $this->assertTrue($head->appliesTo(Flat::factory()->for($this->building)->create()));
    }
}
