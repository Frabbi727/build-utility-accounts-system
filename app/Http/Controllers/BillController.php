<?php

namespace App\Http\Controllers;

use App\Models\ServiceChargeBill;
use App\Services\Billing\BillSummary;
use App\Support\CurrentBuilding;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    /**
     * Every bill for one month in the building being worked in, one sheet per page.
     *
     * Building scope comes from CurrentBuilding rather than a query parameter: a batch
     * print is an operational action, and operational screens are always scoped to the
     * building the operator has selected.
     */
    public function month(Request $request, CurrentBuilding $current): View
    {
        Gate::authorize('viewAny', ServiceChargeBill::class);

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = Carbon::parse(($validated['month'] ?? now()->format('Y-m')).'-01')->startOfMonth();

        $building = $current->get();

        $bills = $building === null
            ? collect()
            : ServiceChargeBill::query()
                ->whereIn('flat_id', $building->flats()->select('id'))
                ->whereDate('billing_month', $month)
                ->with(['items', 'flat.owner', 'flat.building'])
                ->join('flats', 'flats.id', '=', 'service_charge_bills.flat_id')
                ->orderBy('flats.number')
                ->select('service_charge_bills.*')
                ->get();

        return view('bills.print', [
            'summaries' => $bills->map(fn (ServiceChargeBill $bill) => $this->summaries->for($bill))->all(),
            'title' => __('billing.bills_for_month', ['month' => $month->format('F Y')]),
        ]);
    }
}
