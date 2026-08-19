<?php

namespace Tests\Feature\Reports;

use App\Enums\AccountCode;
use App\Enums\PaymentMethod;
use App\Models\Building;
use App\Models\Flat;
use App\Models\JournalLine;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use App\Services\Expenses\RecordExpense;
use App\Services\JournalService;
use App\Services\Reporting\LedgerReports;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CashBookTest extends TestCase
{
    use RefreshDatabase;

    private LedgerReports $reports;

    private JournalService $journal;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->reports = app(LedgerReports::class);
        $this->journal = app(JournalService::class);

        $building = Building::factory()->flatRate('3000.00')->create();
        $this->flat = Flat::factory()->for($building)->create();

        foreach (['2026-07-01', '2026-08-01'] as $month) {
            app(GenerateMonthlyBills::class)->handle($building, Carbon::parse($month));
        }
    }

    public function test_the_opening_balance_is_everything_posted_before_the_range(): void
    {
        app(RecordPayment::class)->handle($this->flat, '3000.00', PaymentMethod::Cash, Carbon::parse('2026-07-15'));

        $cash = $this->journal->account(AccountCode::CashInHand);

        $this->assertSame('3000.00', $this->reports->openingBalance($cash, Carbon::parse('2026-08-01')));
        $this->assertTrue($this->reports->movements($cash, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'))->isEmpty());
    }

    public function test_opening_plus_movements_equals_the_closing_balance(): void
    {
        app(RecordPayment::class)->handle($this->flat, '3000.00', PaymentMethod::Cash, Carbon::parse('2026-07-15'));
        app(RecordPayment::class)->handle($this->flat, '3000.00', PaymentMethod::Cash, Carbon::parse('2026-08-15'));
        app(RecordExpense::class)->handle(
            $this->journal->account(AccountCode::Cleaning),
            '1200.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-20'),
            'Cleaning',
        );

        $cash = $this->journal->account(AccountCode::CashInHand);

        $opening = $this->reports->openingBalance($cash, Carbon::parse('2026-08-01'));
        $movements = $this->reports->movements($cash, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        $net = $movements->reduce(
            fn (string $carry, JournalLine $line): string => bcadd($carry, bcsub((string) $line->debit, (string) $line->credit, 2), 2),
            '0.00',
        );

        $this->assertSame('3000.00', $opening);
        $this->assertSame('1800.00', $net);
        $this->assertSame(
            $this->journal->balanceFor($cash, null, Carbon::parse('2026-08-31')),
            bcadd($opening, $net, 2),
        );
    }

    public function test_movements_come_back_in_date_order(): void
    {
        app(RecordPayment::class)->handle($this->flat, '1000.00', PaymentMethod::Cash, Carbon::parse('2026-08-20'));
        app(RecordPayment::class)->handle($this->flat, '1000.00', PaymentMethod::Cash, Carbon::parse('2026-08-05'));

        $dates = $this->reports
            ->movements($this->journal->account(AccountCode::CashInHand), Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'))
            ->map(fn (JournalLine $line): string => $line->journalEntry->entry_date->toDateString())
            ->all();

        $this->assertSame(['2026-08-05', '2026-08-20'], $dates);
    }

    public function test_the_bank_book_and_the_cash_book_do_not_mix(): void
    {
        app(RecordPayment::class)->handle($this->flat, '3000.00', PaymentMethod::Cash, Carbon::parse('2026-08-05'));
        app(RecordPayment::class)->handle($this->flat, '2000.00', PaymentMethod::Bkash, Carbon::parse('2026-08-06'));

        $from = Carbon::parse('2026-08-01');
        $to = Carbon::parse('2026-08-31');

        $cash = $this->reports->movements($this->journal->account(AccountCode::CashInHand), $from, $to);
        $bank = $this->reports->movements($this->journal->account(AccountCode::Bank), $from, $to);

        $this->assertCount(1, $cash);
        $this->assertCount(1, $bank);
        $this->assertSame('3000.00', (string) $cash->sole()->debit);
        $this->assertSame('2000.00', (string) $bank->sole()->debit);
    }
}
