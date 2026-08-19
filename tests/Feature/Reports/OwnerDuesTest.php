<?php

namespace Tests\Feature\Reports;

use App\Enums\AccountCode;
use App\Enums\PaymentMethod;
use App\Models\Building;
use App\Models\Flat;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use App\Services\JournalService;
use App\Services\Reporting\LedgerReports;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OwnerDuesTest extends TestCase
{
    use RefreshDatabase;

    private LedgerReports $reports;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->reports = app(LedgerReports::class);
        $this->building = Building::factory()->flatRate('3000.00')->create(['due_day_of_month' => 10]);
    }

    public function test_a_bill_still_inside_its_first_month_sits_in_the_current_bucket(): void
    {
        Flat::factory()->for($this->building)->create();

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        $row = $this->reports->agingByFlat(Carbon::parse('2026-08-20'))->sole();

        $this->assertSame('3000.00', $row['outstanding']);
        $this->assertSame('3000.00', $row['current']);
        $this->assertSame('0.00', $row['days_90_plus']);
    }

    public function test_an_old_bill_ages_into_the_ninety_day_bucket(): void
    {
        Flat::factory()->for($this->building)->create();

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-01-01'));

        $row = $this->reports->agingByFlat(Carbon::parse('2026-08-20'))->sole();

        $this->assertSame('3000.00', $row['days_90_plus']);
        $this->assertSame('0.00', $row['current']);
    }

    public function test_bills_of_different_ages_land_in_different_buckets(): void
    {
        Flat::factory()->for($this->building)->create();

        foreach (['2026-05-01', '2026-06-01', '2026-08-01'] as $month) {
            app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse($month));
        }

        $row = $this->reports->agingByFlat(Carbon::parse('2026-08-20'))->sole();

        // Due dates 2026-05-10, 2026-06-10, 2026-08-10 → 102, 71 and 10 days overdue.
        $this->assertSame('9000.00', $row['outstanding']);
        $this->assertSame('3000.00', $row['days_90_plus']);
        $this->assertSame('3000.00', $row['days_61_90']);
        $this->assertSame('3000.00', $row['current']);
        $this->assertSame('0.00', $row['days_31_60']);
    }

    public function test_the_buckets_always_add_up_to_the_ledger_outstanding(): void
    {
        $flat = Flat::factory()->for($this->building)->create();

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-06-01'));
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-07-01'));
        app(RecordPayment::class)->handle($flat, '4500.00', PaymentMethod::Cash, Carbon::parse('2026-07-20'));

        $row = $this->reports->agingByFlat(Carbon::parse('2026-08-20'))->sole();

        $ledger = app(JournalService::class)->balanceFor(
            app(JournalService::class)->account(AccountCode::ServiceChargeReceivable),
            $flat->id,
        );

        $buckets = ['current', 'days_31_60', 'days_61_90', 'days_90_plus'];
        $sum = array_reduce($buckets, fn (string $carry, string $key): string => bcadd($carry, $row[$key], 2), '0.00');

        $this->assertSame('1500.00', $ledger);
        $this->assertSame($ledger, $row['outstanding']);
        $this->assertSame($ledger, $sum);
    }

    public function test_a_fully_paid_flat_drops_off_the_report(): void
    {
        $flat = Flat::factory()->for($this->building)->create();

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));
        app(RecordPayment::class)->handle($flat, '3000.00', PaymentMethod::Cash, Carbon::parse('2026-08-15'));

        $this->assertTrue($this->reports->agingByFlat(Carbon::parse('2026-08-20'))->isEmpty());
    }

    public function test_a_later_payment_does_not_reduce_an_earlier_as_of_date(): void
    {
        $flat = Flat::factory()->for($this->building)->create();

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));
        app(RecordPayment::class)->handle($flat, '3000.00', PaymentMethod::Cash, Carbon::parse('2026-09-15'));

        $this->assertSame('3000.00', $this->reports->agingByFlat(Carbon::parse('2026-08-31'))->sole()['outstanding']);
        $this->assertTrue($this->reports->agingByFlat(Carbon::parse('2026-09-30'))->isEmpty());
    }
}
