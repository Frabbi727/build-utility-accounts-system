<?php

namespace App\Services\Expenses;

use App\Enums\AccountCode;
use App\Enums\VendorBillStatus;
use App\Exceptions\InvalidJournalEntryException;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Services\JournalService;
use App\Support\JournalLineData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records a vendor bill as a payable, so expenses are recognised when incurred.
 *
 * Dr (each line's expense account)
 *     Cr Accounts Payable
 */
class RecordVendorBill
{
    public function __construct(private readonly JournalService $journal) {}

    /**
     * @param  list<array{account_id: int, description: string, amount: string}>  $items
     */
    public function handle(
        Vendor $vendor,
        Carbon $billDate,
        Carbon $dueDate,
        string $description,
        array $items,
        ?int $buildingId = null,
        ?string $vendorReference = null,
    ): VendorBill {
        if ($items === []) {
            throw new InvalidJournalEntryException('A vendor bill needs at least one line item.');
        }

        $total = '0.00';
        foreach ($items as $item) {
            if (bccomp($item['amount'], '0', 2) <= 0) {
                throw new InvalidJournalEntryException('Vendor bill line amounts must be positive.');
            }
            $total = bcadd($total, $item['amount'], 2);
        }

        return DB::transaction(function () use ($vendor, $billDate, $dueDate, $description, $items, $total, $buildingId, $vendorReference): VendorBill {
            $bill = VendorBill::create([
                'vendor_id' => $vendor->id,
                'building_id' => $buildingId,
                'bill_no' => $this->nextBillNo($billDate),
                'vendor_reference' => $vendorReference,
                'bill_date' => $billDate,
                'due_date' => $dueDate,
                'total_amount' => $total,
                'status' => VendorBillStatus::Unpaid,
                'description' => $description,
            ]);

            $lines = [];

            foreach ($items as $item) {
                $bill->items()->create($item);

                $lines[] = JournalLineData::debit(
                    $item['account_id'],
                    $item['amount'],
                    null,
                    $bill->bill_no,
                );
            }

            $lines[] = JournalLineData::credit(
                $this->journal->account(AccountCode::AccountsPayable),
                $total,
                null,
                $bill->bill_no,
            );

            $this->journal->post(
                $billDate,
                __('expenses.vendor_bill_posted', ['vendor' => $vendor->name, 'bill' => $bill->bill_no]),
                $lines,
                $bill,
            );

            return $bill->load('items');
        });
    }

    private function nextBillNo(Carbon $date): string
    {
        $prefix = 'VB-'.$date->format('Ym').'-';

        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$prefix]);

        $sequence = VendorBill::where('bill_no', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
