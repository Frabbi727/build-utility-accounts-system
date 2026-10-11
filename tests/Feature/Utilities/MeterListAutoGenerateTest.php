<?php

namespace Tests\Feature\Utilities;

use App\Enums\AccountCode;
use App\Enums\Role;
use App\Livewire\Utilities\MeterList;
use App\Models\Building;
use App\Models\Flat;
use App\Models\FlatUtilityConfig;
use App\Models\Floor;
use App\Models\Meter;
use App\Models\User;
use App\Models\Utility;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MeterListAutoGenerateTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Floor $floor1;

    private Floor $floor2;

    private Flat $flat101;

    private Flat $flat102;

    private Flat $flat201;

    private Utility $elec;

    private Utility $gas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->create();
        app(CurrentBuilding::class)->set($this->building->id);

        $this->floor1 = Floor::factory()->create(['building_id' => $this->building->id, 'name' => '1st Floor', 'level' => 1]);
        $this->floor2 = Floor::factory()->create(['building_id' => $this->building->id, 'name' => '2nd Floor', 'level' => 2]);

        $this->flat101 = Flat::factory()->create(['building_id' => $this->building->id, 'floor_id' => $this->floor1->id, 'number' => '101', 'is_active' => true]);
        $this->flat102 = Flat::factory()->create(['building_id' => $this->building->id, 'floor_id' => $this->floor1->id, 'number' => '102', 'is_active' => true]);
        $this->flat201 = Flat::factory()->create(['building_id' => $this->building->id, 'floor_id' => $this->floor2->id, 'number' => '201', 'is_active' => true]);

        $incomeAccountId = app(JournalService::class)->account(AccountCode::ServiceChargeIncome)->id;

        $this->elec = Utility::factory()->electricity()->create([
            'building_id' => $this->building->id,
            'name' => 'Electricity',
            'account_id' => $incomeAccountId,
            'is_active' => true,
        ]);

        $this->gas = Utility::factory()->gas()->create([
            'building_id' => $this->building->id,
            'name' => 'Gas',
            'account_id' => $incomeAccountId,
            'is_active' => true,
        ]);
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role->value]);

        return $user;
    }

    public function test_an_accountant_or_admin_can_open_the_bulk_meter_generation_wizard(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(MeterList::class)
            ->assertSet('showAutoGenerateModal', false)
            ->call('startAutoGenerate')
            ->assertSet('showAutoGenerateModal', true)
            ->assertSet('scope', 'all')
            ->assertCount('selectedUtilityIds', 2)
            ->assertCount('selectedFlatIds', 3)
            ->assertNotSet('previewSummary', null);
    }

    public function test_testing_scoping_options_all_by_floor_and_selected_flats(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(MeterList::class)
            ->call('startAutoGenerate')
            ->assertSet('scope', 'all')
            // In scope=all, all 3 flats, 2 utilities => 6 eligible meters
            ->assertSet('previewSummary.totalFlatsCount', 3)
            ->assertSet('previewSummary.eligibleMetersCount', 6)

            // Change scope to floor
            ->set('scope', 'floor')
            ->set('scopeFloorId', $this->floor1->id)
            ->assertSet('previewSummary.totalFlatsCount', 2)
            ->assertSet('previewSummary.eligibleMetersCount', 4)

            // Change scope to selected flats
            ->set('scope', 'selected')
            ->set('selectedFlatIds', [$this->flat201->id])
            ->call('updatePreview')
            ->assertSet('previewSummary.totalFlatsCount', 1)
            ->assertSet('previewSummary.eligibleMetersCount', 2);
    }

    public function test_preview_calculation_reflects_eligible_skipped_excluded_and_inactive_warnings(): void
    {
        // Create an existing active meter for flat 101 electricity
        Meter::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat101->id,
            'utility_id' => $this->elec->id,
            'is_active' => true,
            'meter_no' => 'ELEC-101',
        ]);

        // Create an existing inactive meter for flat 102 gas
        Meter::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat102->id,
            'utility_id' => $this->gas->id,
            'is_active' => false,
            'meter_no' => 'OLD-GAS-102',
        ]);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(MeterList::class)
            ->call('startAutoGenerate')
            ->assertSet('previewSummary.totalFlatsCount', 3)
            // 3 flats * 2 utilities = 6. 2 existing meters => 4 eligible, 2 skipped
            ->assertSet('previewSummary.eligibleMetersCount', 4)
            ->assertSet('previewSummary.skippedMetersCount', 2)
            ->assertCount('previewSummary.inactiveWarnings', 1);
    }

    public function test_flat_inclusion_exclusion_toggle(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(MeterList::class)
            ->call('startAutoGenerate')
            ->assertSet('previewSummary.eligibleMetersCount', 6)
            ->assertSet('previewSummary.excludedFlatsCount', 0)

            // Toggle exclusion for flat 101 electricity
            ->call('toggleFlatUtility', $this->flat101->id, $this->elec->id)
            ->assertSet("flatUtilityOverrides.{$this->flat101->id}-{$this->elec->id}", false)
            ->assertSet('previewSummary.eligibleMetersCount', 5)
            ->assertSet('previewSummary.excludedFlatsCount', 1)

            // Toggle back on
            ->call('toggleFlatUtility', $this->flat101->id, $this->elec->id)
            ->assertSet("flatUtilityOverrides.{$this->flat101->id}-{$this->elec->id}", true)
            ->assertSet('previewSummary.eligibleMetersCount', 6)
            ->assertSet('previewSummary.excludedFlatsCount', 0);
    }

    public function test_execution_generates_meters_resets_modal_state_and_notifies(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(MeterList::class)
            ->call('startAutoGenerate')
            ->set('utilityPrefixes', [
                $this->elec->id => 'ELEC',
                $this->gas->id => 'GAS',
            ])
            ->call('executeAutoGenerate')
            ->assertSet('showAutoGenerateModal', false)
            ->assertNotSet('notice', null);

        // Verify meters were created in the database
        $this->assertSame(6, Meter::where('building_id', $this->building->id)->count());
        $this->assertDatabaseHas('meters', [
            'building_id' => $this->building->id,
            'flat_id' => $this->flat101->id,
            'utility_id' => $this->elec->id,
            'meter_no' => 'ELEC-101',
        ]);
        $this->assertDatabaseHas('meters', [
            'building_id' => $this->building->id,
            'flat_id' => $this->flat201->id,
            'utility_id' => $this->gas->id,
            'meter_no' => 'GAS-201',
        ]);
    }

    public function test_committee_member_or_unauthorized_user_cannot_trigger_execute_auto_generate(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Committee))
            ->test(MeterList::class)
            ->call('executeAutoGenerate')
            ->assertForbidden();

        $this->assertSame(0, Meter::count());
    }

    public function test_translations_render_cleanly_in_both_en_and_bn(): void
    {
        app()->setLocale('en');
        $enString = __('utilities.auto_generate_meters');
        $this->assertSame('Auto-generate meters', $enString);
        $this->assertIsString(__('utilities.meters_generated_summary', ['created' => 2, 'skipped' => 1, 'excluded' => 0]));

        app()->setLocale('bn');
        $bnString = __('utilities.auto_generate_meters');
        $this->assertSame('স্বয়ংক্রিয় মিটার তৈরি', $bnString);
        $this->assertIsString(__('utilities.meters_generated_summary', ['created' => 2, 'skipped' => 1, 'excluded' => 0]));

        app()->setLocale('en');
    }

    public function test_empty_utilities_or_empty_flats_validation_in_execute_auto_generate(): void
    {
        // 1. Empty utilities selected
        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(MeterList::class)
            ->call('startAutoGenerate')
            ->set('selectedUtilityIds', [])
            ->call('executeAutoGenerate')
            ->assertSet('noticeType', 'error')
            ->assertSet('notice', __('utilities.no_utilities_selected'));

        $this->assertSame(0, Meter::count());

        // 2. Empty flats selected
        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(MeterList::class)
            ->call('startAutoGenerate')
            ->set('scope', 'selected')
            ->set('selectedFlatIds', [])
            ->call('executeAutoGenerate')
            ->assertSet('noticeType', 'error')
            ->assertSet('notice', __('utilities.no_flats_selected'));

        $this->assertSame(0, Meter::count());
    }

    public function test_updated_selected_flat_ids_reactively_updates_preview(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(MeterList::class)
            ->call('startAutoGenerate')
            ->set('scope', 'selected')
            // setting selectedFlatIds triggers updatedSelectedFlatIds() automatically
            ->set('selectedFlatIds', [$this->flat101->id])
            ->assertSet('previewSummary.totalFlatsCount', 1)
            ->assertSet('previewSummary.eligibleMetersCount', 2)
            ->set('selectedFlatIds', [$this->flat101->id, $this->flat102->id])
            ->assertSet('previewSummary.totalFlatsCount', 2)
            ->assertSet('previewSummary.eligibleMetersCount', 4);
    }

    public function test_preloads_saved_flat_utility_config_state_in_wizard(): void
    {
        FlatUtilityConfig::create([
            'flat_id' => $this->flat101->id,
            'utility_id' => $this->elec->id,
            'is_enabled' => false,
        ]);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(MeterList::class)
            ->call('startAutoGenerate')
            ->assertSet("flatUtilityOverrides.{$this->flat101->id}-{$this->elec->id}", false)
            ->assertSet('previewSummary.excludedFlatsCount', 1)
            ->assertSet('previewSummary.eligibleMetersCount', 5);
    }
}
