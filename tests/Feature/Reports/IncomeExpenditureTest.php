<?php

namespace Tests\Feature\Reports;

use App\Enums\AccountCode;
use App\Enums\AccountType;
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

class IncomeExpenditureTest extends TestCase
{
    use RefreshDatabase;

    private LedgerReports $reports;

    private JournalService $journal;

    private Building $building;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->reports = app(LedgerReports::class);
        $this->journal = app(JournalService::class);

        $this->building = Building::factory()->flatRate('3000.00')->create();
        $this->flat = Flat::factory()->for($this->building)->create();
    }

    private function surplusFor(string $from, string $to): string
    {
        return bcsub(
            $this->reports->total($this->reports->balancesByType(AccountType::Income, Carbon::parse($from), Carbon::parse($to))),
            $this->reports->total($this->reports->balancesByType(AccountType::Expense, Carbon::parse($from), Carbon::parse($to))),
            2,
        );
    }

    public function test_the_surplus_is_income_less_expenditure(): void
    {
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        app(RecordExpense::class)->handle(
            $this->journal->account(AccountCode::GuardSalary),
            '1200.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-10'),
            'Guard salary',
        );

        $this->assertSame('1800.00', $this->surplusFor('2026-08-01', '2026-08-31'));
    }

    public function test_spending_more_than_was_earned_shows_a_deficit(): void
    {
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        app(RecordExpense::class)->handle(
            $this->journal->account(AccountCode::RepairsMaintenance),
            '5000.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-10'),
            'Roof repair',
        );

        $this->assertSame('-2000.00', $this->surplusFor('2026-08-01', '2026-08-31'));
    }

    public function test_a_service_charge_counts_as_income_when_it_falls_due_not_when_it_is_paid(): void
    {
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        // Collected two months later; the income still belongs to August.
        app(RecordPayment::class)->handle($this->flat, '3000.00', PaymentMethod::Cash, Carbon::parse('2026-10-05'));

        $august = $this->reports->balancesByType(AccountType::Income, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));
        $october = $this->reports->balancesByType(AccountType::Income, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $this->assertSame('3000.00', $this->reports->total($august));
        $this->assertSame('0.00', $this->reports->total($october));
    }

    public function test_a_period_with_no_activity_reports_a_zero_surplus(): void
    {
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        $this->assertSame('0.00', $this->surplusFor('2026-09-01', '2026-09-30'));
    }

    public function test_income_accounts_are_reported_on_their_credit_side(): void
    {
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        $row = $this->reports
            ->balancesByType(AccountType::Income, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'))
            ->sole();

        $this->assertSame(AccountCode::ServiceChargeIncome->value, $row['account']->code);
        $this->assertSame('3000.00', $row['amount']);
    }
}
