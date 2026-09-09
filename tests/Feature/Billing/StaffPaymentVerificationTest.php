<?php

namespace Tests\Feature\Billing;

use App\Enums\AccountCode;
use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentSubmissionStatus;
use App\Enums\Role;
use App\Livewire\Billing\PaymentSubmissionList;
use App\Livewire\Dashboard;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class StaffPaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private User $accountant;

    private User $resident;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->flatRate('3000.00')->create(['due_day_of_month' => 10]);
        app(CurrentBuilding::class)->set($this->building->id);

        $this->accountant = User::factory()->create(['name' => 'Accountant Staff']);
        $this->accountant->assignRole(Role::Accountant->value);

        $this->resident = User::factory()->create(['name' => 'Mr Resident']);
        $this->resident->assignRole(Role::Owner->value);

        $owner = Owner::factory()->create([
            'user_id' => $this->resident->id,
            'name' => 'Mr Resident',
        ]);

        $this->flat = Flat::factory()->for($this->building)->create([
            'owner_id' => $owner->id,
            'number' => '301',
        ]);
    }

    public function test_resident_cannot_access_staff_payment_submissions_screen(): void
    {
        $this->actingAs($this->resident)
            ->get(route('billing.submissions'))
            ->assertForbidden();
    }

    public function test_accountant_can_access_submissions_and_view_pending_queue(): void
    {
        $submission = PaymentSubmission::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat->id,
            'user_id' => $this->resident->id,
            'amount' => '3000.00',
            'payment_method' => PaymentMethod::Bkash,
            'reference_number' => 'TRX-VERIFY-001',
            'status' => PaymentSubmissionStatus::Pending,
        ]);

        $this->actingAs($this->accountant)
            ->get(route('billing.submissions'))
            ->assertOk();

        Livewire::actingAs($this->accountant)
            ->test(PaymentSubmissionList::class)
            ->assertSee('TRX-VERIFY-001')
            ->assertSee('3,000.00')
            ->assertSee('301');
    }

    public function test_accountant_can_approve_submission_which_posts_journal_and_allocates_bills(): void
    {
        // Generate an unpaid bill of 3000 BDT
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-09-01'));

        $submission = PaymentSubmission::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat->id,
            'user_id' => $this->resident->id,
            'amount' => '3000.00',
            'payment_method' => PaymentMethod::Bkash,
            'reference_number' => 'TRX-APPROVE-555',
            'payment_date' => '2026-09-10',
            'status' => PaymentSubmissionStatus::Pending,
        ]);

        Livewire::actingAs($this->accountant)
            ->test(PaymentSubmissionList::class)
            ->call('approve', $submission->id)
            ->assertHasNoErrors();

        $submission->refresh();
        $this->assertSame(PaymentSubmissionStatus::Approved, $submission->status);
        $this->assertSame($this->accountant->id, $submission->reviewed_by);
        $this->assertNotNull($submission->reviewed_at);
        $this->assertNotNull($submission->payment_id);

        $payment = Payment::find($submission->payment_id);
        $this->assertNotNull($payment);
        $this->assertSame('3000.00', $payment->amount);
        $this->assertSame(PaymentMethod::Bkash, $payment->method);
        $this->assertSame('TRX-APPROVE-555', $payment->reference);

        // Bill should be fully allocated and marked paid
        $bill = $this->flat->serviceChargeBills()->first();
        $this->assertNotNull($bill);
        $this->assertSame(BillStatus::Paid, $bill->status);
        $this->assertSame('0.00', $bill->outstandingAmount());

        // Check journal balance for flat receivable is now 0.00
        $journal = app(JournalService::class);
        $receivableAccount = $journal->account(AccountCode::ServiceChargeReceivable);
        $balance = $journal->balanceFor($receivableAccount, $this->flat->id);
        $this->assertSame('0.00', $balance);
    }

    public function test_accountant_can_reject_submission_with_reason(): void
    {
        $submission = PaymentSubmission::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat->id,
            'user_id' => $this->resident->id,
            'amount' => '3000.00',
            'payment_method' => PaymentMethod::Nagad,
            'reference_number' => 'TRX-REJECT-999',
            'status' => PaymentSubmissionStatus::Pending,
        ]);

        Livewire::actingAs($this->accountant)
            ->test(PaymentSubmissionList::class)
            ->call('openRejectModal', $submission->id)
            ->set('rejectionReason', 'Transaction ID could not be verified in Nagad merchant statement.')
            ->call('reject')
            ->assertHasNoErrors();

        $submission->refresh();
        $this->assertSame(PaymentSubmissionStatus::Rejected, $submission->status);
        $this->assertSame('Transaction ID could not be verified in Nagad merchant statement.', $submission->rejection_reason);
        $this->assertSame($this->accountant->id, $submission->reviewed_by);
        $this->assertNotNull($submission->reviewed_at);
        $this->assertNull($submission->payment_id);

        // No payment should have been created
        $this->assertDatabaseMissing('payments', [
            'reference' => 'TRX-REJECT-999',
        ]);
    }

    public function test_staff_dashboard_displays_pending_submission_count(): void
    {
        PaymentSubmission::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat->id,
            'user_id' => $this->resident->id,
            'status' => PaymentSubmissionStatus::Pending,
        ]);

        Livewire::actingAs($this->accountant)
            ->test(Dashboard::class)
            ->assertSee(__('billing.pending_submissions', ['count' => 1]))
            ->assertSee(route('billing.submissions'));
    }
}
