<?php

namespace Tests\Feature\Billing;

use App\Enums\AccountCode;
use App\Models\AdHocCharge;
use App\Models\Building;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A one-off charge rides along on the next monthly bill, is accrued like any other
 * income, and is stamped so it can never be billed twice.
 */
class AdHocChargeTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journal;

    private Building $building;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->journal = app(JournalService::class);
        $this->building = Building::factory()->flatRate('3000.00')->create(['due_day_of_month' => 10]);
        $this->flat = Flat::factory()->for($this->building)->create();
    }

    private function charge(string $amount, string $month, string $description = 'Balcony repair'): AdHocCharge
    {
        return AdHocCharge::factory()->create([
            'flat_id' => $this->flat->id,
            'account_id' => $this->journal->account(AccountCode::OtherIncome)->id,
            'amount' => $amount,
            'effective_month' => Carbon::parse($month),
            'description' => $description,
        ]);
    }

    private function generate(string $month): void
    {
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse($month));
    }

    public function test_it_is_added_to_the_next_bill_as_its_own_line(): void
    {
        $charge = $this->charge('750.00', '2026-06-01');

        $this->generate('2026-06-01');

        $bill = ServiceChargeBill::sole();

        $this->assertSame('3750.00', (string) $bill->total_amount);
        $this->assertSame(2, $bill->items()->count());
        $this->assertSame('750.00', (string) $bill->items()->where('description', 'Balcony repair')->sole()->amount);
        $this->assertSame($bill->id, $charge->fresh()->applied_to_bill_id);
    }

    public function test_it_credits_its_own_income_account(): void
    {
        $this->charge('750.00', '2026-06-01');

        $this->generate('2026-06-01');

        $this->assertSame('750.00', $this->journal->balanceFor($this->journal->account(AccountCode::OtherIncome)));
        $this->assertSame('3000.00', $this->journal->balanceFor($this->journal->account(AccountCode::ServiceChargeIncome)));

        // The receivable carries the whole bill, under the flat dimension.
        $this->assertSame('3750.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::ServiceChargeReceivable),
            $this->flat->id,
        ));
    }

    public function test_it_is_never_billed_twice(): void
    {
        $this->charge('750.00', '2026-06-01');

        $this->generate('2026-06-01');
        $this->generate('2026-07-01');

        $july = ServiceChargeBill::orderByDesc('billing_month')->first();

        $this->assertSame('3000.00', (string) $july->total_amount);
        $this->assertSame(1, $july->items()->count());
        $this->assertSame('750.00', $this->journal->balanceFor($this->journal->account(AccountCode::OtherIncome)));
    }

    public function test_a_charge_for_a_later_month_is_left_alone(): void
    {
        $charge = $this->charge('750.00', '2026-08-01');

        $this->generate('2026-06-01');

        $this->assertSame('3000.00', (string) ServiceChargeBill::sole()->total_amount);
        $this->assertNull($charge->fresh()->applied_to_bill_id);
    }

    public function test_a_charge_added_after_its_month_was_billed_lands_on_the_following_bill(): void
    {
        $this->generate('2026-06-01');

        // Recorded late, dated for a month that has already been billed.
        $charge = $this->charge('750.00', '2026-06-01');

        $this->generate('2026-07-01');

        $july = ServiceChargeBill::orderByDesc('billing_month')->first();

        $this->assertSame('3750.00', (string) $july->total_amount);
        $this->assertSame($july->id, $charge->fresh()->applied_to_bill_id);
    }

    public function test_a_flat_with_only_an_ad_hoc_charge_is_still_billed(): void
    {
        $bare = Building::factory()->create(['due_day_of_month' => 10]);
        $flat = Flat::factory()->for($bare)->create();

        AdHocCharge::factory()->create([
            'flat_id' => $flat->id,
            'account_id' => $this->journal->account(AccountCode::OtherIncome)->id,
            'amount' => '400.00',
            'effective_month' => Carbon::parse('2026-06-01'),
        ]);

        app(GenerateMonthlyBills::class)->handle($bare, Carbon::parse('2026-06-01'));

        $bill = ServiceChargeBill::where('flat_id', $flat->id)->sole();

        $this->assertSame('400.00', (string) $bill->total_amount);
    }

    public function test_the_entry_stays_balanced(): void
    {
        $this->charge('750.00', '2026-06-01');

        $this->generate('2026-06-01');

        $entry = ServiceChargeBill::sole()->journalEntries()->with('lines')->first();

        $debit = $entry->lines->reduce(fn (string $c, $l): string => bcadd($c, (string) $l->debit, 2), '0.00');
        $credit = $entry->lines->reduce(fn (string $c, $l): string => bcadd($c, (string) $l->credit, 2), '0.00');

        $this->assertSame($debit, $credit);
        $this->assertSame('3750.00', $debit);
    }
}
