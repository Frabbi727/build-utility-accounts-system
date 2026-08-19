<?php

namespace App\Livewire\Accounting;

use App\Enums\PeriodStatus;
use App\Models\AccountingPeriod;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Locking a period is how a closed month stays closed: JournalService::post()
 * already refuses to write into a locked period, so this screen just turns the key.
 */
class PeriodList extends Component
{
    public function lock(int $id): void
    {
        $period = AccountingPeriod::findOrFail($id);

        $this->authorize('update', $period);

        $period->update([
            'status' => PeriodStatus::Locked,
            'locked_at' => now(),
            'locked_by' => auth()->id(),
        ]);

        session()->flash('status', __('accounting.period_locked'));
    }

    public function unlock(int $id): void
    {
        $period = AccountingPeriod::findOrFail($id);

        $this->authorize('update', $period);

        $period->update([
            'status' => PeriodStatus::Open,
            'locked_at' => null,
            'locked_by' => null,
        ]);

        session()->flash('status', __('accounting.period_unlocked'));
    }

    public function render(): View
    {
        $this->authorize('viewAny', AccountingPeriod::class);

        return view('livewire.accounting.period-list', [
            'periods' => AccountingPeriod::withCount('journalEntries')
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->get(),
        ])->layout('components.layouts.app');
    }
}
