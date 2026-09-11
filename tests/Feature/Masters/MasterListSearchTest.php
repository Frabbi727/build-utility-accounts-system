<?php

namespace Tests\Feature\Masters;

use App\Enums\Role;
use App\Livewire\Admin\MaintenanceRequestList;
use App\Livewire\FlatList;
use App\Livewire\Masters\NoticeList;
use App\Livewire\Masters\OwnerList;
use App\Livewire\Masters\VendorList;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Floor;
use App\Models\MaintenanceRequest;
use App\Models\Notice;
use App\Models\Owner;
use App\Models\User;
use App\Models\Vendor;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterListSearchTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->create();
        app(CurrentBuilding::class)->set($this->building->id);

        $this->admin = User::factory()->create();
        $this->admin->syncRoles([Role::Admin->value]);
    }

    public function test_maintenance_request_search_is_case_insensitive_and_matches_title_description_and_flat(): void
    {
        $flatA = Flat::factory()->create([
            'building_id' => $this->building->id,
            'number' => '4A',
        ]);
        $flatB = Flat::factory()->create([
            'building_id' => $this->building->id,
            'number' => '5B',
        ]);

        $req1 = MaintenanceRequest::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $flatA->id,
            'title' => 'Water Leakage in Kitchen',
            'description' => 'Pipe dripping continuously',
        ]);

        $req2 = MaintenanceRequest::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $flatB->id,
            'title' => 'Electrical Short Circuit',
            'description' => 'Main fuse breaker tripped',
        ]);

        $otherBuilding = Building::factory()->create();
        $otherFlat = Flat::factory()->create([
            'building_id' => $otherBuilding->id,
            'number' => '4A',
        ]);
        MaintenanceRequest::factory()->create([
            'building_id' => $otherBuilding->id,
            'flat_id' => $otherFlat->id,
            'title' => 'Other Building Leakage In Kitchen',
            'description' => 'Pipe leaking elsewhere',
        ]);

        // Search by lowercase title fragment
        Livewire::actingAs($this->admin)
            ->test(MaintenanceRequestList::class)
            ->set('search', 'leakage')
            ->assertSee('Water Leakage in Kitchen')
            ->assertDontSee('Other Building Leakage In Kitchen')
            ->assertDontSee('Electrical Short Circuit');

        // Search by uppercase description fragment
        Livewire::actingAs($this->admin)
            ->test(MaintenanceRequestList::class)
            ->set('search', 'FUSE')
            ->assertSee('Electrical Short Circuit')
            ->assertDontSee('Water Leakage in Kitchen');

        // Search by flat number lowercase
        Livewire::actingAs($this->admin)
            ->test(MaintenanceRequestList::class)
            ->set('search', '4a')
            ->assertSee('Water Leakage in Kitchen')
            ->assertDontSee('Other Building Leakage In Kitchen')
            ->assertDontSee('Electrical Short Circuit');

        // Whitespace only search shows building results without error
        Livewire::actingAs($this->admin)
            ->test(MaintenanceRequestList::class)
            ->set('search', '   ')
            ->assertSee('Water Leakage in Kitchen')
            ->assertSee('Electrical Short Circuit')
            ->assertDontSee('Other Building Leakage In Kitchen');
    }

    public function test_notice_search_is_case_insensitive_and_matches_title_and_content(): void
    {
        Notice::factory()->create([
            'building_id' => $this->building->id,
            'title' => 'Generator Maintenance Tomorrow',
            'content' => 'Elevator will run on emergency generator power.',
        ]);

        Notice::factory()->create([
            'building_id' => $this->building->id,
            'title' => 'Annual General Meeting',
            'content' => 'Community hall reserved on Friday evening.',
        ]);

        // Search lowercase title
        Livewire::actingAs($this->admin)
            ->test(NoticeList::class)
            ->set('search', 'generator')
            ->assertSee('Generator Maintenance Tomorrow')
            ->assertDontSee('Annual General Meeting');

        // Search uppercase content
        Livewire::actingAs($this->admin)
            ->test(NoticeList::class)
            ->set('search', 'COMMUNITY')
            ->assertSee('Annual General Meeting')
            ->assertDontSee('Generator Maintenance Tomorrow');
    }

    public function test_flat_search_matches_number_owner_name_and_phone(): void
    {
        $floor = Floor::factory()->create(['building_id' => $this->building->id]);

        $ownerA = Owner::factory()->create([
            'name' => 'John Doe',
            'phone' => '01711122233',
        ]);
        $ownerB = Owner::factory()->create([
            'name' => 'Jane Smith',
            'phone' => '01899988877',
        ]);

        Flat::factory()->create([
            'building_id' => $this->building->id,
            'floor_id' => $floor->id,
            'owner_id' => $ownerA->id,
            'number' => 'A-101',
        ]);

        Flat::factory()->create([
            'building_id' => $this->building->id,
            'floor_id' => $floor->id,
            'owner_id' => $ownerB->id,
            'number' => 'B-202',
        ]);

        // Search lowercase flat number
        Livewire::actingAs($this->admin)
            ->test(FlatList::class)
            ->set('search', 'a-101')
            ->assertSee('A-101')
            ->assertDontSee('B-202');

        // Search owner name (mixed case)
        Livewire::actingAs($this->admin)
            ->test(FlatList::class)
            ->set('search', 'smith')
            ->assertSee('B-202')
            ->assertDontSee('A-101');

        // Search owner phone
        Livewire::actingAs($this->admin)
            ->test(FlatList::class)
            ->set('search', '111222')
            ->assertSee('A-101')
            ->assertDontSee('B-202');
    }

    public function test_owner_search_matches_name_phone_and_email(): void
    {
        Owner::factory()->create([
            'name' => 'Alice Johnson',
            'phone' => '01511223344',
            'email' => 'alice@domain.test',
        ]);

        Owner::factory()->create([
            'name' => 'Bob Williams',
            'phone' => '01655667788',
            'email' => 'bob@domain.test',
        ]);

        // Search name lowercase
        Livewire::actingAs($this->admin)
            ->test(OwnerList::class)
            ->set('search', 'alice')
            ->assertSee('Alice Johnson')
            ->assertDontSee('Bob Williams');

        // Search phone
        Livewire::actingAs($this->admin)
            ->test(OwnerList::class)
            ->set('search', '556677')
            ->assertSee('Bob Williams')
            ->assertDontSee('Alice Johnson');

        // Search email
        Livewire::actingAs($this->admin)
            ->test(OwnerList::class)
            ->set('search', 'ALICE@DOMAIN')
            ->assertSee('Alice Johnson')
            ->assertDontSee('Bob Williams');
    }

    public function test_vendor_search_matches_name_phone_and_email(): void
    {
        Vendor::factory()->create([
            'name' => 'Otis Elevator Services',
            'phone' => '01911223344',
            'email' => 'support@otis.test',
            'is_active' => true,
        ]);

        Vendor::factory()->create([
            'name' => 'Aqua Plumbing Solutions',
            'phone' => '01399887766',
            'email' => 'info@aqua.test',
            'is_active' => true,
        ]);

        // Search name lowercase
        Livewire::actingAs($this->admin)
            ->test(VendorList::class)
            ->set('search', 'otis')
            ->assertSee('Otis Elevator Services')
            ->assertDontSee('Aqua Plumbing Solutions');

        // Search phone
        Livewire::actingAs($this->admin)
            ->test(VendorList::class)
            ->set('search', '998877')
            ->assertSee('Aqua Plumbing Solutions')
            ->assertDontSee('Otis Elevator Services');

        // Search email uppercase
        Livewire::actingAs($this->admin)
            ->test(VendorList::class)
            ->set('search', 'SUPPORT@OTIS')
            ->assertSee('Otis Elevator Services')
            ->assertDontSee('Aqua Plumbing Solutions');
    }
}
