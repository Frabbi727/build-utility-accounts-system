<?php

namespace Tests\Feature\Api;

use App\Enums\PaymentMethod;
use App\Enums\PaymentSubmissionStatus;
use App\Enums\Role;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResidentPaymentSubmissionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_resident_can_submit_offline_payment_with_slip(): void
    {
        Storage::fake('public');

        $building = Building::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);

        $file = UploadedFile::fake()->image('bkash_slip.jpg');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/resident/payment-submissions', [
            'flat_id' => $flat->id,
            'amount' => '3500.00',
            'payment_method' => PaymentMethod::Bkash->value,
            'reference_number' => 'TRX8971239',
            'payment_date' => now()->toDateString(),
            'resident_notes' => 'Paid via personal bKash',
            'slip' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reference_number', 'TRX8971239')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('payment_submissions', [
            'flat_id' => $flat->id,
            'amount' => '3500.00',
            'reference_number' => 'TRX8971239',
            'status' => 'pending',
        ]);
    }

    public function test_resident_can_list_their_submissions(): void
    {
        $building = Building::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);

        PaymentSubmission::factory()->create([
            'building_id' => $building->id,
            'flat_id' => $flat->id,
            'user_id' => $user->id,
            'reference_number' => 'TRX-101',
            'status' => PaymentSubmissionStatus::Pending,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/resident/payment-submissions?flat_id='.$flat->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.reference_number', 'TRX-101');
    }

    public function test_resident_can_list_posted_payments_with_receipts(): void
    {
        $building = Building::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);

        Payment::factory()->create([
            'flat_id' => $flat->id,
            'receipt_no' => 'REC-2026-001',
            'amount' => '2500.00',
            'method' => PaymentMethod::Bkash,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/resident/payments?flat_id='.$flat->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.receipt_no', 'REC-2026-001');
    }

    public function test_resident_forbidden_from_submitting_payment_for_other_flat(): void
    {
        $userA = User::factory()->create();
        $userA->assignRole(Role::Owner->value);
        $ownerA = Owner::factory()->create(['user_id' => $userA->id]);
        Flat::factory()->create(['owner_id' => $ownerA->id]);

        $userB = User::factory()->create();
        $userB->assignRole(Role::Owner->value);
        $ownerB = Owner::factory()->create(['user_id' => $userB->id]);
        $flatB = Flat::factory()->create(['owner_id' => $ownerB->id]);

        $response = $this->actingAs($userA, 'sanctum')->postJson('/api/v1/resident/payment-submissions', [
            'flat_id' => $flatB->id,
            'amount' => '2000.00',
            'payment_method' => PaymentMethod::Bkash->value,
            'reference_number' => 'TRX000',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertStatus(403);
    }

    public function test_resident_cannot_submit_duplicate_reference_number(): void
    {
        $building = Building::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);

        $payload = [
            'flat_id' => $flat->id,
            'amount' => '3500.00',
            'payment_method' => PaymentMethod::Bkash->value,
            'reference_number' => 'DUPTRX12345',
            'payment_date' => now()->toDateString(),
        ];

        $first = $this->actingAs($user, 'sanctum')->postJson('/api/v1/resident/payment-submissions', $payload);
        $first->assertStatus(201);

        $second = $this->actingAs($user, 'sanctum')->postJson('/api/v1/resident/payment-submissions', $payload);
        $second->assertStatus(422)
            ->assertJsonValidationErrors(['reference_number']);
    }
}
