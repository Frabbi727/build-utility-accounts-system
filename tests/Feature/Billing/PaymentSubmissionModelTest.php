<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentMethod;
use App\Enums\PaymentSubmissionStatus;
use App\Enums\Role;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PaymentSubmissionModelTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private User $residentUser;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->building = Building::factory()->create();
        app(CurrentBuilding::class)->set($this->building->id);

        $this->residentUser = User::factory()->create();
        $this->residentUser->assignRole(Role::Owner->value);

        $owner = Owner::factory()->create(['user_id' => $this->residentUser->id]);
        $this->flat = Flat::factory()->for($this->building)->for($owner)->create(['number' => '5A']);
    }

    public function test_it_creates_a_payment_submission_with_factory(): void
    {
        $submission = PaymentSubmission::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat->id,
            'user_id' => $this->residentUser->id,
            'amount' => '4500.00',
            'payment_method' => PaymentMethod::Bkash,
            'reference_number' => 'TRX12345678',
            'status' => PaymentSubmissionStatus::Pending,
        ]);

        $this->assertDatabaseHas('payment_submissions', [
            'id' => $submission->id,
            'amount' => '4500.00',
            'reference_number' => 'TRX12345678',
            'status' => 'pending',
        ]);

        $this->assertSame($this->flat->id, $submission->flat->id);
        $this->assertSame($this->residentUser->id, $submission->user->id);
        $this->assertSame($this->building->id, $submission->building->id);
        $this->assertSame(PaymentSubmissionStatus::Pending, $submission->status);
    }

    public function test_owner_policy_authorizes_own_submissions_only(): void
    {
        $submission = PaymentSubmission::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat->id,
            'user_id' => $this->residentUser->id,
        ]);

        $otherResident = User::factory()->create();
        $otherResident->assignRole(Role::Owner->value);

        $this->assertTrue(Gate::forUser($this->residentUser)->allows('view', $submission));
        $this->assertFalse(Gate::forUser($otherResident)->allows('view', $submission));
    }

    public function test_accountant_can_view_and_manage_submissions(): void
    {
        $accountant = User::factory()->create();
        $accountant->assignRole(Role::Accountant->value);

        $submission = PaymentSubmission::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat->id,
            'user_id' => $this->residentUser->id,
        ]);

        $this->assertTrue(Gate::forUser($accountant)->allows('view', $submission));
        $this->assertTrue(Gate::forUser($accountant)->allows('manage', $submission));
    }
}
