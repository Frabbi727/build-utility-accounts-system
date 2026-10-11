<?php

namespace Tests\Feature\Api;

use App\Enums\AccountCode;
use App\Enums\BillStatus;
use App\Enums\Role;
use App\Models\BillItem;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\ServiceChargeBill;
use App\Models\User;
use App\Services\JournalService;
use App\Support\JournalLineData;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentBillsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_resident_can_filter_and_paginate_bills(): void
    {
        $building = Building::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);

        foreach (['2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01', '2026-05-01'] as $month) {
            ServiceChargeBill::factory()->create([
                'flat_id' => $flat->id,
                'status' => BillStatus::Unpaid,
                'billing_month' => $month,
            ]);
        }

        foreach (['2026-06-01', '2026-07-01'] as $month) {
            ServiceChargeBill::factory()->create([
                'flat_id' => $flat->id,
                'status' => BillStatus::Paid,
                'billing_month' => $month,
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')->getJson(
            '/api/v1/resident/bills?flat_id='.$flat->id.'&status=unpaid&per_page=3'
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonCount(3, 'data');
    }

    public function test_resident_can_view_single_bill_itemized_breakdown(): void
    {
        $building = Building::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);

        $bill = ServiceChargeBill::factory()->create([
            'flat_id' => $flat->id,
            'status' => BillStatus::Unpaid,
            'total_amount' => '4500.00',
        ]);

        BillItem::factory()->create([
            'service_charge_bill_id' => $bill->id,
            'description' => 'Lift Maintenance',
            'amount' => '1500.00',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/resident/bills/'.$bill->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bill_no', $bill->bill_no)
            ->assertJsonPath('data.total_amount', '4500.00')
            ->assertJsonPath('data.items.0.description', 'Lift Maintenance');
    }

    public function test_resident_forbidden_from_viewing_another_flat_bill(): void
    {
        $userA = User::factory()->create();
        $userA->assignRole(Role::Owner->value);
        $ownerA = Owner::factory()->create(['user_id' => $userA->id]);
        Flat::factory()->create(['owner_id' => $ownerA->id]);

        $userB = User::factory()->create();
        $userB->assignRole(Role::Owner->value);
        $ownerB = Owner::factory()->create(['user_id' => $userB->id]);
        $flatB = Flat::factory()->create(['owner_id' => $ownerB->id]);

        $billB = ServiceChargeBill::factory()->create(['flat_id' => $flatB->id]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/resident/bills/'.$billB->id);

        $response->assertStatus(403);
    }

    public function test_resident_bill_details_reflect_net_balances_when_advance_is_held(): void
    {
        $building = Building::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);

        $bill = ServiceChargeBill::factory()->create([
            'flat_id' => $flat->id,
            'status' => BillStatus::Paid,
            'total_amount' => '1000.00',
        ]);

        $journal = app(JournalService::class);
        $rec = $journal->account(AccountCode::ServiceChargeReceivable);
        $adv = $journal->account(AccountCode::AdvanceFromOwners);

        $journal->post(
            now(),
            'Test balances',
            [
                JournalLineData::debit($rec, '500.00', $flat->id),
                JournalLineData::credit($adv, '2000.00', $flat->id),
                JournalLineData::debit($journal->account(AccountCode::Bank), '1500.00'),
            ],
            $flat
        );

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/resident/bills/'.$bill->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_due', '0.00')
            ->assertJsonPath('data.advance_held', '1500.00')
            ->assertJsonPath('data.arrears', '0.00')
            ->assertJsonPath('data.gross_total_due', '500.00')
            ->assertJsonPath('data.gross_advance_held', '2000.00');
    }
}
