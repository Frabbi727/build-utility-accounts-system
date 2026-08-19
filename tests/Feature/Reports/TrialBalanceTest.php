<?php

namespace Tests\Feature\Reports;

use App\Enums\AccountCode;
use App\Enums\PaymentMethod;
use App\Livewire\TrialBalance;
use App\Models\Building;
use App\Models\Flat;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The trial balance is the ledger's self-check: if it does not balance, something
 * has written to journal_lines outside JournalService.
 */
class TrialBalanceTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->building = Building::factory()->flatRate('3000.00')->create(['due_day_of_month' => 10]);
    }

    private function actingAsAccountant(): void
    {
        $user = User::factory()->create();
        $user->assignRole('accountant');

        $this->actingAs($user);
    }

    public function test_an_empty_ledger_produces_no_rows_and_still_balances(): void
    {
        $this->actingAsAccountant();

        Livewire::test(TrialBalance::class)
            ->assertSet('asOf', now()->endOfMonth()->toDateString())
            ->assertViewHas('rows', fn ($rows): bool => $rows->isEmpty())
            ->assertViewHas('isBalanced', true);
    }

    public function test_debits_equal_credits_after_billing_and_collection(): void
    {
        $this->actingAsAccountant();

        Flat::factory()->count(3)->for($this->building)->create();

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));
        app(RecordPayment::class)->handle(
            Flat::first(),
            '3000.00',
            PaymentMethod::Bkash,
            Carbon::parse('2026-08-12'),
        );

        Livewire::test(TrialBalance::class)
            ->set('asOf', '2026-08-31')
            ->assertViewHas('isBalanced', true)
            ->assertViewHas('totalDebit', '9000.00')
            ->assertViewHas('totalCredit', '9000.00');
    }

    public function test_each_account_is_reported_on_its_natural_side(): void
    {
        $this->actingAsAccountant();

        Flat::factory()->for($this->building)->create();

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        $rows = Livewire::test(TrialBalance::class)->set('asOf', '2026-08-31')->viewData('rows');

        $receivable = $rows->firstWhere(fn (array $r): bool => $r['account']->code === AccountCode::ServiceChargeReceivable->value);
        $income = $rows->firstWhere(fn (array $r): bool => $r['account']->code === AccountCode::ServiceChargeIncome->value);

        // A receivable is debit-normal, income is credit-normal.
        $this->assertSame('3000.00', $receivable['debit']);
        $this->assertSame('0.00', $receivable['credit']);
        $this->assertSame('0.00', $income['debit']);
        $this->assertSame('3000.00', $income['credit']);
    }

    public function test_the_as_of_date_excludes_later_entries(): void
    {
        $this->actingAsAccountant();

        Flat::factory()->for($this->building)->create();

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        // The accrual is dated the 10th, so a cut-off before that sees nothing.
        Livewire::test(TrialBalance::class)
            ->set('asOf', '2026-08-01')
            ->assertViewHas('totalDebit', '0.00')
            ->assertViewHas('isBalanced', true);
    }
}
