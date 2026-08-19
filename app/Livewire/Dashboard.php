<?php

namespace App\Livewire;

use App\Enums\AccountCode;
use App\Models\ChargeHead;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Illuminate\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(JournalService $journal, CurrentBuilding $current): View
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

        $building = $current->get();

        return view('livewire.dashboard', [
            'isStaff' => true,
            'ownFlat' => null,
            'building' => $building,
            // Money figures come off the whole ledger: journal lines carry a flat
            // dimension but no building one, so these are org-wide by construction.
            'totalReceivable' => $journal->balanceFor($journal->account(AccountCode::ServiceChargeReceivable)),
            'cash' => $journal->balanceFor($journal->account(AccountCode::CashInHand)),
            'bank' => $journal->balanceFor($journal->account(AccountCode::Bank)),
            // Counts are scoped to the building being worked in.
            'flatCount' => $building === null ? 0 : $building->activeFlats()->count(),
            'unpaidBills' => $building === null ? 0 : ServiceChargeBill::whereIn('status', ['unpaid', 'partially_paid'])
                ->whereIn('flat_id', $building->flats()->select('id'))
                ->count(),
            // Bill generation silently produces nothing without these, so say so.
            'needsChargeHeads' => $building !== null
                && ! ChargeHead::where('building_id', $building->id)->where('is_active', true)->exists(),
            'needsFlats' => $building !== null && $building->activeFlats()->count() === 0,
        ])->layout('components.layouts.app');
    }
}
