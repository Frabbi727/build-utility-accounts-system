<?php

namespace App\Livewire\Accounting;

use App\Exceptions\InvalidJournalEntryException;
use App\Models\Account;
use App\Models\Flat;
use App\Services\Accounting\PostOpeningBalances;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Brings a building's existing balances into the ledger, once.
 */
class OpeningBalances extends Component
{
    public string $asOf = '';

    public string $cash = '0';

    public string $bank = '0';

    public string $payables = '0';

    public string $deposits = '0';

    /** @var array<int, string> flat id => amount already owed */
    public array $flatDues = [];

    public function mount(): void
    {
        $this->authorize('create', Account::class);

        $this->asOf = now()->startOfMonth()->toDateString();

        foreach ($this->flats() as $flat) {
            $this->flatDues[$flat->id] = '';
        }
    }

    /**
     * @return Collection<int, Flat>
     */
    private function flats()
    {
        $building = app(CurrentBuilding::class)->get();

        return $building === null
            ? Flat::whereRaw('1 = 0')->get()
            : $building->activeFlats()->orderBy('number')->get();
    }

    public function post(PostOpeningBalances $poster): void
    {
        $this->authorize('create', Account::class);

        $this->validate([
            'asOf' => ['required', 'date'],
            'cash' => ['required', 'numeric', 'min:0'],
            'bank' => ['required', 'numeric', 'min:0'],
            'payables' => ['required', 'numeric', 'min:0'],
            'deposits' => ['required', 'numeric', 'min:0'],
            'flatDues.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $dues = collect($this->flatDues)
            ->map(fn (?string $amount): string => trim((string) $amount) === '' ? '0' : $amount)
            ->filter(fn (string $amount): bool => bccomp($amount, '0', 2) > 0)
            ->all();

        try {
            $entry = $poster->handle(
                Carbon::parse($this->asOf),
                $this->cash,
                $this->bank,
                $dues,
                $this->payables,
                $this->deposits,
            );
        } catch (InvalidJournalEntryException) {
            $this->addError('cash', __('accounting.nothing_to_post'));

            return;
        }

        session()->flash('status', __('accounting.opening_posted', ['ref' => $entry->ref_no]));
    }

    /**
     * The running totals shown under the form, so the operator sees the balancing
     * General Fund figure before posting.
     *
     * @return array{debits: numeric-string, credits: numeric-string, fund: numeric-string}
     */
    private function totals(): array
    {
        $debits = bcadd($this->numeric($this->cash), $this->numeric($this->bank), 2);

        foreach ($this->flatDues as $amount) {
            $debits = bcadd($debits, $this->numeric($amount), 2);
        }

        $credits = bcadd($this->numeric($this->payables), $this->numeric($this->deposits), 2);

        return ['debits' => $debits, 'credits' => $credits, 'fund' => bcsub($debits, $credits, 2)];
    }

    private function numeric(?string $value): string
    {
        $value = trim((string) $value);

        return is_numeric($value) ? bcadd($value, '0', 2) : '0.00';
    }

    public function render(PostOpeningBalances $poster): View
    {
        $this->authorize('viewAny', Account::class);

        return view('livewire.accounting.opening-balances', [
            'flats' => $this->flats(),
            'alreadyPosted' => $poster->alreadyPosted(),
            'totals' => $this->totals(),
        ])->layout('components.layouts.app');
    }
}
