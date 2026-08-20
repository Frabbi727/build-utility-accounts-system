<?php

namespace App\Support;

use App\Models\PaymentAllocation;
use App\Models\ServiceChargeBill;

/**
 * What one bill actually asks an owner to pay, with the working shown.
 *
 * `broughtForward` is derived by subtracting this bill's outstanding from the flat's
 * ledger receivable, never by summing older bills. That makes
 * `broughtForward + thisBillOutstanding === totalDue` true by construction, so a printed
 * bill can never disagree with the dues report or the trial balance.
 *
 * `priorOpenBills` is therefore a *detail listing*, not the source of the figure.
 * `unbilledArrears` is whatever the listing does not account for — a receivable posted
 * with no bill behind it, such as an opening balance — so the column still adds up.
 */
final readonly class BillSummaryData
{
    /**
     * @param  list<array{bill: ServiceChargeBill, outstanding: string}>  $priorOpenBills
     * @param  list<PaymentAllocation>  $paymentsReceived  each with its `payment` loaded
     */
    public function __construct(
        public ServiceChargeBill $bill,
        public string $monthCharges,
        public string $paidOnThisBill,
        public string $thisBillOutstanding,
        public string $broughtForward,
        public string $totalDue,
        public array $priorOpenBills,
        public string $unbilledArrears,
        public array $paymentsReceived,
        public string $advanceHeld,
    ) {}

    public function hasArrears(): bool
    {
        return bccomp($this->broughtForward, '0', 2) !== 0;
    }

    public function hasUnbilledArrears(): bool
    {
        return bccomp($this->unbilledArrears, '0', 2) !== 0;
    }

    public function hasAdvance(): bool
    {
        return bccomp($this->advanceHeld, '0', 2) > 0;
    }

    public function hasPayments(): bool
    {
        return $this->paymentsReceived !== [];
    }
}
