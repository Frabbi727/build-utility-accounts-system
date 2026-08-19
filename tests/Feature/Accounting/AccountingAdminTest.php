<?php

namespace Tests\Feature\Accounting;

use App\Enums\AccountCode;
use App\Enums\AccountType;
use App\Enums\PeriodStatus;
use App\Enums\Role;
use App\Exceptions\InvalidJournalEntryException;
use App\Exceptions\PeriodLockedException;
use App\Livewire\Accounting\OpeningBalances;
use App\Livewire\Accounting\PeriodList;
use App\Livewire\AccountList;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Building;
use App\Models\Flat;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\PostOpeningBalances;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use App\Support\JournalLineData;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingAdminTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->journal = app(JournalService::class);
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role->value]);

        return $user;
    }

    // --- Chart of accounts -------------------------------------------------

    public function test_an_admin_can_add_an_account(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(AccountList::class)
            ->call('create')
            ->set('code', '5090')
            ->set('name', 'Security Equipment')
            ->set('type', AccountType::Expense->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(AccountType::Expense, Account::firstWhere('code', '5090')->type);
    }

    public function test_account_codes_must_be_unique(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(AccountList::class)
            ->call('create')
            ->set('code', AccountCode::Bank->value)
            ->set('name', 'Second Bank')
            ->call('save')
            ->assertHasErrors('code');
    }

    public function test_an_accountant_cannot_edit_the_chart_of_accounts(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(AccountList::class)
            ->assertOk()
            ->call('create')
            ->assertForbidden();
    }

    public function test_a_reserved_accounts_code_and_type_survive_an_edit(): void
    {
        $bank = $this->journal->account(AccountCode::Bank);

        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(AccountList::class)
            ->call('edit', $bank->id)
            ->assertSet('editingReserved', true)
            ->set('code', '9999')
            ->set('type', AccountType::Expense->value)
            ->set('name', 'Renamed Bank')
            ->call('save')
            ->assertHasNoErrors();

        $bank->refresh();
        $this->assertSame(AccountCode::Bank->value, $bank->code);
        $this->assertSame(AccountType::Asset, $bank->type);
        $this->assertSame('Renamed Bank', $bank->name);
    }

    public function test_a_reserved_account_cannot_be_deleted(): void
    {
        $cash = $this->journal->account(AccountCode::CashInHand);

        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(AccountList::class)
            ->call('delete', $cash->id)
            ->assertForbidden();

        $this->assertNotNull($cash->fresh());
    }

    public function test_an_account_carrying_journal_lines_cannot_be_deleted(): void
    {
        $account = Account::factory()->create(['code' => '5091', 'type' => AccountType::Expense]);
        $this->journal->post(
            Carbon::parse('2026-08-01'),
            'Test',
            [
                JournalLineData::debit($account, '100.00'),
                JournalLineData::credit($this->journal->account(AccountCode::CashInHand), '100.00'),
            ],
        );

        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(AccountList::class)
            ->call('delete', $account->id)
            ->assertForbidden();

        $this->assertNotNull($account->fresh());
    }

    public function test_postings_cannot_be_turned_off_on_an_account_that_has_them(): void
    {
        $account = Account::factory()->create(['code' => '5092', 'type' => AccountType::Expense]);
        $this->journal->post(
            Carbon::parse('2026-08-01'),
            'Test',
            [
                JournalLineData::debit($account, '50.00'),
                JournalLineData::credit($this->journal->account(AccountCode::CashInHand), '50.00'),
            ],
        );

        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(AccountList::class)
            ->call('edit', $account->id)
            ->set('isPostable', false)
            ->call('save')
            ->assertHasErrors('isPostable');

        $this->assertTrue($account->fresh()->is_postable);
    }

    public function test_an_unused_account_can_be_deleted(): void
    {
        $account = Account::factory()->create(['code' => '5093', 'type' => AccountType::Expense]);

        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(AccountList::class)
            ->call('delete', $account->id);

        $this->assertNull($account->fresh());
    }

    // --- Opening balances --------------------------------------------------

    public function test_opening_balances_post_one_balanced_entry_with_the_fund_balancing(): void
    {
        $building = Building::factory()->create();
        app(CurrentBuilding::class)->set($building->id);
        $flat = Flat::factory()->for($building)->create(['number' => '1A']);

        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(OpeningBalances::class)
            ->set('asOf', '2026-01-01')
            ->set('cash', '5000')
            ->set('bank', '120000')
            ->set('payables', '15000')
            ->set('deposits', '20000')
            ->set("flatDues.{$flat->id}", '3000')
            ->call('post')
            ->assertHasNoErrors();

        $entry = JournalEntry::with('lines')->firstOrFail();

        $this->assertSame(
            bcadd((string) $entry->lines->sum('debit'), '0', 2),
            bcadd((string) $entry->lines->sum('credit'), '0', 2),
        );

        $this->assertSame('5000.00', $this->journal->balanceFor($this->journal->account(AccountCode::CashInHand)));
        $this->assertSame('120000.00', $this->journal->balanceFor($this->journal->account(AccountCode::Bank)));
        $this->assertSame('3000.00', $this->journal->balanceFor($this->journal->account(AccountCode::ServiceChargeReceivable), $flat->id));
        // 128000 debits - 35000 credits
        $this->assertSame('93000.00', $this->journal->balanceFor($this->journal->account(AccountCode::GeneralFund)));
    }

    public function test_opening_balances_can_only_be_posted_once(): void
    {
        $poster = app(PostOpeningBalances::class);
        $poster->handle(Carbon::parse('2026-01-01'), '1000');

        $this->assertTrue($poster->alreadyPosted());

        $this->expectException(InvalidJournalEntryException::class);
        $poster->handle(Carbon::parse('2026-01-01'), '2000');
    }

    public function test_posting_opening_balances_with_no_amounts_is_refused(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(OpeningBalances::class)
            ->set('cash', '0')
            ->set('bank', '0')
            ->call('post')
            ->assertHasErrors('cash');

        $this->assertSame(0, JournalEntry::count());
    }

    public function test_a_building_with_more_liabilities_than_assets_debits_the_fund(): void
    {
        app(PostOpeningBalances::class)->handle(Carbon::parse('2026-01-01'), '1000', '0', [], '5000');

        $this->assertSame('-4000.00', $this->journal->balanceFor($this->journal->account(AccountCode::GeneralFund)));
    }

    public function test_an_accountant_cannot_post_opening_balances(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(OpeningBalances::class)
            ->assertForbidden();
    }

    // --- Periods -----------------------------------------------------------

    public function test_an_admin_locks_a_period_and_posting_into_it_then_fails(): void
    {
        $period = $this->journal->periodFor(Carbon::parse('2026-08-01'));

        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(PeriodList::class)
            ->call('lock', $period->id);

        $this->assertSame(PeriodStatus::Locked, $period->fresh()->status);

        $this->expectException(PeriodLockedException::class);
        $this->journal->post(
            Carbon::parse('2026-08-15'),
            'Should not post',
            [
                JournalLineData::debit($this->journal->account(AccountCode::CashInHand), '10.00'),
                JournalLineData::credit($this->journal->account(AccountCode::OtherIncome), '10.00'),
            ],
        );
    }

    public function test_unlocking_a_period_lets_posting_resume(): void
    {
        $period = AccountingPeriod::factory()->create(['year' => 2026, 'month' => 8, 'status' => PeriodStatus::Locked]);

        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(PeriodList::class)
            ->call('unlock', $period->id);

        $this->assertSame(PeriodStatus::Open, $period->fresh()->status);

        $entry = $this->journal->post(
            Carbon::parse('2026-08-15'),
            'Now allowed',
            [
                JournalLineData::debit($this->journal->account(AccountCode::CashInHand), '10.00'),
                JournalLineData::credit($this->journal->account(AccountCode::OtherIncome), '10.00'),
            ],
        );

        $this->assertNotNull($entry->id);
    }

    public function test_an_accountant_cannot_lock_a_period(): void
    {
        $period = AccountingPeriod::factory()->create(['year' => 2026, 'month' => 8]);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(PeriodList::class)
            ->assertOk()
            ->call('lock', $period->id)
            ->assertForbidden();

        $this->assertSame(PeriodStatus::Open, $period->fresh()->status);
    }
}
