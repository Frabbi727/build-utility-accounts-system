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

class LedgerDrillDownTest extends TestCase
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

        $this->building = Building::factory()->flatRate('3000.00')->create();
        app(CurrentBuilding::class)->set($this->building->id);

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole(Role::Accountant->value);

        $this->flat = Flat::factory()->for($this->building)->create();
    }

    public function test_ledger_drill_down_opens_and_renders_journal_movements(): void
    {
        $this->actingAs($this->accountant);

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));
        app(RecordPayment::class)->handle(
            $this->flat,
            '3000.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-15')
        );

        $receivable = $this->journal->account(AccountCode::ServiceChargeReceivable);

        Livewire::test(TrialBalance::class)
            ->call('openDrillDown', $receivable->id)
            ->assertSet('showDrillDown', true)
            ->assertSet('drillDownAccountId', $receivable->id)
            ->assertSee($this->flat->number)
            ->assertSee('3,000.00')
            ->call('closeDrillDown')
            ->assertSet('showDrillDown', false);
    }
}
