<?php

namespace App\Livewire;

use App\Enums\AccountType;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Account;
use App\Policies\AccountPolicy;
use App\Services\JournalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * The chart of accounts, with balances derived from the ledger.
 *
 * The guard rails here matter more than the CRUD: the posting services resolve
 * accounts by the codes in App\Enums\AccountCode, so a reserved account's code and
 * type are locked, it cannot be deleted, and no account that already carries
 * journal lines can be deleted or made unpostable.
 */
class AccountList extends Component
{
    use WithCrudModal {
        save as private saveRecord;
    }

    public string $code = '';

    public string $name = '';

    public ?string $nameBn = null;

    public string $type = 'expense';

    public ?int $parentId = null;

    public bool $isPostable = true;

    public bool $isActive = true;

    /** True while editing an account the posting services depend on. */
    public bool $editingReserved = false;

    /**
     * @return array<string, mixed>
     */
    protected function formRules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('accounts', 'code')->ignore($this->editingId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'nameBn' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'parentId' => [
                'nullable', 'integer',
                Rule::exists('accounts', 'id')->whereNot('id', $this->editingId),
            ],
            'isPostable' => ['boolean'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @param  Account|null  $record
     */
    protected function fillForm(?Model $record): void
    {
        $this->code = $record->code ?? '';
        $this->name = $record->name ?? '';
        $this->nameBn = $record?->name_bn;
        $this->type = $record?->type->value ?? AccountType::Expense->value;
        $this->parentId = $record?->parent_id;
        $this->isPostable = $record->is_postable ?? true;
        $this->isActive = $record->is_active ?? true;
        $this->editingReserved = $record !== null && AccountPolicy::isReserved($record);
    }

    protected function findRecord(int $id): Account
    {
        return Account::findOrFail($id);
    }

    protected function authorizeAction(string $ability, ?Model $record = null): void
    {
        $this->authorize($ability, $record ?? Account::class);
    }

    protected function persist(): Account
    {
        $account = $this->editingId === null ? new Account : $this->findRecord($this->editingId);

        $reserved = $account->exists && AccountPolicy::isReserved($account);

        $account->fill([
            // A reserved account's code and type are what the services resolve by.
            'code' => $reserved ? $account->code : $this->code,
            'type' => $reserved ? $account->type : AccountType::from($this->type),
            'name' => $this->name,
            'name_bn' => $this->nameBn,
            'parent_id' => $this->parentId,
            'is_postable' => $this->isPostable,
            'is_active' => $reserved ? true : $this->isActive,
        ])->save();

        session()->flash('status', __('masters.saved'));

        return $account;
    }

    public function save(): void
    {
        $account = $this->editingId === null ? null : $this->findRecord($this->editingId);

        // Turning off postings on an account that already has them would strand
        // the history it carries.
        if (! $this->isPostable && $account !== null && $account->journalLines()->exists()) {
            $this->addError('isPostable', __('accounting.unpostable_has_postings'));

            return;
        }

        $this->saveRecord();
    }

    /**
     * @param  Account  $record
     */
    protected function deleteRecord(Model $record): bool
    {
        if ($record->journalLines()->exists()) {
            session()->flash('error', __('accounting.delete_has_postings'));

            return false;
        }

        $record->delete();

        return true;
    }

    public function render(JournalService $journal): View
    {
        $this->authorize('viewAny', Account::class);

        $accounts = Account::with('children')
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();

        $balances = Account::where('is_postable', true)
            ->get()
            ->mapWithKeys(fn (Account $a): array => [$a->id => $journal->balanceFor($a)]);

        return view('livewire.account-list', [
            'groups' => $accounts,
            'balances' => $balances,
            'types' => AccountType::cases(),
            'parents' => Account::orderBy('code')->get(),
        ])->layout('components.layouts.app');
    }
}
