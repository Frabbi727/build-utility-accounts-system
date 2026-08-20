<div class="space-y-6">
    <x-ui.page-header :title="__('masters.vendors')" :description="__('masters.vendors_help')">
        <x-slot:actions>
            @can('create', App\Models\Vendor::class)
                <x-ui.button wire:click="create">{{ __('masters.new_vendor') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('livewire.partials.crud-feedback')

    @if ($vendors->isEmpty() && $search === '')
        <x-ui.empty-state :title="__('masters.no_vendors')" :description="__('masters.no_vendors_help')">
            <x-slot:actions>
                @can('create', App\Models\Vendor::class)
                    <x-ui.button wire:click="create">{{ __('masters.new_vendor') }}</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-ui.empty-state>
    @else
        <div class="flex justify-end">
            <input type="search" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('masters.search') }}" class="w-full sm:w-64">
        </div>

        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-2">{{ __('masters.name') }}</th>
                <th class="px-4 py-2">{{ __('masters.phone') }}</th>
                <th class="px-4 py-2">{{ __('masters.email') }}</th>
                <th class="px-4 py-2 text-right">{{ __('nav.vendor_bills') }}</th>
                <th class="px-4 py-2">{{ __('masters.status') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
            </x-slot:head>

            @foreach ($vendors as $vendor)
                <tr>
                    <td class="px-4 py-2 font-medium text-slate-900">{{ $vendor->name }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $vendor->phone ?? '—' }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $vendor->email ?? '—' }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $vendor->bills_count }}</td>
                    <td class="px-4 py-2">
                        <x-ui.badge :variant="$vendor->is_active ? 'success' : 'neutral'">
                            {{ $vendor->is_active ? __('masters.active') : __('masters.inactive') }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 py-2 text-right">
                        <div class="flex justify-end gap-3">
                            @can('update', $vendor)
                                <button type="button" wire:click="edit({{ $vendor->id }})"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900">{{ __('masters.edit') }}</button>
                            @endcan
                            @can('delete', $vendor)
                                <button type="button" wire:click="confirmDelete({{ $vendor->id }})"
                                        class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('masters.delete') }}</button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div>{{ $vendors->links() }}</div>
    @endif

    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('masters.edit_vendor') : __('masters.new_vendor')">
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <x-form.field :label="__('masters.name')" name="name" required>
                        <x-form.input wire:model="name" />
                    </x-form.field>

                    <x-form.field :label="__('masters.phone')" name="phone">
                        <x-form.input type="tel" wire:model="phone" />
                    </x-form.field>

                    <x-form.field :label="__('masters.email')" name="email">
                        <x-form.input type="email" wire:model="email" />
                    </x-form.field>

                    <x-form.field :label="__('masters.address')" name="address">
                        <x-form.input wire:model="address" />
                    </x-form.field>

                    <x-form.field name="isActive" class="sm:col-span-2">
                        <x-form.checkbox wire:model="isActive" :label="__('masters.active')" />
                    </x-form.field>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                    <x-ui.button variant="secondary" wire:click="cancel">{{ __('masters.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('masters.save') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
