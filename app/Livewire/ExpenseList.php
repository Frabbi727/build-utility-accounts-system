<?php

namespace App\Livewire;

use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Flat;
use App\Services\Expenses\RecordExpense;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ExpenseList extends Component
{
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

    public function save(RecordExpense $recorder): void
    {
        $this->authorize('manage', Flat::class);

        $this->validate([
            'accountId' => ['required', 'integer', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'string', 'in:cash,bank,bkash,nagad'],
            'spentOn' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $expense = $recorder->handle(
            Account::findOrFail($this->accountId),
            $this->amount,
            PaymentMethod::from($this->method),
            Carbon::parse($this->spentOn),
            $this->description,
        );

        session()->flash('status', __('expenses.expense_recorded', ['voucher' => $expense->voucher_no]));

        $this->reset(['amount', 'description']);
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('viewAny', Flat::class);

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
