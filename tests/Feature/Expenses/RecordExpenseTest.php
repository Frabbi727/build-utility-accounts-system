<?php

namespace Tests\Feature\Expenses;

use App\Enums\AccountCode;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidJournalEntryException;
use App\Models\Expense;
use App\Models\JournalLine;
use App\Models\Staff;
use App\Services\Expenses\RecordExpense;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\AssertsLedger;
use Tests\TestCase;

class RecordExpenseTest extends TestCase
{
    use AssertsLedger, RefreshDatabase;

    private JournalService $journal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->journal = app(JournalService::class);
    }

    public function test_a_cash_expense_debits_the_category_and_credits_cash(): void
    {
        app(RecordExpense::class)->handle(
            $this->journal->account(AccountCode::GeneratorFuel),
            '2500.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-12'),
            'Diesel for generator',
        );

        $this->assertSame('2500.00', $this->journal->balanceFor($this->journal->account(AccountCode::GeneratorFuel)));
        $this->assertSame('-2500.00', $this->journal->balanceFor($this->journal->account(AccountCode::CashInHand)));
        $this->assertLedgerIsBalanced();
    }

    public function test_a_salary_payment_is_charged_to_the_staff_expense_account(): void
    {
        $staff = Staff::factory()->create([
            'expense_account_id' => $this->journal->account(AccountCode::GuardSalary)->id,
        ]);

        $expense = app(RecordExpense::class)->handle(
            $this->journal->account(AccountCode::GuardSalary),
            '12000.00',
            PaymentMethod::Bank,
            Carbon::parse('2026-08-31'),
            'August salary',
            staff: $staff,
        );

        $this->assertSame($staff->id, $expense->staff_id);
        $this->assertSame('12000.00', $this->journal->balanceFor($this->journal->account(AccountCode::GuardSalary)));
        $this->assertSame('-12000.00', $this->journal->balanceFor($this->journal->account(AccountCode::Bank)));
        $this->assertLedgerIsBalanced();
    }

    public function test_mobile_wallet_expenses_are_paid_from_the_bank_account(): void
    {
        app(RecordExpense::class)->handle(
            $this->journal->account(AccountCode::Cleaning),
            '800.00',
            PaymentMethod::Bkash,
            Carbon::parse('2026-08-12'),
            'Cleaning supplies',
        );

        $this->assertSame('-800.00', $this->journal->balanceFor($this->journal->account(AccountCode::Bank)));
        $this->assertSame('0.00', $this->journal->balanceFor($this->journal->account(AccountCode::CashInHand)));
    }

    public function test_an_expense_cannot_be_charged_to_an_income_account(): void
    {
        $this->expectException(InvalidJournalEntryException::class);

        app(RecordExpense::class)->handle(
            $this->journal->account(AccountCode::ServiceChargeIncome),
            '500.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-12'),
            'Should not post',
        );
    }

    public function test_a_non_positive_expense_is_refused(): void
    {
        $this->expectException(InvalidJournalEntryException::class);

        app(RecordExpense::class)->handle(
            $this->journal->account(AccountCode::Admin),
            '0',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-12'),
            'Should not post',
        );
    }

    public function test_a_rejected_expense_leaves_no_rows_behind(): void
    {
        try {
            app(RecordExpense::class)->handle(
                $this->journal->account(AccountCode::ServiceChargeIncome),
                '500.00',
                PaymentMethod::Cash,
                Carbon::parse('2026-08-12'),
                'Should not post',
            );
        } catch (InvalidJournalEntryException) {
            // expected
        }

        $this->assertSame(0, Expense::count());
        $this->assertSame(0, JournalLine::count());
    }

    public function test_the_expense_is_linked_to_its_journal_entry(): void
    {
        $expense = app(RecordExpense::class)->handle(
            $this->journal->account(AccountCode::WaterWasa),
            '4500.00',
            PaymentMethod::Bank,
            Carbon::parse('2026-08-12'),
            'WASA bill',
        );

        $this->assertSame($expense->id, $expense->journalEntries()->firstOrFail()->source_id);
    }
}
