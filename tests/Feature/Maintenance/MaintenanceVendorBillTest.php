<?php

namespace Tests\Feature\Maintenance;

use App\Enums\AccountCode;
use App\Enums\MaintenanceStatus;
use App\Enums\VendorBillStatus;
use App\Models\Account;
use App\Models\Building;
use App\Models\Flat;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Services\JournalService;
use App\Services\Maintenance\CreateVendorBillForTicket;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceVendorBillTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    private User $user;

    private Vendor $vendor;

    private Account $expenseAccount;

    private CreateVendorBillForTicket $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->building = Building::factory()->create();
        $this->flat = Flat::factory()->for($this->building)->create();
        $this->user = User::factory()->create();
        $this->vendor = Vendor::factory()->create(['name' => 'Apex Plumbing Services']);

        $this->expenseAccount = app(JournalService::class)->account(AccountCode::RepairsMaintenance);
        $this->service = app(CreateVendorBillForTicket::class);
    }

    public function test_it_creates_vendor_bill_and_links_to_maintenance_ticket(): void
    {
        $request = MaintenanceRequest::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat->id,
            'user_id' => $this->user->id,
            'status' => MaintenanceStatus::Open,
            'cost' => '0.00',
        ]);

        $bill = $this->service->handle(
            $request,
            $this->vendor,
            '3500.00',
            $this->expenseAccount->id,
            'Pipe replacement and emergency leak repair'
        );

        $this->assertSame(VendorBillStatus::Unpaid, $bill->status);
        $this->assertSame('3500.00', $bill->total_amount);
        $this->assertSame($request->id, $bill->maintenance_request_id);

        $request->refresh();
        $this->assertSame('3500.00', $request->cost);
        $this->assertSame(MaintenanceStatus::InProgress, $request->status);
        $this->assertSame($this->vendor->id, $request->assigned_vendor_id);

        $this->assertDatabaseHas('maintenance_request_activities', [
            'maintenance_request_id' => $request->id,
            'type' => 'vendor_bill_created',
        ]);
    }
}
