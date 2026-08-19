<?php

namespace Tests\Feature\Utilities;

use App\Enums\AccountCode;
use App\Enums\Role;
use App\Livewire\Utilities\MeterList;
use App\Livewire\Utilities\UtilityList;
use App\Livewire\Utilities\UtilityTariffList;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityTariff;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class UtilityScreensTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->create();
        $this->flat = Flat::factory()->for($this->building)->create();

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

    private function utility(): Utility
    {
        return Utility::factory()->electricity()->create([
            'building_id' => $this->building->id,
            'account_id' => $this->incomeAccountId(),
        ]);
    }

    public function test_an_accountant_can_add_a_utility(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(UtilityList::class)
            ->call('create')
            ->set('name', 'Electricity')
            ->set('nameBn', 'বিদ্যুৎ')
            ->set('unitLabel', 'kWh')
            ->set('accountId', $this->incomeAccountId())
            ->call('save')
            ->assertHasNoErrors();

        $utility = Utility::firstOrFail();

        $this->assertSame('Electricity', $utility->name);
        $this->assertSame('kWh', $utility->unit_label);
        $this->assertSame($this->building->id, $utility->building_id);
    }

    public function test_a_committee_member_cannot_add_a_utility(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Committee))
            ->test(UtilityList::class)
            ->call('create')
            ->assertForbidden();
    }

    public function test_a_utility_cannot_be_credited_to_an_expense_account(): void
    {
        // Recovering consumption is income. Crediting the expense account the building
        // was billed on would quietly understate expenses in every report.
        $expenseAccount = app(JournalService::class)->account(AccountCode::CommonElectricity)->id;

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(UtilityList::class)
            ->call('create')
            ->set('name', 'Electricity')
            ->set('unitLabel', 'kWh')
            ->set('accountId', $expenseAccount)
            ->call('save')
            ->assertHasErrors('accountId');

        $this->assertSame(0, Utility::count());
    }

    public function test_a_utility_name_is_unique_within_the_building(): void
    {
        $this->utility();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(UtilityList::class)
            ->call('create')
            ->set('name', 'Electricity')
            ->set('unitLabel', 'kWh')
            ->set('accountId', $this->incomeAccountId())
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_another_buildings_utility_cannot_be_edited(): void
    {
        $other = Utility::factory()->create(['account_id' => $this->incomeAccountId()]);

        // findRecord() narrows by CurrentBuilding, so the record is simply not there.
        // Over HTTP that surfaces as a 404; here the query exception is the proof.
        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(UtilityList::class)
            ->call('edit', $other->id);
    }

    public function test_a_meter_can_be_left_common_by_omitting_the_unit(): void
    {
        $utility = $this->utility();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(MeterList::class)
            ->call('create')
            ->set('utilityId', $utility->id)
            ->set('flatId', null)
            ->set('meterNo', 'MAIN-01')
            ->call('save')
            ->assertHasNoErrors();

        $meter = Meter::firstOrFail();

        $this->assertTrue($meter->isCommon());
        $this->assertSame($this->building->id, $meter->building_id);
    }

    public function test_a_meter_cannot_be_attached_to_another_buildings_flat(): void
    {
        $utility = $this->utility();
        $foreignFlat = Flat::factory()->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(MeterList::class)
            ->call('create')
            ->set('utilityId', $utility->id)
            ->set('flatId', $foreignFlat->id)
            ->set('meterNo', 'MTR-99')
            ->call('save')
            ->assertHasErrors('flatId');

        $this->assertSame(0, Meter::count());
    }

    public function test_a_meter_number_is_unique_within_its_utility(): void
    {
        $utility = $this->utility();
        Meter::factory()->create([
            'building_id' => $this->building->id,
            'utility_id' => $utility->id,
            'flat_id' => $this->flat->id,
            'meter_no' => 'MTR-01',
        ]);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(MeterList::class)
            ->call('create')
            ->set('utilityId', $utility->id)
            ->set('flatId', $this->flat->id)
            ->set('meterNo', 'MTR-01')
            ->call('save')
            ->assertHasErrors('meterNo');
    }

    public function test_a_tariff_is_saved_with_its_slabs(): void
    {
        $utility = $this->utility();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(UtilityTariffList::class)
            ->call('create')
            ->set('utilityId', $utility->id)
            ->set('effectiveFrom', '2026-01-01')
            ->set('fixedCharge', '125')
            ->set('slabs', [
                ['from_unit' => '0', 'to_unit' => '75', 'rate_per_unit' => '4.85'],
                ['from_unit' => '75', 'to_unit' => null, 'rate_per_unit' => '6.63'],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $tariff = UtilityTariff::with('slabs')->firstOrFail();

        $this->assertCount(2, $tariff->slabs);
        $this->assertSame('125.00', (string) $tariff->fixed_charge);
        $this->assertSame('4.8500', (string) $tariff->slabs->first()->rate_per_unit);
        $this->assertNull($tariff->slabs->last()->to_unit);
    }

    public function test_a_tariff_with_a_gap_between_slabs_is_refused(): void
    {
        $utility = $this->utility();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(UtilityTariffList::class)
            ->call('create')
            ->set('utilityId', $utility->id)
            ->set('effectiveFrom', '2026-01-01')
            ->set('slabs', [
                ['from_unit' => '0', 'to_unit' => '75', 'rate_per_unit' => '4.85'],
                ['from_unit' => '100', 'to_unit' => null, 'rate_per_unit' => '6.63'],
            ])
            ->call('save')
            ->assertHasErrors('slabs');

        $this->assertSame(0, UtilityTariff::count());
    }

    public function test_a_new_tariff_closes_off_the_one_it_supersedes(): void
    {
        $utility = $this->utility();
        $old = UtilityTariff::factory()->flatRate('8.5000')->create([
            'utility_id' => $utility->id,
            'effective_from' => Carbon::parse('2026-01-01'),
        ]);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(UtilityTariffList::class)
            ->call('create')
            ->set('utilityId', $utility->id)
            ->set('effectiveFrom', '2026-08-01')
            ->set('slabs', [['from_unit' => '0', 'to_unit' => null, 'rate_per_unit' => '12']])
            ->call('save')
            ->assertHasNoErrors();

        // Closed the day before the new one starts, so no date is claimed twice.
        $this->assertSame('2026-07-31', $old->refresh()->effective_to->toDateString());
        $this->assertSame(
            '12.0000',
            (string) $utility->tariffOn(Carbon::parse('2026-08-15'))->slabs->firstOrFail()->rate_per_unit,
        );
        $this->assertSame(
            '8.5000',
            (string) $utility->tariffOn(Carbon::parse('2026-07-15'))->slabs->firstOrFail()->rate_per_unit,
        );
    }

    public function test_a_tariff_that_has_priced_a_confirmed_reading_cannot_be_edited(): void
    {
        $utility = $this->utility();
        $tariff = UtilityTariff::factory()->flatRate('8.5000')->create([
            'utility_id' => $utility->id,
            'effective_from' => Carbon::parse('2026-01-01'),
        ]);
        $meter = Meter::factory()->create([
            'building_id' => $this->building->id,
            'utility_id' => $utility->id,
            'flat_id' => $this->flat->id,
        ]);
        MeterReading::factory()->confirmed($tariff)->create(['meter_id' => $meter->id]);

        $this->assertTrue($tariff->refresh()->isInUse());

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(UtilityTariffList::class)
            ->call('edit', $tariff->id)
            ->assertForbidden();
    }
}
