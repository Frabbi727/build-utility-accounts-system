<?php

namespace Tests\Feature\Reports;

use App\Enums\AccountCode;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Livewire\Reports\BalanceSheet;
use App\Livewire\Reports\CashBook;
use App\Livewire\Reports\CollectionReport;
use App\Livewire\Reports\ExpenseByCategory;
use App\Livewire\Reports\IncomeExpenditure;
use App\Livewire\Reports\OwnerDues;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use App\Services\Expenses\RecordExpense;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The report screens themselves: that they render, and that only staff reach them.
 */
class ReportScreensTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const ROUTES = [
        'reports.index',
        'reports.owner-dues',
        'reports.collections',
        'reports.expenses',
        'reports.cash-book',
        'reports.income-expenditure',
        'reports.balance-sheet',
        'reports.trial-balance',
    ];

    private Building $building;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->flatRate('3000.00')->create();
        $this->flat = Flat::factory()->for($this->building)->create(['number' => 'A-101']);
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role->value]);

        return $user;
    }

    private function seedActivity(): void
    {
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));
        app(RecordPayment::class)->handle($this->flat, '1000.00', PaymentMethod::Cash, Carbon::parse('2026-08-15'));
        app(RecordExpense::class)->handle(
            app(JournalService::class)->account(AccountCode::Cleaning),
            '400.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-20'),
            'Stairwell cleaning',
        );
    }

    public function test_an_owner_cannot_reach_any_report(): void
    {
        $user = $this->userWithRole(Role::Owner);
        Owner::factory()->create(['user_id' => $user->id]);

        foreach (self::ROUTES as $route) {
            $this->actingAs($user)->get(route($route))->assertForbidden();
        }
    }

    public function test_a_committee_member_can_read_every_report(): void
    {
        $user = $this->userWithRole(Role::Committee);

        foreach (self::ROUTES as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }
    }

    public function test_the_owner_dues_screen_lists_a_flat_in_arrears(): void
    {
        $this->seedActivity();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(OwnerDues::class)
            ->set('asOf', '2026-08-31')
            ->assertOk()
            ->assertSee('A-101')
            ->assertSee('2,000.00');
    }

    public function test_the_collection_screen_reports_the_receipts_it_reconciles(): void
    {
        $this->seedActivity();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(CollectionReport::class)
            ->set('from', '2026-08-01')
            ->set('to', '2026-08-31')
            ->assertOk()
            ->assertSee('1,000.00')
            ->assertSee(__('reports.collections_reconcile'));
    }

    public function test_the_expense_screen_reports_the_period_spend(): void
    {
        $this->seedActivity();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(ExpenseByCategory::class)
            ->set('from', '2026-08-01')
            ->set('to', '2026-08-31')
            ->assertOk()
            ->assertSee('400.00');
    }

    public function test_the_cash_book_screen_can_switch_between_cash_and_bank(): void
    {
        $this->seedActivity();
        app(RecordPayment::class)->handle($this->flat, '750.00', PaymentMethod::Bkash, Carbon::parse('2026-08-25'));

        $component = Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(CashBook::class)
            ->set('from', '2026-08-01')
            ->set('to', '2026-08-31');

        $component->assertOk()->assertSee('600.00'); // 1000 collected − 400 spent

        $component->set('account', AccountCode::Bank->value)
            ->assertOk()
            ->assertSee('750.00');
    }

    public function test_the_income_and_expenditure_screen_reports_a_surplus(): void
    {
        $this->seedActivity();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(IncomeExpenditure::class)
            ->set('from', '2026-08-01')
            ->set('to', '2026-08-31')
            ->assertOk()
            ->assertSee(__('reports.surplus'))
            ->assertSee('2,600.00'); // 3000 income − 400 expense
    }

    public function test_the_balance_sheet_screen_ties_to_the_ledger(): void
    {
        $this->seedActivity();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(BalanceSheet::class)
            ->set('asOf', '2026-08-31')
            ->assertOk()
            ->assertSee(__('reports.sheet_balanced'))
            ->assertDontSee(__('reports.sheet_out_of_balance'));
    }
}
