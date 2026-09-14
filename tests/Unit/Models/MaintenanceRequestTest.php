<?php

namespace Tests\Unit\Models;

use App\Enums\MaintenanceStatus;
use App\Models\Building;
use App\Models\Flat;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestActivity;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_it_has_expected_casts_and_defaults(): void
    {
        $request = MaintenanceRequest::factory()->create([
            'cost' => '1500.50',
            'rating' => 5,
            'rating_comment' => 'Excellent repair',
        ]);

        $this->assertSame('1500.50', $request->cost);
        $this->assertSame(5, $request->rating);
        $this->assertSame('Excellent repair', $request->rating_comment);
    }

    public function test_it_identifies_overdue_tickets_accurately(): void
    {
        $overdueRequest = MaintenanceRequest::factory()->create([
            'status' => MaintenanceStatus::Open,
            'due_by' => now()->subHours(2),
        ]);

        $this->assertTrue($overdueRequest->isOverdue());
        $this->assertSame('overdue', $overdueRequest->slaStatus());

        $onTrackRequest = MaintenanceRequest::factory()->create([
            'status' => MaintenanceStatus::Open,
            'due_by' => now()->addHours(24),
        ]);

        $this->assertFalse($onTrackRequest->isOverdue());
        $this->assertSame('on_track', $onTrackRequest->slaStatus());

        $resolvedOnTime = MaintenanceRequest::factory()->create([
            'status' => MaintenanceStatus::Resolved,
            'due_by' => now()->addHours(24),
            'resolved_at' => now(),
        ]);

        $this->assertFalse($resolvedOnTime->isOverdue());
        $this->assertSame('resolved_on_time', $resolvedOnTime->slaStatus());

        $resolvedLate = MaintenanceRequest::factory()->create([
            'status' => MaintenanceStatus::Resolved,
            'due_by' => now()->subHours(5),
            'resolved_at' => now()->subHours(2),
        ]);

        $this->assertTrue($resolvedLate->isOverdue());
        $this->assertSame('resolved_late', $resolvedLate->slaStatus());
    }

    public function test_it_relates_to_vendor_bills_and_activities(): void
    {
        $building = Building::factory()->create();
        $flat = Flat::factory()->for($building)->create();
        $user = User::factory()->create();
        $vendor = Vendor::factory()->create();

        $request = MaintenanceRequest::factory()->create([
            'building_id' => $building->id,
            'flat_id' => $flat->id,
            'user_id' => $user->id,
            'assigned_vendor_id' => $vendor->id,
        ]);

        $bill = VendorBill::factory()->create([
            'vendor_id' => $vendor->id,
            'building_id' => $building->id,
            'maintenance_request_id' => $request->id,
        ]);

        $activity = MaintenanceRequestActivity::factory()->create([
            'maintenance_request_id' => $request->id,
            'user_id' => $user->id,
            'type' => 'assigned',
            'description' => 'Vendor assigned',
        ]);

        $this->assertCount(1, $request->vendorBills);
        $this->assertTrue($request->vendorBills->first()->is($bill));
        $this->assertCount(1, $request->activities);
        $this->assertTrue($request->activities->first()->is($activity));
        $this->assertTrue($bill->maintenanceRequest->is($request));
    }
}
