<?php

namespace Tests\Feature\Billing;

use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Livewire\PaymentList;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use App\Services\Billing\ReversePayment;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentListReversalTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private User $accountant;

    private Flat $flat;

    private JournalService $journal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->journal = app(JournalService::class);

        $this->building = Building::factory()->flatRate('3000.00')->create();
        app(CurrentBuilding::class)->set($this->building->id);

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole(Role::Accountant->value);

        $this->flat = Flat::factory()->for($this->building)->create();
    }

    public function test_accountant_can_filter_payments_by_status(): void
    {
        $this->actingAs($this->accountant);

        // Record a payment
        $payment1 = app(RecordPayment::class)->handle(
            $this->flat,
            '3000.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-10')
        );

        $payment2 = app(RecordPayment::class)->handle(
            $this->flat,
            '2000.00',
            PaymentMethod::Bank,
            Carbon::parse('2026-08-12')
        );

        // Reverse payment 2
        app(ReversePayment::class)->handle($payment2, 'Invalid EFT');

        Livewire::test(PaymentList::class)
            ->set('statusFilter', 'active')
            ->assertSee($payment1->receipt_no)
            ->assertDontSee($payment2->receipt_no)
            ->set('statusFilter', 'reversed')
            ->assertSee($payment2->receipt_no)
            ->assertDontSee($payment1->receipt_no);
    }

    public function test_accountant_can_reverse_payment_through_modal(): void
    {
        $this->actingAs($this->accountant);

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        $payment = app(RecordPayment::class)->handle(
            $this->flat,
            '3000.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-10')
        );

        $bill = $this->flat->serviceChargeBills()->firstOrFail();
        $this->assertSame(BillStatus::Paid, $bill->status);

        Livewire::test(PaymentList::class)
            ->call('openReversalModal', $payment->id)
            ->assertSet('showReversalModal', true)
            ->assertSet('reversingPaymentId', $payment->id)
            ->set('reversalReason', 'Cheque bounced at clearing')
            ->call('reversePayment')
            ->assertSet('showReversalModal', false)
            ->assertHasNoErrors();

        $payment->refresh();
        $this->assertTrue($payment->isReversed());
        $this->assertSame('Cheque bounced at clearing', $payment->reversal_reason);

        $bill->refresh();
        $this->assertSame(BillStatus::Unpaid, $bill->status);
    }
}
