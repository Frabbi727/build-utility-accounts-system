<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\SpotlightSearch;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\User;
use App\Services\Reporting\LedgerReports;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SpotlightSearchTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->building = Building::factory()->create(['name' => 'Sunset Towers']);
        app(CurrentBuilding::class)->set($this->building->id);

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole(Role::Accountant->value);
    }

    public function test_spotlight_can_be_opened_and_closed(): void
    {
        Livewire::actingAs($this->accountant)
            ->test(SpotlightSearch::class)
            ->assertSet('isOpen', false)
            ->call('open')
            ->assertSet('isOpen', true)
            ->call('close')
            ->assertSet('isOpen', false);
    }

    public function test_spotlight_searches_flats_by_number_owner_and_phone(): void
    {
        $owner1 = Owner::factory()->create(['name' => 'Rahim Chowdhury', 'phone' => '01711234567']);
        $owner2 = Owner::factory()->create(['name' => 'Karim Ullah', 'phone' => '01819000000']);

        $flat1 = Flat::factory()->for($this->building)->for($owner1)->create(['number' => '4B']);
        $flat2 = Flat::factory()->for($this->building)->for($owner2)->create(['number' => '2A']);

        // Search by flat number
        Livewire::actingAs($this->accountant)
            ->test(SpotlightSearch::class)
            ->set('query', '4B')
            ->assertSee('4B')
            ->assertSee('Rahim Chowdhury')
            ->assertDontSee('Karim Ullah');

        // Search by owner name
        Livewire::actingAs($this->accountant)
            ->test(SpotlightSearch::class)
            ->set('query', 'Karim')
            ->assertSee('2A')
            ->assertSee('Karim Ullah')
            ->assertDontSee('Rahim Chowdhury');

        // Search by phone
        Livewire::actingAs($this->accountant)
            ->test(SpotlightSearch::class)
            ->set('query', '01711')
            ->assertSee('4B')
            ->assertSee('Rahim Chowdhury')
            ->assertDontSee('Karim Ullah');
    }

    public function test_spotlight_scopes_search_to_current_building(): void
    {
        $otherBuilding = Building::factory()->create(['name' => 'Other Heights']);
        $otherOwner = Owner::factory()->create(['name' => 'Other Owner']);
        Flat::factory()->for($otherBuilding)->for($otherOwner)->create(['number' => '99Z']);

        Flat::factory()->for($this->building)->create(['number' => '101']);

        Livewire::actingAs($this->accountant)
            ->test(SpotlightSearch::class)
            ->set('query', '99Z')
            ->assertDontSee('Other Owner')
            ->set('query', '101')
            ->assertSee('101');
    }

    public function test_spotlight_shows_navigation_shortcuts_for_staff(): void
    {
        Livewire::actingAs($this->accountant)
            ->test(SpotlightSearch::class)
            ->set('query', '')
            ->assertSee(__('nav.dashboard'))
            ->assertSee(__('nav.reports'));
    }

    public function test_spotlight_searches_navigation_shortcuts_dynamically(): void
    {
        Livewire::actingAs($this->accountant)
            ->test(SpotlightSearch::class)
            ->set('query', __('nav.flats'))
            ->assertSee(__('nav.flats'))
            ->assertDontSee(__('nav.dashboard'));
    }

    public function test_spotlight_clear_resets_query_and_error(): void
    {
        Livewire::actingAs($this->accountant)
            ->test(SpotlightSearch::class)
            ->set('query', 'something')
            ->assertSet('query', 'something')
            ->call('clear')
            ->assertSet('query', '')
            ->assertSet('errorMessage', null);
    }

    public function test_spotlight_renders_empty_state_when_no_match(): void
    {
        Livewire::actingAs($this->accountant)
            ->test(SpotlightSearch::class)
            ->set('query', 'XYZNONEXISTENT999')
            ->assertSee(__('billing.no_flats'))
            ->assertSee('XYZNONEXISTENT999');
    }

    public function test_spotlight_handles_dues_calculation_failure_gracefully(): void
    {
        $mockReports = $this->createMock(LedgerReports::class);
        $mockReports->method('outstandingByFlat')
            ->willThrowException(new \RuntimeException('Ledger connection failure'));

        $this->app->instance(LedgerReports::class, $mockReports);

        Flat::factory()->for($this->building)->create(['number' => '5A']);

        Livewire::actingAs($this->accountant)
            ->test(SpotlightSearch::class)
            ->set('query', '5A')
            ->assertSee('5A')
            ->assertSee('Could not calculate current dues');
    }

    public function test_spotlight_opens_via_dispatched_event(): void
    {
        Livewire::actingAs($this->accountant)
            ->test(SpotlightSearch::class)
            ->assertSet('isOpen', false)
            ->dispatch('open-spotlight')
            ->assertSet('isOpen', true);
    }
}
