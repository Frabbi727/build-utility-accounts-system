<?php

namespace App\Livewire\Masters;

use App\Enums\Role;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Owners are org-wide rather than per-building: one person can own flats in
 * several buildings, so this screen is deliberately not scoped by the switcher.
 */
class OwnerList extends Component
{
    use WithCrudModal, WithPagination;

    public string $search = '';

    public string $name = '';

    public ?string $phone = null;

    public ?string $email = null;

    public ?string $nationalId = null;

    public ?int $userId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'nationalId' => ['nullable', 'string', 'max:50'],
            'userId' => [
                'nullable', 'integer', 'exists:users,id',
                // A login belongs to at most one owner.
                Rule::unique('owners', 'user_id')->ignore($this->editingId),
            ],
        ];
    }

    /**
     * @param  Owner|null  $record
     */
    protected function fillForm(?Model $record): void
    {
        $this->name = $record->name ?? '';
        $this->phone = $record?->phone;
        $this->email = $record?->email;
        $this->nationalId = $record?->national_id;
        $this->userId = $record?->user_id;
    }

    protected function findRecord(int $id): Owner
    {
        return Owner::findOrFail($id);
    }

    protected function authorizeAction(string $ability, ?Model $record = null): void
    {
        $this->authorize($ability, $record ?? Owner::class);
    }

    protected function persist(): Owner
    {
        $owner = $this->editingId === null ? new Owner : $this->findRecord($this->editingId);

        $owner->fill([
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'national_id' => $this->nationalId,
            'user_id' => $this->userId,
        ])->save();

        session()->flash('status', __('masters.saved'));

        return $owner;
    }

    public function render(): View
    {
        $this->authorize('viewAny', Owner::class);

        return view('livewire.masters.owner-list', [
            'owners' => Owner::query()
                ->with('user')
                ->withCount('flats')
                ->when($this->search !== '', fn ($q) => $q->where('name', 'ilike', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(20),
            // Only owner-role logins are offered, so staff accounts stay unlinked.
            'linkableUsers' => User::role(Role::Owner->value)->orderBy('name')->get(),
        ])->layout('components.layouts.app');
    }
}
