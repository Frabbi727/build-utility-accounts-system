<?php

namespace App\Livewire\Masters;

use App\Enums\Role;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Building;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Tenants of the current building. Bills stay with the owner — a tenant record is
 * contact information, not a party to the ledger.
 */
class TenantList extends Component
{
    use WithCrudModal, WithPagination;

    public ?int $flatId = null;

    public string $name = '';

    public ?string $phone = null;

    public ?string $email = null;

    public ?string $leaseStartedOn = null;

    public ?string $leaseEndedOn = null;

    public bool $showUserModal = false;

    public ?int $targetTenantId = null;

    public string $newUserName = '';

    public string $newUserEmail = '';

    public string $newUserPassword = '';

    public ?string $generatedCredentials = null;

    public function openCreateUserModal(int $tenantId): void
    {
        $tenant = $this->findRecord($tenantId);
        $this->authorizeAction('update', $tenant);
        $this->targetTenantId = $tenant->id;
        $this->newUserName = $tenant->name;
        $this->newUserEmail = $tenant->email ?? '';
        $this->newUserPassword = Str::random(10);
        $this->generatedCredentials = null;
        $this->showUserModal = true;
    }

    public function closeUserModal(): void
    {
        $this->showUserModal = false;
        $this->targetTenantId = null;
        $this->generatedCredentials = null;
    }

    public function provisionUser(): void
    {
        $tenant = $this->findRecord((int) $this->targetTenantId);
        $this->authorizeAction('update', $tenant);
        $this->validate([
            'newUserName' => ['required', 'string', 'max:255'],
            'newUserEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'newUserPassword' => ['required', 'string', 'min:8'],
        ]);

        $tenant = $this->findRecord((int) $this->targetTenantId);

        $user = User::create([
            'name' => $this->newUserName,
            'email' => $this->newUserEmail,
            'password' => Hash::make($this->newUserPassword),
        ]);
        $user->assignRole(Role::Tenant->value);

        $tenant->update([
            'email' => $this->newUserEmail,
            'user_id' => $user->id,
        ]);

        $this->generatedCredentials = "Email: {$this->newUserEmail} | Password: {$this->newUserPassword}";
        $this->notify(__('masters.saved'));
    }

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
            'flatId' => [
                'required', 'integer',
                Rule::exists('flats', 'id')->where('building_id', app(CurrentBuilding::class)->id()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'leaseStartedOn' => ['nullable', 'date'],
            'leaseEndedOn' => ['nullable', 'date', 'after_or_equal:leaseStartedOn'],
        ];
    }

    /**
     * @param  Tenant|null  $record
     */
    protected function fillForm(?Model $record): void
    {
        $this->flatId = $record?->flat_id;
        $this->name = $record->name ?? '';
        $this->phone = $record?->phone;
        $this->email = $record?->email;
        $this->leaseStartedOn = $record?->lease_started_on?->toDateString();
        $this->leaseEndedOn = $record?->lease_ended_on?->toDateString();
    }

    protected function findRecord(int $id): Tenant
    {
        return Tenant::whereHas('flat', fn ($q) => $q->where('building_id', app(CurrentBuilding::class)->id()))
            ->findOrFail($id);
    }

    protected function authorizeAction(string $ability, ?Model $record = null): void
    {
        $this->authorize($ability, $record ?? Tenant::class);
    }

    protected function persist(): Tenant
    {
        $tenant = $this->editingId === null ? new Tenant : $this->findRecord($this->editingId);

        $tenant->fill([
            'flat_id' => $this->flatId,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'lease_started_on' => $this->leaseStartedOn,
            'lease_ended_on' => $this->leaseEndedOn,
        ])->save();

        return $tenant;
    }

    public function render(): View
    {
        $this->authorize('viewAny', Tenant::class);

        $building = $this->building();

        return view('livewire.masters.tenant-list', [
            'building' => $building,
            'tenants' => Tenant::query()
                ->when($building !== null, fn ($q) => $q->whereHas('flat', fn ($f) => $f->where('building_id', $building->id)))
                ->when($building === null, fn ($q) => $q->whereRaw('1 = 0'))
                ->with('flat')
                ->orderBy('name')
                ->paginate(20),
            'flats' => $building === null ? collect() : $building->flats()->orderBy('number')->get(),
        ])->layout('components.layouts.app');
    }
}
