<?php

namespace Tests\Feature\Livewire;

use App\Enums\AccountCode;
use App\Enums\Role;
use App\Enums\VendorBillStatus;
use App\Livewire\ExpenseList;
use App\Livewire\VendorBillList;
use App\Models\Expense;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseScreensTest extends TestCase
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

    public function test_the_expense_screen_records_and_posts(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(ExpenseList::class)
            ->set('accountId', $this->journal->account(AccountCode::CommonElectricity)->id)
            ->set('amount', '7500.00')
            ->set('method', 'bank')
            ->set('spentOn', '2026-08-14')
            ->set('description', 'August electricity')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Expense::count());
        $this->assertSame('7500.00', $this->journal->balanceFor($this->journal->account(AccountCode::CommonElectricity)));
        $this->assertSame('-7500.00', $this->journal->balanceFor($this->journal->account(AccountCode::Bank)));
    }

    public function test_the_expense_screen_validates_its_input(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(ExpenseList::class)
            ->set('accountId', null)
            ->set('amount', '-5')
            ->set('description', '')
            ->call('save')
            ->assertHasErrors(['accountId', 'amount', 'description']);

        $this->assertSame(0, Expense::count());
    }

    public function test_a_committee_member_cannot_record_an_expense(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Committee))
            ->test(ExpenseList::class)
            ->set('accountId', $this->journal->account(AccountCode::Admin)->id)
            ->set('amount', '100.00')
            ->set('spentOn', '2026-08-14')
            ->set('description', 'Nope')
            ->call('save')
            ->assertForbidden();

        $this->assertSame(0, Expense::count());
    }

    public function test_a_committee_member_can_still_read_the_expense_list(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Committee))
            ->test(ExpenseList::class)
            ->assertOk();
    }

    public function test_the_vendor_bill_screen_records_a_payable(): void
    {
        $vendor = Vendor::factory()->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(VendorBillList::class)
            ->set('vendorId', $vendor->id)
            ->set('accountId', $this->journal->account(AccountCode::LiftMaintenance)->id)
            ->set('amount', '9000.00')
            ->set('billDate', '2026-08-05')
            ->set('dueDate', '2026-09-05')
            ->set('description', 'Lift servicing')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, VendorBill::count());
        $this->assertSame('9000.00', $this->journal->balanceFor($this->journal->account(AccountCode::AccountsPayable)));
    }

    public function test_a_due_date_before_the_bill_date_is_rejected(): void
    {
        $vendor = Vendor::factory()->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(VendorBillList::class)
            ->set('vendorId', $vendor->id)
            ->set('accountId', $this->journal->account(AccountCode::Cleaning)->id)
            ->set('amount', '1000.00')
            ->set('billDate', '2026-08-20')
            ->set('dueDate', '2026-08-01')
            ->set('description', 'Backwards dates')
            ->call('save')
            ->assertHasErrors('dueDate');

        $this->assertSame(0, VendorBill::count());
    }

    public function test_the_vendor_bill_screen_settles_a_bill(): void
    {
        $vendor = Vendor::factory()->create();

        $component = Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(VendorBillList::class)
            ->set('vendorId', $vendor->id)
            ->set('accountId', $this->journal->account(AccountCode::Cleaning)->id)
            ->set('amount', '4000.00')
            ->set('billDate', '2026-08-05')
            ->set('dueDate', '2026-09-05')
            ->set('description', 'Cleaning contract')
            ->call('save');

        $bill = VendorBill::firstOrFail();

        $component->call('startPayment', $bill->id)
            ->assertSet('payAmount', '4000.00')
            ->set('payMethod', 'cash')
            ->set('payDate', '2026-09-01')
            ->call('pay')
            ->assertHasNoErrors();

        $this->assertSame(VendorBillStatus::Paid, $bill->fresh()->status);
        $this->assertSame('0.00', $this->journal->balanceFor($this->journal->account(AccountCode::AccountsPayable)));
        $this->assertSame('-4000.00', $this->journal->balanceFor($this->journal->account(AccountCode::CashInHand)));
    }

    public function test_a_committee_member_cannot_settle_a_bill(): void
    {
        $bill = VendorBill::factory()->create();

        Livewire::actingAs($this->userWithRole(Role::Committee))
            ->test(VendorBillList::class)
            ->call('startPayment', $bill->id)
            ->assertForbidden();
    }
}
