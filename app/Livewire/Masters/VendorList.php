<?php

namespace App\Livewire\Masters;

use App\Livewire\Concerns\WithCrudModal;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class VendorList extends Component
{
    use WithCrudModal, WithPagination;

    public string $search = '';

    public string $name = '';

    public ?string $phone = null;

    public ?string $email = null;

    public ?string $address = null;

    public bool $isActive = true;

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
            'address' => ['nullable', 'string', 'max:255'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @param  Vendor|null  $record
     */
    protected function fillForm(?Model $record): void
    {
        $this->name = $record->name ?? '';
        $this->phone = $record?->phone;
        $this->email = $record?->email;
        $this->address = $record?->address;
        $this->isActive = $record->is_active ?? true;
    }

    protected function findRecord(int $id): Vendor
    {
        return Vendor::findOrFail($id);
    }

    protected function authorizeAction(string $ability, ?Model $record = null): void
    {
        $this->authorize($ability, $record ?? Vendor::class);
    }

    protected function persist(): Vendor
    {
        $vendor = $this->editingId === null ? new Vendor : $this->findRecord($this->editingId);

        $vendor->fill([
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'is_active' => $this->isActive,
        ])->save();

        session()->flash('status', __('masters.saved'));

        return $vendor;
    }

    public function render(): View
    {
        $this->authorize('viewAny', Vendor::class);

        return view('livewire.masters.vendor-list', [
            'vendors' => Vendor::query()
                ->withCount('bills')
                ->when($this->search !== '', fn ($q) => $q->where('name', 'ilike', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(20),
        ])->layout('components.layouts.app');
    }
}
