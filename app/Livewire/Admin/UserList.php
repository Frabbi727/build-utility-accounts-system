<?php

namespace App\Livewire\Admin;

use App\Enums\Role;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Logins and the role each one carries. Administrators only — an accountant runs
 * the books but does not hand out access.
 */
class UserList extends Component
{
    use WithCrudModal, WithPagination;

    public string $search = '';

    public string $name = '';

    public string $email = '';

    /** Left blank on an edit, which keeps the existing password. */
    public string $password = '';

    public string $role = '';

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
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->editingId),
            ],
            'password' => [
                $this->editingId === null ? 'required' : 'nullable',
                'string', 'min:8',
            ],
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }

    /**
     * @param  User|null  $record
     */
    protected function fillForm(?Model $record): void
    {
        $this->name = $record->name ?? '';
        $this->email = $record->email ?? '';
        $this->password = '';
        $this->role = $record?->getRoleNames()->first() ?? Role::Committee->value;
    }

    protected function findRecord(int $id): User
    {
        return User::findOrFail($id);
    }

    protected function authorizeAction(string $ability, ?Model $record = null): void
    {
        $this->authorize($ability, $record ?? User::class);
    }

    protected function persist(): User
    {
        $user = $this->editingId === null ? new User : $this->findRecord($this->editingId);

        $user->name = $this->name;
        $user->email = $this->email;

        if ($this->password !== '') {
            $user->password = Hash::make($this->password);
        }

        $user->save();

        // One role per login keeps the access rules answerable at a glance.
        $user->syncRoles([$this->role]);

        session()->flash('status', __('masters.saved'));

        return $user;
    }

    public function render(): View
    {
        $this->authorize('viewAny', User::class);

        return view('livewire.admin.user-list', [
            'users' => User::query()
                ->with(['roles', 'owner'])
                ->when($this->search !== '', fn ($q) => $q
                    ->where(fn ($w) => $w->where('name', 'ilike', "%{$this->search}%")
                        ->orWhere('email', 'ilike', "%{$this->search}%")))
                ->orderBy('name')
                ->paginate(20),
            'roles' => Role::cases(),
        ])->layout('components.layouts.app');
    }
}
