<?php

namespace App\Livewire;

use App\Models\Account;
use App\Models\Flat;
use App\Services\JournalService;
use Illuminate\View\View;
use Livewire\Component;

class AccountList extends Component
{
    public function render(JournalService $journal): View
    {
        $this->authorize('viewAny', Flat::class);

        $accounts = Account::with('children')
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();

        $balances = Account::where('is_postable', true)
            ->get()
            ->mapWithKeys(fn (Account $a): array => [$a->id => $journal->balanceFor($a)]);

        return view('livewire.account-list', ['groups' => $accounts, 'balances' => $balances])
            ->layout('components.layouts.app');
    }
}
