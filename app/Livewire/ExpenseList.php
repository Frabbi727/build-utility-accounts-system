<?php

namespace App\Livewire;

use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Livewire\Concerns\PostsToLedger;
use App\Livewire\Concerns\WithConfirmation;
use App\Livewire\Concerns\WithNotices;
use App\Models\Account;
use App\Models\Expense;
use App\Services\Expenses\RecordExpense;
use App\Services\JournalService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ExpenseList extends Component
{
    use PostsToLedger;
    use WithConfirmation;
    use WithNotices;
    use WithPagination;

    public ?int $accountId = null;

    public string $amount = '';

    public string $method = 'cash';

    public string $spentOn = '';

    public string $description = '';

    public function mount(): void
    {
        $this->spentOn = now()->toDateString();
    }

    /**
     * @return list<string>
     */
    protected function confirmableActions(): array
    {
        return ['save'];
    }

    public function askSave(): void
    {
        $this->authorize('create', Expense::class);

        $this->validate($this->rules());
        $this->clearNotice();

        $this->askConfirm('save');
    }

    public function save(): void
    {
        $this->authorize('create', Expense::class);

        $this->validate($this->rules());

        $expense = $this->postGuarded(
            fn () => app(RecordExpense::class)->handle(
                Account::findOrFail($this->accountId),
                $this->amount,
                PaymentMethod::from($this->method),
                Carbon::parse($this->spentOn),
                $this->description,
            ),
            'spentOn',
        );

        if ($expense === null) {
            return;
        }

        $this->notify(__('expenses.expense_recorded', ['voucher' => $expense->voucher_no]));

        $this->reset(['amount', 'description']);
        $this->resetPage();
    }

    /**
     * What the money is about to do, for the confirmation dialog.
     *
     * The balance is shown because nothing stops a cash payment from driving Cash in Hand
     * negative — the operator is the only check on that, so give them the number.
     *
     * @return array{account: Account, balanceAfter: string, overdrawn: bool}|null
     */
    public function expensePreview(): ?array
    {
        if ($this->accountId === null || ! is_numeric($this->amount)) {
            return null;
        }

        $account = Account::find($this->accountId);

        if ($account === null) {
            return null;
        }

        $journal = app(JournalService::class);
        $source = $journal->account(PaymentMethod::from($this->method)->accountCode());
        $balanceAfter = bcsub($journal->balanceFor($source), bcadd($this->amount, '0', 2), 2);

        return [
            'account' => $account,
            'balanceAfter' => $balanceAfter,
            'overdrawn' => bccomp($balanceAfter, '0', 2) < 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'accountId' => ['required', 'integer', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'string', 'in:cash,bank,bkash,nagad'],
            'spentOn' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }

    public function render(): View
    {
        $this->authorize('viewAny', Expense::class);

        return view('livewire.expense-list', [
            'expenses' => Expense::with(['account', 'vendor', 'staff'])
                ->orderByDesc('spent_on')
                ->orderByDesc('id')
                ->paginate(20),
            'expenseAccounts' => Account::where('is_postable', true)
                ->where('type', AccountType::Expense)
                ->orderBy('code')
                ->get(),
            'methods' => PaymentMethod::cases(),
        ])->layout('components.layouts.app');
    }
}
