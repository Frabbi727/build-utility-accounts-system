<?php

namespace App\Services\Billing;

use App\Enums\BillStatus;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use Illuminate\Support\Collection;

/**
 * Decides which bills a payment settles, oldest first, and what is left over as advance.
 *
 * This is the only place that ordering lives. `RecordPayment` uses it to write the
 * allocations, and the confirmation dialog uses it to tell the operator what their money
 * is about to do — so the preview cannot drift away from what actually happens.
 */
class AllocationPlanner
{
    /**
     * @param  bool  $lock  take a row lock while planning; RecordPayment does, previews do not
     * @return array{lines: list<array{bill: ServiceChargeBill, amount: string}>, allocated: string, advance: string}
     */
    public function plan(Flat $flat, string $amount, bool $lock = false): array
    {
        $amount = bcadd($amount, '0', 2);
        $remaining = $amount;
        $allocated = '0.00';
        $lines = [];

        foreach ($this->outstandingBills($flat, $lock) as $bill) {
            if (bccomp($remaining, '0', 2) <= 0) {
                break;
            }

            $outstanding = $bill->outstandingAmount();

            if (bccomp($outstanding, '0', 2) <= 0) {
                continue;
            }

            $apply = bccomp($remaining, $outstanding, 2) < 0 ? $remaining : $outstanding;

            $lines[] = ['bill' => $bill, 'amount' => $apply];

            $remaining = bcsub($remaining, $apply, 2);
            $allocated = bcadd($allocated, $apply, 2);
        }

        return [
            'lines' => $lines,
            'allocated' => $allocated,
            'advance' => bcsub($amount, $allocated, 2),
        ];
    }

    /**
     * @return Collection<int, ServiceChargeBill>
     */
    private function outstandingBills(Flat $flat, bool $lock): Collection
    {
        $query = ServiceChargeBill::where('flat_id', $flat->id)
            ->whereIn('status', [BillStatus::Unpaid, BillStatus::PartiallyPaid])
            ->orderBy('billing_month')
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }
}
