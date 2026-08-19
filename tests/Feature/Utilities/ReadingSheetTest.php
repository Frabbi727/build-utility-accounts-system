<?php

namespace Tests\Feature\Utilities;

use App\Enums\AccountCode;
use App\Enums\ReadingStatus;
use App\Enums\Role;
use App\Livewire\Utilities\ReadingSheet;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityTariff;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ReadingSheetTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    private Utility $electricity;

    private Meter $meter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->create();
        $this->flat = Flat::factory()->for($this->building)->create();
        $this->electricity = Utility::factory()->electricity()->create([
            'building_id' => $this->building->id,
            'account_id' => app(JournalService::class)->account(AccountCode::ServiceChargeIncome)->id,
        ]);
        $this->meter = Meter::factory()->create([
            'building_id' => $this->building->id,
            'utility_id' => $this->electricity->id,
            'flat_id' => $this->flat->id,
            'digits' => 5,
            'initial_reading' => '1000.000',
        ]);

        app(CurrentBuilding::class)->set($this->building->id);
    }

    private function accountant(): User
    {
        $user = User::factory()->create();
        $user->syncRoles([Role::Accountant->value]);

        return $user;
    }

    private function tariff(string $rate = '8.5000'): UtilityTariff
    {
        return UtilityTariff::factory()->flatRate($rate)->create([
            'utility_id' => $this->electricity->id,
            'effective_from' => Carbon::parse('2026-01-01'),
        ]);
    }

    public function test_the_sheet_lists_a_row_per_active_meter_seeded_with_the_previous_value(): void
    {
        Livewire::actingAs($this->accountant())
            ->test(ReadingSheet::class)
            ->set('month', '2026-08')
            ->assertViewHas('previous', fn ($previous): bool => $previous[$this->meter->id] === '1000.000');
    }

    public function test_saving_a_reading_computes_and_stores_the_consumption(): void
    {
        Livewire::actingAs($this->accountant())
            ->test(ReadingSheet::class)
            ->set('month', '2026-08')
            ->set("rows.{$this->meter->id}.current", '1120.000')
            ->set("rows.{$this->meter->id}.date", '2026-08-28')
            ->call('save')
            ->assertHasNoErrors();

        $reading = MeterReading::firstOrFail();

        $this->assertSame('1000.000', (string) $reading->previous_reading);
        $this->assertSame('120.000', (string) $reading->consumption);
        $this->assertSame(ReadingStatus::Draft, $reading->status);
    }

    public function test_a_meter_that_rolled_over_counts_forward_not_backward(): void
    {
        $this->meter->update(['initial_reading' => '99990.000']);

        Livewire::actingAs($this->accountant())
            ->test(ReadingSheet::class)
            ->set('month', '2026-08')
            ->set("rows.{$this->meter->id}.current", '10.000')
            ->call('save')
            ->assertHasNoErrors();

        // A 5-digit meter reading 99990 then 10 recorded 20 units, not minus 99980.
        $this->assertSame('20.000', (string) MeterReading::firstOrFail()->consumption);
    }

    public function test_a_reading_wider_than_the_meters_dials_is_refused(): void
    {
        Livewire::actingAs($this->accountant())
            ->test(ReadingSheet::class)
            ->set('month', '2026-08')
            ->set("rows.{$this->meter->id}.current", '123456.000')
            ->call('save')
            ->assertHasErrors("rows.{$this->meter->id}.current");

        $this->assertSame(0, MeterReading::count());
    }

    public function test_confirming_stamps_the_tariff_and_makes_the_reading_billable(): void
    {
        $tariff = $this->tariff();
        $reading = MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
            'billing_month' => Carbon::parse('2026-08-01'),
            'reading_date' => Carbon::parse('2026-08-28'),
        ]);

        Livewire::actingAs($this->accountant())
            ->test(ReadingSheet::class)
            ->set('month', '2026-08')
            ->call('confirm', $reading->id)
            ->assertHasNoErrors();

        $reading->refresh();

        $this->assertSame(ReadingStatus::Confirmed, $reading->status);
        $this->assertSame($tariff->id, $reading->utility_tariff_id);
    }

    public function test_confirming_without_a_tariff_reports_an_error_rather_than_throwing(): void
    {
        $reading = MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
            'billing_month' => Carbon::parse('2026-08-01'),
            'reading_date' => Carbon::parse('2026-08-28'),
        ]);

        Livewire::actingAs($this->accountant())
            ->test(ReadingSheet::class)
            ->set('month', '2026-08')
            ->call('confirm', $reading->id)
            ->assertHasErrors('month');

        $this->assertSame(ReadingStatus::Draft, $reading->refresh()->status);
    }

    public function test_confirm_all_settles_every_draft_in_the_month(): void
    {
        $this->tariff();
        $second = Meter::factory()->create([
            'building_id' => $this->building->id,
            'utility_id' => $this->electricity->id,
            'flat_id' => Flat::factory()->for($this->building)->create()->id,
        ]);

        foreach ([$this->meter, $second] as $meter) {
            MeterReading::factory()->create([
                'meter_id' => $meter->id,
                'billing_month' => Carbon::parse('2026-08-01'),
                'reading_date' => Carbon::parse('2026-08-28'),
            ]);
        }

        Livewire::actingAs($this->accountant())
            ->test(ReadingSheet::class)
            ->set('month', '2026-08')
            ->call('confirmAll')
            ->assertHasNoErrors();

        $this->assertSame(2, MeterReading::where('status', ReadingStatus::Confirmed)->count());
    }

    public function test_a_billed_reading_is_left_untouched_by_a_later_save(): void
    {
        $reading = MeterReading::factory()->confirmed($this->tariff())->create([
            'meter_id' => $this->meter->id,
            'billing_month' => Carbon::parse('2026-08-01'),
            'reading_date' => Carbon::parse('2026-08-28'),
            'previous_reading' => '1000.000',
            'current_reading' => '1120.000',
            'consumption' => '120.000',
        ]);

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));
        $this->assertNotNull($reading->refresh()->applied_to_bill_id);

        // The sheet offers no input for a billed row, but a crafted request must not
        // rewrite history either: a posted bill is never edited.
        Livewire::actingAs($this->accountant())
            ->test(ReadingSheet::class)
            ->set('month', '2026-08')
            ->set("rows.{$this->meter->id}.current", '9999.000')
            ->call('save');

        $this->assertSame('120.000', (string) $reading->refresh()->consumption);
    }

    public function test_a_committee_member_cannot_save_readings(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([Role::Committee->value]);

        Livewire::actingAs($user)
            ->test(ReadingSheet::class)
            ->set('month', '2026-08')
            ->set("rows.{$this->meter->id}.current", '1120.000')
            ->call('save')
            ->assertForbidden();

        $this->assertSame(0, MeterReading::count());
    }

    public function test_readings_are_scoped_to_the_current_building(): void
    {
        $foreignMeter = Meter::factory()->create();

        Livewire::actingAs($this->accountant())
            ->test(ReadingSheet::class)
            ->set('month', '2026-08')
            ->assertViewHas('meters', fn ($meters): bool => $meters->count() === 1
                && ! $meters->contains('id', $foreignMeter->id));
    }
}
