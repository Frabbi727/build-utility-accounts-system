<?php

namespace Tests\Feature\Billing;

use App\Models\Building;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Services\Billing\GenerateMonthlyBills;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GenerateMonthlyBillsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_it_charges_a_flat_rate_regardless_of_size(): void
    {
        $building = Building::factory()->flatRate('2500.00')->create();
        Flat::factory()->for($building)->create(['size_sqft' => '800.00']);
        Flat::factory()->for($building)->create(['size_sqft' => '2000.00']);

        $bills = app(GenerateMonthlyBills::class)->handle($building, Carbon::parse('2026-08-01'));

        $this->assertCount(2, $bills);
        $this->assertSame(['2500.00', '2500.00'], $bills->pluck('total_amount')->map(fn ($a): string => (string) $a)->all());
    }

    public function test_it_charges_per_square_foot_when_configured(): void
    {
        $building = Building::factory()->perSqft('3.50')->create();
        Flat::factory()->for($building)->create(['size_sqft' => '1200.00']);

        $bills = app(GenerateMonthlyBills::class)->handle($building, Carbon::parse('2026-08-01'));

        $this->assertSame('4200.00', (string) $bills->first()->total_amount);
    }

    public function test_rerunning_the_same_month_does_not_double_bill(): void
    {
        $building = Building::factory()->flatRate('3000.00')->create();
        Flat::factory()->count(2)->for($building)->create();

        app(GenerateMonthlyBills::class)->handle($building, Carbon::parse('2026-08-01'));
        $second = app(GenerateMonthlyBills::class)->handle($building, Carbon::parse('2026-08-01'));

        $this->assertCount(0, $second);
        $this->assertSame(2, ServiceChargeBill::count());
    }

    public function test_it_skips_inactive_flats(): void
    {
        $building = Building::factory()->flatRate('3000.00')->create();
        Flat::factory()->for($building)->create();
        Flat::factory()->for($building)->create(['is_active' => false]);

        $bills = app(GenerateMonthlyBills::class)->handle($building, Carbon::parse('2026-08-01'));

        $this->assertCount(1, $bills);
    }

    public function test_the_bill_is_linked_to_its_journal_entry_as_the_source(): void
    {
        $building = Building::factory()->flatRate('3000.00')->create();
        Flat::factory()->for($building)->create();

        $bill = app(GenerateMonthlyBills::class)->handle($building, Carbon::parse('2026-08-01'))->first();

        $entry = $bill->journalEntries()->firstOrFail();

        $this->assertSame($bill->id, $entry->source_id);
        $this->assertSame(ServiceChargeBill::class, $entry->source_type);
    }

    public function test_the_due_date_follows_the_building_configuration(): void
    {
        $building = Building::factory()->flatRate('3000.00')->create(['due_day_of_month' => 15]);
        Flat::factory()->for($building)->create();

        $bill = app(GenerateMonthlyBills::class)->handle($building, Carbon::parse('2026-08-01'))->first();

        $this->assertSame('2026-08-15', $bill->due_date->toDateString());
    }
}
