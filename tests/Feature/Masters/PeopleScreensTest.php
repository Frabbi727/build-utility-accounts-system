<?php

namespace Tests\Feature\Masters;

use App\Enums\AccountCode;
use App\Enums\Role;
use App\Livewire\Masters\OwnerList;
use App\Livewire\Masters\StaffList;
use App\Livewire\Masters\TenantList;
use App\Livewire\Masters\VendorList;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PeopleScreensTest extends TestCase
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

    public function test_an_owner_record_can_be_created_and_linked_to_a_login(): void
    {
        $login = $this->userWithRole(Role::Owner);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(OwnerList::class)
            ->call('create')
            ->set('name', 'Rahim Uddin')
            ->set('phone', '01711000000')
            ->set('email', 'rahim@example.com')
            ->set('userId', $login->id)
            ->call('save')
            ->assertHasNoErrors();

        $owner = Owner::firstOrFail();
        $this->assertSame($login->id, $owner->user_id);
        $this->assertSame($owner->id, $login->fresh()->owner->id);
    }

    public function test_one_login_cannot_be_linked_to_two_owners(): void
    {
        $login = $this->userWithRole(Role::Owner);
        Owner::factory()->create(['user_id' => $login->id]);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(OwnerList::class)
            ->call('create')
            ->set('name', 'Second Claimant')
            ->set('userId', $login->id)
            ->call('save')
            ->assertHasErrors('userId');

        $this->assertSame(1, Owner::count());
    }

    public function test_an_owner_with_an_invalid_email_is_rejected(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(OwnerList::class)
            ->set('name', 'Someone')
            ->set('email', 'not-an-email')
            ->call('save')
            ->assertHasErrors('email');
    }

    public function test_a_tenant_can_be_recorded_against_a_flat(): void
    {
        $flat = Flat::factory()->for($this->building)->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(TenantList::class)
            ->call('create')
            ->set('flatId', $flat->id)
            ->set('name', 'Karim Mia')
            ->set('leaseStartedOn', '2026-01-01')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($flat->id, Tenant::firstOrFail()->flat_id);
    }

    public function test_a_lease_ending_before_it_starts_is_rejected(): void
    {
        $flat = Flat::factory()->for($this->building)->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(TenantList::class)
            ->set('flatId', $flat->id)
            ->set('name', 'Karim Mia')
            ->set('leaseStartedOn', '2026-06-01')
            ->set('leaseEndedOn', '2026-01-01')
            ->call('save')
            ->assertHasErrors('leaseEndedOn');

        $this->assertSame(0, Tenant::count());
    }

    public function test_a_tenant_cannot_be_attached_to_another_buildings_flat(): void
    {
        $otherFlat = Flat::factory()->for(Building::factory()->create())->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(TenantList::class)
            ->set('flatId', $otherFlat->id)
            ->set('name', 'Wrong Building')
            ->call('save')
            ->assertHasErrors('flatId');

        $this->assertSame(0, Tenant::count());
    }

    public function test_a_vendor_can_be_created_and_deactivated(): void
    {
        $component = Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(VendorList::class)
            ->call('create')
            ->set('name', 'Dhaka Lift Services')
            ->set('phone', '01711000001')
            ->call('save')
            ->assertHasNoErrors();

        $vendor = Vendor::firstOrFail();
        $this->assertTrue($vendor->is_active);

        $component->call('edit', $vendor->id)
            ->set('isActive', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($vendor->fresh()->is_active);
    }

    public function test_a_staff_member_is_created_against_the_current_building(): void
    {
        $account = app(JournalService::class)->account(AccountCode::GuardSalary);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(StaffList::class)
            ->call('create')
            ->set('name', 'Abdul Karim')
            ->set('designation', 'Guard')
            ->set('monthlySalary', '12000')
            ->set('expenseAccountId', $account->id)
            ->set('joinedOn', '2024-03-01')
            ->call('save')
            ->assertHasNoErrors();

        $staff = Staff::firstOrFail();
        $this->assertSame($this->building->id, $staff->building_id);
        $this->assertSame('12000.00', (string) $staff->monthly_salary);
        $this->assertSame($account->id, $staff->expense_account_id);
    }

    public function test_a_staff_member_needs_a_designation(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(StaffList::class)
            ->set('name', 'Nameless Role')
            ->set('designation', '')
            ->call('save')
            ->assertHasErrors('designation');

        $this->assertSame(0, Staff::count());
    }

    public function test_a_committee_member_reads_the_people_screens_but_cannot_change_them(): void
    {
        $committee = $this->userWithRole(Role::Committee);

        foreach ([OwnerList::class, TenantList::class, VendorList::class, StaffList::class] as $screen) {
            Livewire::actingAs($committee)->test($screen)->assertOk()->call('create')->assertForbidden();
        }
    }

    public function test_an_owner_role_cannot_reach_the_people_screens(): void
    {
        $owner = $this->userWithRole(Role::Owner);

        foreach ([OwnerList::class, TenantList::class, VendorList::class, StaffList::class] as $screen) {
            Livewire::actingAs($owner)->test($screen)->assertForbidden();
        }
    }
}
