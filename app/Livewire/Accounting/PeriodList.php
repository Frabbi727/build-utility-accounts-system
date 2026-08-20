<?php

namespace App\Livewire\Accounting;

use App\Enums\PeriodStatus;
use App\Livewire\Concerns\WithConfirmation;
use App\Livewire\Concerns\WithNotices;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Locking a period is how a closed month stays closed: JournalService::post()
 * already refuses to write into a locked period, so this screen just turns the key.
 *
 * Both directions are confirmed and audited. Unlocking in particular reopens a month
 * that was declared final, which is the sort of thing an auditor asks about later.
 */
class PeriodList extends Component
{
    use WithConfirmation;
    use WithNotices;

    /**
     * @return list<string>
     */
    protected function confirmableActions(): array
    {
        return ['lock', 'unlock'];
    }

    public function lock(int $id): void
    {
        $period = AccountingPeriod::findOrFail($id);

        $this->authorize('update', $period);

        $period->update([
            'status' => PeriodStatus::Locked,
            'locked_at' => now(),
            'locked_by' => auth()->id(),
        ]);

        $this->recordAudit($period, 'accounting_period.locked');
        $this->notify(__('accounting.period_locked'));
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

        $this->recordAudit($period, 'accounting_period.unlocked');
        $this->notify(__('accounting.period_unlocked'));
    }

    /**
     * The dialog's contents for whichever period is awaiting confirmation.
     *
     * @return array{title: string, message: string, period: string, entries: int}|null
     */
    public function pendingPeriod(): ?array
    {
        if ($this->pendingConfirm === null) {
            return null;
        }

        $period = AccountingPeriod::withCount('journalEntries')->find($this->pendingConfirm['id']);

        if ($period === null) {
            return null;
        }

        $isUnlock = $this->pendingConfirm['action'] === 'unlock';

        return [
            'title' => $isUnlock ? __('accounting.unlock_title') : __('accounting.lock_title'),
            'message' => $isUnlock ? __('accounting.unlock_warning') : __('accounting.lock_warning'),
            'period' => Carbon::create($period->year, $period->month, 1)->translatedFormat('F Y'),
            'entries' => $period->journal_entries_count,
        ];
    }

    private function recordAudit(AccountingPeriod $period, string $action): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $period->getMorphClass(),
            'subject_id' => $period->id,
            'payload' => ['year' => $period->year, 'month' => $period->month],
            'created_at' => now(),
        ]);
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
