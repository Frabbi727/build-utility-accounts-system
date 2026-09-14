<?php

namespace App\Services\Maintenance;

use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRequest;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Services\Expenses\RecordVendorBill;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateVendorBillForTicket
{
    public function __construct(
        private readonly RecordVendorBill $recordVendorBill,
        private readonly MaintenanceTimelineService $timelineService,
    ) {}

    public function handle(
        MaintenanceRequest $request,
        Vendor $vendor,
        string $amount,
        int $expenseAccountId,
        string $description,
        ?Carbon $billDate = null,
        ?Carbon $dueDate = null,
        ?string $vendorReference = null,
    ): VendorBill {
        $billDate = $billDate ?? now();
        $dueDate = $dueDate ?? now()->addDays(15);

        return DB::transaction(function () use ($request, $vendor, $amount, $expenseAccountId, $description, $billDate, $dueDate, $vendorReference): VendorBill {
            $items = [
                [
                    'account_id' => $expenseAccountId,
                    'description' => $description,
                    'amount' => $amount,
                ],
            ];

            $bill = $this->recordVendorBill->handle(
                $vendor,
                $billDate,
                $dueDate,
                $description,
                $items,
                $request->building_id,
                $vendorReference,
                $request->id,
            );

            // Update ticket cumulative repair cost
            $request->cost = bcadd((string) $request->cost, $amount, 2);
            if ($request->status === MaintenanceStatus::Open) {
                $request->status = MaintenanceStatus::InProgress;
            }
            $request->assigned_vendor_id = $vendor->id;
            $request->save();

            // Record timeline activity
            $this->timelineService->record(
                $request,
                'vendor_bill_created',
                "Generated Vendor Bill {$bill->bill_no} for amount {$amount} (Vendor: {$vendor->name})",
                [
                    'bill_id' => $bill->id,
                    'bill_no' => $bill->bill_no,
                    'vendor_id' => $vendor->id,
                    'vendor_name' => $vendor->name,
                    'amount' => $amount,
                ]
            );

            return $bill;
        });
    }
}
