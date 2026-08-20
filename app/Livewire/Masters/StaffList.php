<?php

namespace App\Livewire\Masters;

use App\Enums\AccountType;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Account;
use App\Models\Building;
use App\Models\Staff;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * People on the current building's payroll. The expense account is where their
 * salary lands when it is paid, so only postable expense accounts are offered.
 */
class StaffList extends Component
{
    use WithCrudModal, WithPagination;

    public string $name = '';

    public string $designation = '';

    public ?string $phone = null;

    public string $monthlySalary = '0';

    public ?int $expenseAccountId = null;

    public ?string $joinedOn = null;

    public bool $isActive = true;

    private function building(): ?Building
    {
        return app(CurrentBuilding::class)->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'monthlySalary' => ['required', 'numeric', 'min:0'],
            'expenseAccountId' => ['nullable', 'integer', 'exists:accounts,id'],
            'joinedOn' => ['nullable', 'date'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @param  Staff|null  $record
     */
    protected function fillForm(?Model $record): void
    {
        $this->name = $record->name ?? '';
        $this->designation = $record->designation ?? '';
        $this->phone = $record?->phone;
        $this->monthlySalary = $record === null ? '0' : (string) $record->monthly_salary;
        $this->expenseAccountId = $record?->expense_account_id;
        $this->joinedOn = $record?->joined_on?->toDateString();
        $this->isActive = $record->is_active ?? true;
    }

    protected function findRecord(int $id): Staff
    {
        return Staff::where('building_id', app(CurrentBuilding::class)->id())->findOrFail($id);
    }

    protected function authorizeAction(string $ability, ?Model $record = null): void
    {
        $this->authorize($ability, $record ?? Staff::class);
    }

    protected function persist(): Staff
    {
        $staff = $this->editingId === null ? new Staff : $this->findRecord($this->editingId);

        $staff->fill([
            'building_id' => app(CurrentBuilding::class)->getOrFail()->id,
            'name' => $this->name,
            'designation' => $this->designation,
            'phone' => $this->phone,
            'monthly_salary' => $this->monthlySalary,
            'expense_account_id' => $this->expenseAccountId,
            'joined_on' => $this->joinedOn,
            'is_active' => $this->isActive,
        ])->save();

        return $staff;
    }

    public function render(): View
    {
        $this->authorize('viewAny', Staff::class);

        $building = $this->building();

        return view('livewire.masters.staff-list', [
            'building' => $building,
            'staff' => Staff::query()
                ->when($building !== null, fn ($q) => $q->where('building_id', $building->id))
                ->when($building === null, fn ($q) => $q->whereRaw('1 = 0'))
                ->with('expenseAccount')
                ->orderBy('name')
                ->paginate(20),
            'expenseAccounts' => Account::where('is_postable', true)
                ->where('is_active', true)
                ->where('type', AccountType::Expense)
                ->orderBy('code')
                ->get(),
        ])->layout('components.layouts.app');
    }
}
