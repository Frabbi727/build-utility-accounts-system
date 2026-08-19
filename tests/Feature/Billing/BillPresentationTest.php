<?php

namespace Tests\Feature\Billing;

use App\Enums\AccountCode;
use App\Enums\Role;
use App\Livewire\FlatStatement;
use App\Livewire\GenerateBills;
use App\Models\Building;
use App\Models\CostDistribution;
use App\Models\Flat;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityTariff;
use App\Services\Billing\ConfirmReading;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class BillPresentationTest extends TestCase
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

    private function accountant(): User
    {
        $user = User::factory()->create();
        $user->syncRoles([Role::Accountant->value]);

        return $user;
    }

    public function test_a_metered_line_shows_its_quantity_and_rate_on_the_statement(): void
    {
        $utility = Utility::factory()->electricity()->create([
            'building_id' => $this->building->id,
            'account_id' => app(JournalService::class)->account(AccountCode::ServiceChargeIncome)->id,
        ]);
        UtilityTariff::factory()->flatRate('8.5000')->create([
            'utility_id' => $utility->id,
            'effective_from' => Carbon::parse('2026-01-01'),
        ]);
        $meter = Meter::factory()->create([
            'building_id' => $this->building->id,
            'utility_id' => $utility->id,
            'flat_id' => $this->flat->id,
        ]);
        app(ConfirmReading::class)->handle(MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'billing_month' => Carbon::parse('2026-08-01'),
            'reading_date' => Carbon::parse('2026-08-28'),
            'previous_reading' => '0.000',
            'current_reading' => '120.000',
            'consumption' => '120.000',
        ]));

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        // The whole point of storing quantity and rate on the line: an owner can check
        // the arithmetic without being told to trust the total.
        Livewire::actingAs($this->accountant())
            ->test(FlatStatement::class, ['flat' => $this->flat])
            ->assertSee('120 kWh')
            ->assertSee('8.5')
            ->assertSee('1,020.00');
    }

    public function test_the_generate_screen_warns_about_unconfirmed_readings(): void
    {
        $utility = Utility::factory()->electricity()->create([
            'building_id' => $this->building->id,
            'account_id' => app(JournalService::class)->account(AccountCode::ServiceChargeIncome)->id,
        ]);
        $meter = Meter::factory()->create([
            'building_id' => $this->building->id,
            'utility_id' => $utility->id,
            'flat_id' => $this->flat->id,
        ]);
        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'billing_month' => Carbon::parse('2026-08-01'),
        ]);

        // Generation is a one-way door per flat and month, so the screen has to say what
        // is about to be left behind.
        Livewire::actingAs($this->accountant())
            ->test(GenerateBills::class)
            ->set('month', '2026-08')
            ->assertViewHas('pending', fn (array $pending): bool => $pending['readings'] === 1);
    }

    public function test_the_generate_screen_warns_about_unapproved_shared_costs(): void
    {
        CostDistribution::factory()->create([
            'building_id' => $this->building->id,
            'recovery_account_id' => app(JournalService::class)->account(AccountCode::OtherIncome)->id,
            'billing_month' => Carbon::parse('2026-08-01'),
        ]);

        Livewire::actingAs($this->accountant())
            ->test(GenerateBills::class)
            ->set('month', '2026-08')
            ->assertViewHas('pending', fn (array $pending): bool => $pending['distributions'] === 1);
    }

    public function test_the_generate_screen_is_quiet_when_nothing_is_outstanding(): void
    {
        Livewire::actingAs($this->accountant())
            ->test(GenerateBills::class)
            ->set('month', '2026-08')
            ->assertViewHas('pending', fn (array $pending): bool => $pending === ['readings' => 0, 'distributions' => 0]);
    }
}
