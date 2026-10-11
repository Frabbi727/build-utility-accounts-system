<?php

namespace App\Services\Billing;

use App\Enums\AccountCode;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Services\JournalService;
use App\Support\ResidentBalanceData;

/**
 * Single source of truth for resident balances across web and mobile.
 */
class ResidentBalanceService
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly BillSummary $billSummary,
    ) {}

    public function forFlat(Flat $flat): ResidentBalanceData
    {
        $latestBill = ServiceChargeBill::where('flat_id', $flat->id)
            ->with(['items', 'flat.building', 'flat.owner'])
            ->latest('billing_month')
            ->first();

        $serviceChargeReceivable = $this->journal->account(AccountCode::ServiceChargeReceivable);
        $advanceAccount = $this->journal->account(AccountCode::AdvanceFromOwners);

        $grossTotalDue = $this->journal->balanceFor($serviceChargeReceivable, $flat->id);
        $grossAdvanceHeld = $this->journal->balanceFor($advanceAccount, $flat->id);

        $summaryData = null;
        if ($latestBill !== null) {
            $summaryData = $this->billSummary->for($latestBill);
            $currentMonthCharges = $summaryData->monthCharges;
            $grossArrears = $summaryData->broughtForward;
        } else {
            $currentMonthCharges = '0.00';
            $grossArrears = $grossTotalDue;
        }

        // Net amounts for resident presentation (advances cover outstanding dues)
        $totalDue = bccomp($grossTotalDue, $grossAdvanceHeld, 2) > 0
            ? bcsub($grossTotalDue, $grossAdvanceHeld, 2)
            : '0.00';

        $advanceHeld = bccomp($grossAdvanceHeld, $grossTotalDue, 2) > 0
            ? bcsub($grossAdvanceHeld, $grossTotalDue, 2)
            : '0.00';

        $arrears = bccomp($grossArrears, $grossAdvanceHeld, 2) > 0
            ? bcsub($grossArrears, $grossAdvanceHeld, 2)
            : '0.00';

        return new ResidentBalanceData(
            flat: $flat,
            latestBill: $latestBill,
            billSummary: $summaryData,
            grossTotalDue: $grossTotalDue,
            grossAdvanceHeld: $grossAdvanceHeld,
            grossArrears: $grossArrears,
            currentMonthCharges: $currentMonthCharges,
            totalDue: $totalDue,
            advanceHeld: $advanceHeld,
            arrears: $arrears,
        );
    }
}
