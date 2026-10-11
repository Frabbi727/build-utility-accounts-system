<?php

namespace App\Support;

use App\Models\Flat;
use App\Models\ServiceChargeBill;

final readonly class ResidentBalanceData
{
    public function __construct(
        public Flat $flat,
        public ?ServiceChargeBill $latestBill,
        public ?BillSummaryData $billSummary,
        public string $grossTotalDue,
        public string $grossAdvanceHeld,
        public string $grossArrears,
        public string $currentMonthCharges,
        public string $totalDue,
        public string $advanceHeld,
        public string $arrears,
    ) {}
}
