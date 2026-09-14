<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentMethod;
use App\Enums\ReadingStatus;
use App\Models\Building;
use App\Models\Flat;
use App\Models\JournalEntry;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\ServiceChargeBill;
use App\Models\Utility;
use App\Models\UtilityTariff;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use App\Services\Billing\SimulateMonthlyBills;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SimulateMonthlyBillsTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat1;

    private Flat $flat2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->building = Building::factory()->flatRate('2500.00')->create(['due_day_of_month' => 10]);
        $this->flat1 = Flat::factory()->for($this->building)->create(['number' => '101']);
        $this->flat2 = Flat::factory()->for($this->building)->create(['number' => '102']);
    }

    public function test_simulation_calculates_exact_prospective_amounts_without_persisting_bills_or_journal_entries(): void
    {
        $month = Carbon::parse('2026-08-01');

        $result = app(SimulateMonthlyBills::class)->handle($this->building, $month);

        $this->assertSame(2, $result->totalActiveFlats);
        $this->assertSame(2, $result->totalFlatsToBill);
        $this->assertSame(0, $result->alreadyBilledCount);
        $this->assertSame('5000.00', $result->estimatedTotalAmount);
        $this->assertSame('5000.00', $result->estimatedChargeHeadsAmount);
        $this->assertSame('0.00', $result->estimatedAdvancesAmount);
        $this->assertSame('5000.00', $result->estimatedNetReceivable);
        $this->assertSame('2026-08-10', $result->dueDate->toDateString());

        // Assert 0 database mutations occurred
        $this->assertSame(0, ServiceChargeBill::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_simulation_accounts_for_advance_drawdowns(): void
    {
        // Record advance of 1000 for flat 1
        app(RecordPayment::class)->handle(
            $this->flat1,
            '1000.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-07-15'),
            'ADV-001',
        );

        $month = Carbon::parse('2026-08-01');
        $result = app(SimulateMonthlyBills::class)->handle($this->building, $month);

        $this->assertSame('5000.00', $result->estimatedTotalAmount);
        $this->assertSame('1000.00', $result->estimatedAdvancesAmount);
        $this->assertSame('4000.00', $result->estimatedNetReceivable);

        $flat1Sim = collect($result->flats)->firstWhere('flat.id', $this->flat1->id);
        $this->assertNotNull($flat1Sim);
        $this->assertSame('1000.00', $flat1Sim->availableAdvance);
        $this->assertSame('1000.00', $flat1Sim->estimatedAdvanceDrawdown);
        $this->assertSame('1500.00', $flat1Sim->netReceivable);
    }

    public function test_simulation_flags_unconfirmed_readings_and_already_billed_flats(): void
    {
        $utility = Utility::factory()->create(['building_id' => $this->building->id]);
        $tariff = UtilityTariff::factory()->for($utility)->flatRate('10.00')->create();
        $meter = Meter::factory()->for($this->flat1)->create([
            'building_id' => $this->building->id,
            'utility_id' => $utility->id,
        ]);

        MeterReading::factory()->for($meter)->create([
            'billing_month' => '2026-08-01',
            'status' => ReadingStatus::Draft,
        ]);

        // Bill flat 2 already
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        $result = app(SimulateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        $this->assertSame(1, $result->pendingDraftReadingsCount);
        $this->assertSame(2, $result->alreadyBilledCount);
        $this->assertSame(0, $result->totalFlatsToBill);
        $this->assertTrue($result->hasWarnings());

        $flat1Sim = collect($result->flats)->firstWhere('flat.id', $this->flat1->id);
        $this->assertTrue($flat1Sim->hasWarnings());
        $this->assertTrue($flat1Sim->isAlreadyBilled);
    }
}
