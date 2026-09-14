<?php

namespace Tests\Feature\Reports;

use App\Enums\AccountCode;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Livewire\TrialBalance;
use App\Models\Building;
use App\Models\Flat;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class TrialBalanceDateRangeTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    private User $accountant;

    private JournalService $journal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->journal = app(JournalService::class);
        $this->building = Building::factory()->flatRate('2000.00')->create();
        app(CurrentBuilding::class)->set($this->building->id);

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole(Role::Accountant->value);

        $this->flat = Flat::factory()->for($this->building)->create();
    }

    public function test_trial_balance_displays_opening_and_period_movements_when_from_date_is_set(): void
    {
        $this->actingAs($this->accountant);

        // Month 1: July
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-07-01'));

        // Month 2: August (pay July bill and generate August)
        app(RecordPayment::class)->handle(
            $this->flat,
            '2000.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-05')
        );
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        // Inspect Trial Balance for August 1 to August 31
        Livewire::test(TrialBalance::class)
            ->set('from', '2026-08-01')
            ->set('to', '2026-08-31')
            ->assertSet('isRanged', true)
            ->assertSee('Opening Balance')
            ->assertSee('Closing Balance')
            ->assertSee('2,000.00');
    }
}
