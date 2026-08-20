<?php

namespace App\Services\Billing;

use App\Enums\AccountCode;
use App\Models\PaymentAllocation;
use App\Models\ServiceChargeBill;
use App\Services\JournalService;
use App\Support\BillSummaryData;

/**
 * The only place a bill's "total payable now" is worked out.
 *
 * Bill generation, the printable bill, the batch print and the flat statement all read
 * this, for the same reason ChargeCalculator is the only place a monthly charge is
 * computed: a second implementation would eventually disagree in front of an owner
 * holding a printed bill.
 */
class BillSummary
{
    public function __construct(private readonly JournalService $journal) {}

    public function for(ServiceChargeBill $bill): BillSummaryData
    {
        $bill->loadMissing(['items', 'flat.owner', 'flat.building']);

        $monthCharges = bcadd((string) $bill->total_amount, '0', 2);
        $paid = $bill->allocatedAmount();
        $thisBillOutstanding = bcsub($monthCharges, $paid, 2);

        $totalDue = $this->journal->balanceFor(
            $this->journal->account(AccountCode::ServiceChargeReceivable),
            $bill->flat_id,
        );

        $broughtForward = bcsub($totalDue, $thisBillOutstanding, 2);

        [$priorOpenBills, $priorTotal] = $this->priorOpenBills($bill);

        return new BillSummaryData(
            bill: $bill,
            monthCharges: $monthCharges,
            paidOnThisBill: $paid,
            thisBillOutstanding: $thisBillOutstanding,
            broughtForward: $broughtForward,
            totalDue: $totalDue,
            priorOpenBills: $priorOpenBills,
            unbilledArrears: bcsub($broughtForward, $priorTotal, 2),
            paymentsReceived: $this->paymentsReceived($bill),
            advanceHeld: $this->journal->balanceFor(
                $this->journal->account(AccountCode::AdvanceFromOwners),
                $bill->flat_id,
            ),
        );
    }

    /**
     * The flat's still-open bills older than this one, oldest first, with their total.
     *
     * @return array{0: list<array{bill: ServiceChargeBill, outstanding: string}>, 1: string}
     */
    private function priorOpenBills(ServiceChargeBill $bill): array
    {
        $earlier = ServiceChargeBill::query()
            ->where('flat_id', $bill->flat_id)
            ->where('id', '!=', $bill->id)
            ->where(function ($query) use ($bill): void {
                $query->whereDate('billing_month', '<', $bill->billing_month)
                    ->orWhere(function ($same) use ($bill): void {
                        // Same month, earlier row — possible only for legacy data, but
                        // ordering must still be total or the listing is unstable.
                        $same->whereDate('billing_month', $bill->billing_month)
                            ->where('id', '<', $bill->id);
                    });
            })
            ->orderBy('billing_month')
            ->orderBy('id')
            ->get();

        $rows = [];
        $total = '0.00';

        foreach ($earlier as $old) {
            $outstanding = $old->outstandingAmount();

            if (bccomp($outstanding, '0', 2) <= 0) {
                continue;
            }

            $rows[] = ['bill' => $old, 'outstanding' => $outstanding];
            $total = bcadd($total, $outstanding, 2);
        }

        return [$rows, $total];
    }

    /**
     * Allocations against this bill, oldest receipt first.
     *
     * @return list<PaymentAllocation>
     */
    private function paymentsReceived(ServiceChargeBill $bill): array
    {
        return PaymentAllocation::query()
            ->with('payment')
            ->where('service_charge_bill_id', $bill->id)
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->orderBy('payments.received_on')
            ->orderBy('payments.id')
            ->select('payment_allocations.*')
            ->get()
            ->all();
    }
}
