<?php

namespace App\Http\Controllers;

use App\Models\ServiceChargeBill;
use App\Services\Billing\BillSummary;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The printable bill.
 *
 * A plain controller rather than a Livewire screen, for the same reason as
 * ReceiptController: there is nothing interactive on a print page, and it wants its own
 * bare layout. Single and batch render the same sheet component, so they cannot diverge.
 */
class BillController extends Controller
{
    public function __construct(private readonly BillSummary $summaries) {}

    public function show(ServiceChargeBill $bill): View
    {
        Gate::authorize('view', $bill);

        return view('bills.print', [
            'summaries' => [$this->summaries->for($bill)],
            'title' => __('billing.bill').' — '.$bill->bill_no,
        ]);
    }
}
