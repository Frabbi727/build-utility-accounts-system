<?php

namespace Tests\Feature\Reports;

use App\Enums\AccountCode;
use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Vendor;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use App\Services\Expenses\RecordExpense;
use App\Services\Expenses\RecordVendorBill;
use App\Services\JournalService;
use App\Services\Reporting\LedgerReports;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BalanceSheetTest extends TestCase
{
    use RefreshDatabase;

    private LedgerReports $reports;

    private JournalService $journal;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->reports = app(LedgerReports::class);
        $this->journal = app(JournalService::class);
        $this->building = Building::factory()->flatRate('3000.00')->create();
    }

    /**
     * Assets must equal liabilities + funds + the surplus carried to date.
     *
     * @return array{0: string, 1: string}
     */
    private function bothSides(string $asOf): array
    {
        $date = Carbon::parse($asOf);

        $assets = $this->reports->total($this->reports->balancesByType(AccountType::Asset, null, $date));

        $funded = bcadd(
            bcadd(
                $this->reports->total($this->reports->balancesByType(AccountType::Liability, null, $date)),
                $this->reports->total($this->reports->balancesByType(AccountType::Equity, null, $date)),
                2,
            ),
            $this->reports->accumulatedSurplus($date),
            2,
        );

        return [$assets, $funded];
    }

    public function test_it_balances_across_a_full_bill_payment_and_expense_cycle(): void
    {
        $flat = Flat::factory()->for($this->building)->create();

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));
        app(RecordPayment::class)->handle($flat, '2000.00', PaymentMethod::Cash, Carbon::parse('2026-08-15'));
        app(RecordExpense::class)->handle(
            $this->journal->account(AccountCode::Cleaning),
            '500.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-20'),
            'Cleaning',
        );

        [$assets, $funded] = $this->bothSides('2026-08-31');

        // Cash 1500 + receivable 1000 = 2500; surplus 3000 income − 500 expense = 2500.
        $this->assertSame('2500.00', $assets);
        $this->assertSame($assets, $funded);
    }

    public function test_an_unpaid_vendor_bill_shows_as_a_liability(): void
    {
        app(RecordVendorBill::class)->handle(
            Vendor::factory()->create(),
            Carbon::parse('2026-08-12'),
            Carbon::parse('2026-09-12'),
            'Lift servicing',
            [['account_id' => $this->journal->account(AccountCode::LiftMaintenance)->id, 'description' => 'Service', 'amount' => '8000.00']],
        );

        $payable = $this->reports
            ->balancesByType(AccountType::Liability, null, Carbon::parse('2026-08-31'))
            ->sole();

        $this->assertSame(AccountCode::AccountsPayable->value, $payable['account']->code);
        $this->assertSame('8000.00', $payable['amount']);

        [$assets, $funded] = $this->bothSides('2026-08-31');
        $this->assertSame($assets, $funded);
    }

    public function test_an_overpayment_shows_as_an_advance_liability(): void
    {
        $flat = Flat::factory()->for($this->building)->create();

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));
        app(RecordPayment::class)->handle($flat, '5000.00', PaymentMethod::Cash, Carbon::parse('2026-08-15'));

        $advance = $this->reports
            ->balancesByType(AccountType::Liability, null, Carbon::parse('2026-08-31'))
            ->sole();

        $this->assertSame(AccountCode::AdvanceFromOwners->value, $advance['account']->code);
        $this->assertSame('2000.00', $advance['amount']);

        [$assets, $funded] = $this->bothSides('2026-08-31');
        $this->assertSame($assets, $funded);
    }

    public function test_the_sheet_reflects_only_what_had_been_posted_by_the_as_of_date(): void
    {
        Flat::factory()->for($this->building)->create();

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        [$assets, $funded] = $this->bothSides('2026-07-31');

        $this->assertSame('0.00', $assets);
        $this->assertSame('0.00', $funded);
    }

    public function test_an_empty_ledger_balances_at_zero(): void
    {
        [$assets, $funded] = $this->bothSides('2026-08-31');

        $this->assertSame('0.00', $assets);
        $this->assertSame('0.00', $funded);
    }
}
