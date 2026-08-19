<?php

namespace App\Livewire;

use App\Enums\AccountCode;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Services\JournalService;
use Illuminate\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(JournalService $journal): View
    {
        $user = auth()->user();

        if (! $user->isStaff()) {
            // An owner's dashboard is their own flat's statement.
            $flat = $user->owner?->flats()->first();

            return view('livewire.dashboard', [
                'isStaff' => false,
                'ownFlat' => $flat,
            ])->layout('components.layouts.app');
        }

        return view('livewire.dashboard', [
            'isStaff' => true,
            'ownFlat' => null,
            'totalReceivable' => $journal->balanceFor($journal->account(AccountCode::ServiceChargeReceivable)),
            'cash' => $journal->balanceFor($journal->account(AccountCode::CashInHand)),
            'bank' => $journal->balanceFor($journal->account(AccountCode::Bank)),
            'flatCount' => Flat::where('is_active', true)->count(),
            'unpaidBills' => ServiceChargeBill::whereIn('status', ['unpaid', 'partially_paid'])->count(),
        ])->layout('components.layouts.app');
    }
}
