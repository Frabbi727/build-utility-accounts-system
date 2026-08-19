<?php

namespace Tests\Feature\Livewire;

use App\Enums\AccountCode;
use App\Enums\Role;
use App\Livewire\GenerateBills;
use App\Livewire\RecordPaymentForm;
use App\Livewire\TrialBalance;
use App\Models\Building;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Models\User;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BillingScreensTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->flatRate('3000.00')->create();
        $this->flat = Flat::factory()->for($this->building)->create();
    }

    private function accountant(): User
    {
        $user = User::factory()->create();
        $user->syncRoles([Role::Accountant->value]);

        return $user;
    }

    public function test_the_generate_bills_screen_posts_accruals(): void
    {
        Livewire::actingAs($this->accountant())
            ->test(GenerateBills::class)
            ->set('buildingId', $this->building->id)
            ->set('month', '2026-08')
            ->call('generate')
            ->assertHasNoErrors();

        $this->assertSame(1, ServiceChargeBill::count());
        $this->assertSame('3000.00', app(JournalService::class)->balanceFor(
            app(JournalService::class)->account(AccountCode::ServiceChargeReceivable),
            $this->flat->id,
        ));
    }

    public function test_the_generate_bills_screen_validates_its_input(): void
    {
        Livewire::actingAs($this->accountant())
            ->test(GenerateBills::class)
            ->set('buildingId', null)
            ->set('month', 'not-a-month')
            ->call('generate')
            ->assertHasErrors(['buildingId', 'month']);

        $this->assertSame(0, ServiceChargeBill::count());
    }

    public function test_the_payment_screen_records_and_allocates(): void
    {
        Livewire::actingAs($this->accountant())
            ->test(GenerateBills::class)
            ->set('buildingId', $this->building->id)
            ->set('month', '2026-08')
            ->call('generate');

        Livewire::actingAs($this->accountant())
            ->test(RecordPaymentForm::class)
            ->set('flatId', $this->flat->id)
            ->set('amount', '3000.00')
            ->set('method', 'cash')
            ->set('receivedOn', '2026-08-15')
            ->call('save')
            ->assertHasNoErrors();

        $journal = app(JournalService::class);

        $this->assertSame('0.00', $journal->balanceFor(
            $journal->account(AccountCode::ServiceChargeReceivable), $this->flat->id
        ));
        $this->assertSame('3000.00', $journal->balanceFor($journal->account(AccountCode::CashInHand)));
    }

    public function test_the_payment_screen_rejects_a_non_positive_amount(): void
    {
        Livewire::actingAs($this->accountant())
            ->test(RecordPaymentForm::class)
            ->set('flatId', $this->flat->id)
            ->set('amount', '0')
            ->set('receivedOn', '2026-08-15')
            ->call('save')
            ->assertHasErrors('amount');
    }

    public function test_a_committee_member_cannot_call_the_generate_action(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([Role::Committee->value]);

        Livewire::actingAs($user)
            ->test(GenerateBills::class)
            ->set('buildingId', $this->building->id)
            ->set('month', '2026-08')
            ->call('generate')
            ->assertForbidden();

        $this->assertSame(0, ServiceChargeBill::count());
    }

    public function test_the_trial_balance_screen_reports_a_balanced_ledger(): void
    {
        Livewire::actingAs($this->accountant())
            ->test(GenerateBills::class)
            ->set('buildingId', $this->building->id)
            ->set('month', '2026-08')
            ->call('generate');

        Livewire::actingAs($this->accountant())
            ->test(TrialBalance::class)
            ->set('asOf', '2026-12-31')
            ->assertViewHas('isBalanced', true)
            ->assertViewHas('totalDebit', '3000.00')
            ->assertViewHas('totalCredit', '3000.00');
    }
}
