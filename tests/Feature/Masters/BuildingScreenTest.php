<?php

namespace Tests\Feature\Masters;

use App\Enums\LateFeeType;
use App\Enums\Role;
use App\Livewire\Masters\BuildingList;
use App\Models\Building;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BuildingScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role->value]);

        return $user;
    }

    public function test_an_accountant_can_create_a_building(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(BuildingList::class)
            ->call('create')
            ->assertSet('showForm', true)
            ->set('name', 'Shapla Tower')
            ->set('nameBn', 'শাপলা টাওয়ার')
            ->set('address', 'Dhanmondi, Dhaka')
            ->set('dueDayOfMonth', 12)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $building = Building::firstOrFail();
        $this->assertSame('Shapla Tower', $building->name);
        $this->assertSame(12, $building->due_day_of_month);
    }

    public function test_it_validates_the_building_form(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(BuildingList::class)
            ->set('name', '')
            ->set('dueDayOfMonth', 31)
            ->call('save')
            ->assertHasErrors(['name', 'dueDayOfMonth']);

        $this->assertSame(0, Building::count());
    }

    public function test_editing_loads_the_record_and_updates_it(): void
    {
        $building = Building::factory()->create(['name' => 'Old Name']);

        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(BuildingList::class)
            ->call('edit', $building->id)
            ->assertSet('name', 'Old Name')
            ->set('name', 'New Name')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('New Name', $building->fresh()->name);
        $this->assertSame(1, Building::count());
    }

    public function test_a_committee_member_can_read_but_not_create(): void
    {
        Building::factory()->create();

        Livewire::actingAs($this->userWithRole(Role::Committee))
            ->test(BuildingList::class)
            ->assertOk()
            ->call('create')
            ->assertForbidden();
    }

    public function test_an_owner_cannot_reach_the_screen_at_all(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Owner))
            ->test(BuildingList::class)
            ->assertForbidden();
    }

    public function test_a_late_fee_policy_can_be_set_and_edited(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(BuildingList::class)
            ->call('create')
            ->set('name', 'Rose Tower')
            ->set('dueDayOfMonth', 10)
            ->set('lateFeeType', 'percent')
            ->set('lateFeeAmount', '2.50')
            ->set('lateFeeGraceDays', 5)
            ->call('save')
            ->assertHasNoErrors();

        $building = Building::where('name', 'Rose Tower')->sole();

        $this->assertSame(LateFeeType::Percent, $building->late_fee_type);
        $this->assertSame('2.50', (string) $building->late_fee_amount);
        $this->assertSame(5, $building->late_fee_grace_days);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(BuildingList::class)
            ->call('edit', $building->id)
            ->assertSet('lateFeeType', 'percent')
            ->assertSet('lateFeeAmount', '2.50')
            ->assertSet('lateFeeGraceDays', 5);
    }

    public function test_a_building_defaults_to_charging_no_late_fee(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(BuildingList::class)
            ->call('create')
            ->set('name', 'Lily Court')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(LateFeeType::None, Building::where('name', 'Lily Court')->sole()->late_fee_type);
    }

    public function test_a_negative_late_fee_is_rejected(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(BuildingList::class)
            ->call('create')
            ->set('name', 'Iris Place')
            ->set('lateFeeType', 'fixed')
            ->set('lateFeeAmount', '-50')
            ->call('save')
            ->assertHasErrors('lateFeeAmount');
    }
}
