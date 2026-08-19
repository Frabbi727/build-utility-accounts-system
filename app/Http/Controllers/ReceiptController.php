<?php

namespace App\Http\Controllers;

use App\Enums\AccountCode;
use App\Models\Payment;
use App\Services\JournalService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The printable receipt for one payment.
 *
 * A plain controller rather than a Livewire screen: there is nothing interactive here,
 * and a printable page wants its own bare layout.
 */
class ReceiptController extends Controller
{
    public function __invoke(Payment $payment, JournalService $journal): View
    {
        Gate::authorize('view', $payment);

        $payment->load(['flat.building', 'flat.owner', 'allocations.bill']);

        return view('receipts.payment', [
            'payment' => $payment,
            'building' => $payment->flat->building,
            // Shown so the owner can see where the receipt leaves them.
            'outstanding' => $journal->balanceFor(
                $journal->account(AccountCode::ServiceChargeReceivable),
                $payment->flat_id,
            ),
            'advance' => $journal->balanceFor(
                $journal->account(AccountCode::AdvanceFromOwners),
                $payment->flat_id,
            ),
        ]);
    }
}
