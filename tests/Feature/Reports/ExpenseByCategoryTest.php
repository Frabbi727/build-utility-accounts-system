<?php

namespace Tests\Feature\Reports;

use App\Enums\AccountCode;
use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Vendor;
use App\Services\Expenses\RecordExpense;
use App\Services\Expenses\RecordVendorBill;
use App\Services\JournalService;
use App\Services\Reporting\LedgerReports;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ExpenseByCategoryTest extends TestCase
{
    use RefreshDatabase;

    private LedgerReports $reports;

    private JournalService $journal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->reports = app(LedgerReports::class);
        $this->journal = app(JournalService::class);
    }

    /**
     * @param  Collection<int, array{account: Account, amount: string}>  $rows
     */
    private function amountFor(Collection $rows, AccountCode $code): ?string
    {
        return $rows->first(fn (array $row): bool => $row['account']->code === $code->value)['amount'] ?? null;
    }

    public function test_a_direct_expense_lands_in_its_category(): void
    {
        app(RecordExpense::class)->handle(
            $this->journal->account(AccountCode::GuardSalary),
            '12000.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-05'),
            'August guard salary',
        );

        $rows = $this->reports->balancesByType(AccountType::Expense, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        $this->assertSame('12000.00', $this->amountFor($rows, AccountCode::GuardSalary));
        $this->assertSame('12000.00', $this->reports->total($rows));
    }

    public function test_a_vendor_bill_is_counted_in_the_month_it_was_incurred(): void
    {
        app(RecordVendorBill::class)->handle(
            Vendor::factory()->create(),
            Carbon::parse('2026-08-12'),
            Carbon::parse('2026-09-12'),
            'Lift servicing',
            [['account_id' => $this->journal->account(AccountCode::LiftMaintenance)->id, 'description' => 'Quarterly service', 'amount' => '8000.00']],
        );

        $rows = $this->reports->balancesByType(AccountType::Expense, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        // Accrual: the expense belongs to August even though it is paid later.
        $this->assertSame('8000.00', $this->amountFor($rows, AccountCode::LiftMaintenance));
    }

    public function test_categories_outside_the_range_are_excluded(): void
    {
        app(RecordExpense::class)->handle(
            $this->journal->account(AccountCode::Cleaning),
            '1000.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-07-31'),
            'July cleaning',
        );

        $rows = $this->reports->balancesByType(AccountType::Expense, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        $this->assertSame('0.00', $this->reports->total($rows));
        $this->assertTrue($rows->isEmpty());
    }

    public function test_income_accounts_never_appear_among_the_categories(): void
    {
        app(RecordExpense::class)->handle(
            $this->journal->account(AccountCode::Admin),
            '500.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-05'),
            'Stationery',
        );

        $rows = $this->reports->balancesByType(AccountType::Expense, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        $this->assertTrue($rows->every(fn (array $row): bool => $row['account']->type === AccountType::Expense));
        $this->assertNull($this->amountFor($rows, AccountCode::ServiceChargeIncome));
    }

    public function test_several_expenses_on_one_category_are_summed(): void
    {
        foreach (['200.00', '300.50'] as $amount) {
            app(RecordExpense::class)->handle(
                $this->journal->account(AccountCode::GeneratorFuel),
                $amount,
                PaymentMethod::Cash,
                Carbon::parse('2026-08-05'),
                'Diesel',
            );
        }

        $rows = $this->reports->balancesByType(AccountType::Expense, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        $this->assertSame('500.50', $this->amountFor($rows, AccountCode::GeneratorFuel));
    }
}
