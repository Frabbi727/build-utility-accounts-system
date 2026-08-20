<?php

namespace App\Livewire\Concerns;

use App\Exceptions\InvalidJournalEntryException;
use App\Exceptions\PeriodLockedException;
use App\Exceptions\UnbalancedEntryException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Turns a failed ledger post into a field error instead of a 500.
 *
 * `JournalService::post()` refuses a locked period, an unbalanced entry, or a structurally
 * invalid one, and the services above it reject things like paying more than a vendor bill
 * is owed. None of those were caught anywhere, so an operator backdating a receipt into a
 * closed month got a stack trace and lost everything they had typed.
 */
trait PostsToLedger
{
    /**
     * Run a posting action, attaching any domain failure to $field.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $post
     * @return TReturn|null null when the post was refused
     */
    protected function postGuarded(callable $post, string $field = 'amount'): mixed
    {
        try {
            return $post();
        } catch (PeriodLockedException $e) {
            $this->addError($field, $this->periodLockedMessage($e));
        } catch (UnbalancedEntryException|InvalidJournalEntryException $e) {
            $this->addError($field, $e->getMessage());
        } catch (QueryException) {
            $this->addError($field, __('confirmations.post_conflict'));
        }

        return null;
    }

    private function periodLockedMessage(PeriodLockedException $e): string
    {
        $period = $e->period;

        if ($period === null) {
            return __('confirmations.period_locked_generic');
        }

        return __('confirmations.period_locked', [
            'period' => Carbon::create($period->year, $period->month, 1)->translatedFormat('F Y'),
        ]);
    }
}
