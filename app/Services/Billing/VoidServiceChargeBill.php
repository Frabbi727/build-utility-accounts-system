<?php

namespace App\Services\Billing;

use App\Enums\BillStatus;
use App\Exceptions\CannotVoidBillException;
use App\Models\AdHocCharge;
use App\Models\CostDistributionLine;
use App\Models\MeterReading;
use App\Models\ServiceChargeBill;
use App\Services\JournalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VoidServiceChargeBill
{
    public function __construct(
        private readonly JournalService $journal,
    ) {}

    public function handle(
        ServiceChargeBill $bill,
        string $reason,
        ?Carbon $voidDate = null,
    ): ServiceChargeBill {
        if ($bill->status === BillStatus::Voided) {
            throw CannotVoidBillException::becauseAlreadyVoided($bill);
        }

        if ($bill->allocations()->exists()) {
            throw CannotVoidBillException::becausePaid($bill);
        }

        $effectiveDate = $voidDate ?? now();
        $period = $this->journal->periodFor($effectiveDate);

        if ($period->isLocked()) {
            throw CannotVoidBillException::becausePeriodLocked($bill);
        }

        return DB::transaction(function () use ($bill, $reason, $effectiveDate): ServiceChargeBill {
            $unreversedEntries = $bill->journalEntries()
                ->whereNull('reversed_by_id')
                ->get();

            foreach ($unreversedEntries as $entry) {
                $this->journal->reverse(
                    $entry,
                    $effectiveDate,
                    "Void bill {$bill->bill_no}: {$reason}",
                );
            }

            $bill->status = BillStatus::Voided;
            $bill->save();

            MeterReading::where('applied_to_bill_id', $bill->id)->update(['applied_to_bill_id' => null]);
            AdHocCharge::where('applied_to_bill_id', $bill->id)->update(['applied_to_bill_id' => null]);
            CostDistributionLine::where('applied_to_bill_id', $bill->id)->update(['applied_to_bill_id' => null]);

            return $bill->fresh();
        });
    }
}
