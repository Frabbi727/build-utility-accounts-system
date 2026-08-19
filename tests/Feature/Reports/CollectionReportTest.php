<?php

namespace Tests\Feature\Reports;

use App\Enums\AccountCode;
use App\Enums\PaymentMethod;
use App\Models\Building;
use App\Models\Flat;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use App\Services\Expenses\RecordExpense;
use App\Services\JournalService;
use App\Services\Reporting\LedgerReports;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CollectionReportTest extends TestCase
{
    use RefreshDatabase;

    private LedgerReports $reports;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->reports = app(LedgerReports::class);

        $building = Building::factory()->flatRate('3000.00')->create();
        $this->flat = Flat::factory()->for($building)->create();

        app(GenerateMonthlyBills::class)->handle($building, Carbon::parse('2026-08-01'));
    }

    private function pay(string $amount, PaymentMethod $method, string $on): void
    {
        app(RecordPayment::class)->handle($this->flat, $amount, $method, Carbon::parse($on));
    }

    public function test_it_lists_receipts_taken_inside_the_range(): void
    {
        $this->pay('1000.00', PaymentMethod::Cash, '2026-08-05');
        $this->pay('500.00', PaymentMethod::Bkash, '2026-08-25');

        $collections = $this->reports->collections(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        $this->assertCount(2, $collections);
        $this->assertSame('1500.00', $collections->reduce(
            fn (string $carry, $payment): string => bcadd($carry, (string) $payment->amount, 2), '0.00'
        ));
    }

    public function test_the_range_boundaries_are_inclusive(): void
    {
        $this->pay('1000.00', PaymentMethod::Cash, '2026-08-01');
        $this->pay('1000.00', PaymentMethod::Cash, '2026-08-31');
        $this->pay('1000.00', PaymentMethod::Cash, '2026-09-01');

        $this->assertCount(2, $this->reports->collections(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31')));
    }

    public function test_the_receipt_total_reconciles_with_the_ledger(): void
    {
        $this->pay('1000.00', PaymentMethod::Cash, '2026-08-05');
        $this->pay('500.00', PaymentMethod::Bkash, '2026-08-25');

        $this->assertSame(
            '1500.00',
            $this->reports->ledgerCollections(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31')),
        );
    }

    public function test_the_ledger_total_ignores_money_that_did_not_come_from_owners(): void
    {
        $this->pay('1000.00', PaymentMethod::Cash, '2026-08-05');

        // A vendor payment moves cash too, but it is not a collection.
        app(RecordExpense::class)->handle(
            app(JournalService::class)->account(AccountCode::Cleaning),
            '400.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-10'),
            'Stairwell cleaning',
        );

        $this->assertSame(
            '1000.00',
            $this->reports->ledgerCollections(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31')),
        );
    }

    public function test_a_mobile_wallet_receipt_is_reported_under_its_own_method(): void
    {
        $this->pay('500.00', PaymentMethod::Nagad, '2026-08-25');

        $payment = $this->reports->collections(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'))->sole();

        $this->assertSame(PaymentMethod::Nagad, $payment->method);
    }

    public function test_a_period_with_no_receipts_reports_nothing(): void
    {
        $this->assertTrue($this->reports->collections(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'))->isEmpty());
        $this->assertSame('0.00', $this->reports->ledgerCollections(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31')));
    }
}
