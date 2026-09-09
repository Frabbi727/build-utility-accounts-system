<?php

namespace Tests\Feature\Billing;

use App\Enums\Role;
use App\Livewire\Dashboard;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ResidentPaymentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private User $ownerUser;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->flatRate('3500.00')->create(['due_day_of_month' => 10]);
        app(CurrentBuilding::class)->set($this->building->id);

        $this->ownerUser = User::factory()->create(['name' => 'John Resident']);
        $this->ownerUser->assignRole(Role::Owner->value);

        $owner = Owner::factory()->create([
            'user_id' => $this->ownerUser->id,
            'name' => 'John Resident',
        ]);

        $this->flat = Flat::factory()->for($this->building)->create([
            'owner_id' => $owner->id,
            'number' => '402',
        ]);
    }

    public function test_resident_can_open_and_close_payment_modal(): void
    {
        Livewire::actingAs($this->ownerUser)
            ->test(Dashboard::class)
            ->assertSet('showPaymentModal', false)
            ->call('openPaymentModal')
            ->assertSet('showPaymentModal', true)
            ->call('cancelPaymentModal')
            ->assertSet('showPaymentModal', false);
    }

    public function test_resident_can_submit_payment_with_valid_details(): void
    {
        Livewire::actingAs($this->ownerUser)
            ->test(Dashboard::class)
            ->call('openPaymentModal')
            ->set('submissionAmount', '3500.00')
            ->set('submissionMethod', 'bkash')
            ->set('submissionReference', 'TRX998877')
            ->set('submissionDate', '2026-09-10')
            ->set('submissionNotes', 'Paid via bKash personal')
            ->call('submitPayment')
            ->assertHasNoErrors()
            ->assertSet('showPaymentModal', false);

        $this->assertDatabaseHas('payment_submissions', [
            'building_id' => $this->building->id,
            'flat_id' => $this->flat->id,
            'user_id' => $this->ownerUser->id,
            'amount' => '3500.00',
            'payment_method' => 'bkash',
            'reference_number' => 'TRX998877',
            'status' => 'pending',
            'resident_notes' => 'Paid via bKash personal',
        ]);
    }

    public function test_submission_requires_amount_method_and_reference(): void
    {
        Livewire::actingAs($this->ownerUser)
            ->test(Dashboard::class)
            ->call('openPaymentModal')
            ->set('submissionAmount', '')
            ->set('submissionMethod', '')
            ->set('submissionReference', '')
            ->call('submitPayment')
            ->assertHasErrors(['submissionAmount', 'submissionMethod', 'submissionReference']);
    }

    public function test_resident_can_upload_payment_slip_attachment(): void
    {
        Storage::fake('public');

        $slip = UploadedFile::fake()->image('slip.jpg');

        Livewire::actingAs($this->ownerUser)
            ->test(Dashboard::class)
            ->call('openPaymentModal')
            ->set('submissionAmount', '2000.00')
            ->set('submissionMethod', 'bank')
            ->set('submissionReference', 'DEP-443322')
            ->set('submissionSlip', $slip)
            ->call('submitPayment')
            ->assertHasNoErrors();

        $submission = PaymentSubmission::where('reference_number', 'DEP-443322')->firstOrFail();
        $this->assertNotNull($submission->slip_path);
        Storage::disk('public')->assertExists($submission->slip_path);
    }

    public function test_resident_sees_pending_submissions_on_dashboard(): void
    {
        PaymentSubmission::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat->id,
            'user_id' => $this->ownerUser->id,
            'reference_number' => 'TRX-VISIBLE-123',
            'amount' => '3500.00',
        ]);

        Livewire::actingAs($this->ownerUser)
            ->test(Dashboard::class)
            ->assertSee('TRX-VISIBLE-123')
            ->assertSee(__('billing.pending'));
    }
}
