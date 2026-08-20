<div class="space-y-6">
    <x-ui.page-header :title="__('masters.unit_types')" :description="__('masters.unit_types_help')">
        <x-slot:actions>
            @can('create', App\Models\UnitType::class)
                <x-ui.button wire:click="create">{{ __('masters.new_unit_type') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('livewire.partials.crud-feedback')

    @if ($unitTypes->isEmpty())
        <x-ui.empty-state :title="__('masters.no_unit_types')" :description="__('masters.unit_types_help')">
            <x-slot:actions>
                @can('create', App\Models\UnitType::class)
                    <x-ui.button wire:click="create">{{ __('masters.new_unit_type') }}</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-2">{{ __('masters.name') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.flats') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.charge_heads') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
            </x-slot:head>

            @foreach ($unitTypes as $unitType)
                <tr>
                    <td class="px-4 py-2 font-medium text-slate-900">{{ $unitType->displayName() }}</td>
                    <td class="px-4 py-2 text-right text-slate-600">{{ $unitType->flats_count }}</td>
                    <td class="px-4 py-2 text-right text-slate-600">{{ $unitType->charge_heads_count }}</td>
                    <td class="px-4 py-2 text-right">
                        <div class="flex justify-end gap-3">
                            @can('update', $unitType)
                                <button type="button" wire:click="edit({{ $unitType->id }})"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900">{{ __('masters.edit') }}</button>
                            @endcan
                            @can('delete', $unitType)
                                <button type="button" wire:click="confirmDelete({{ $unitType->id }})"
                                        class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('masters.delete') }}</button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('masters.edit_unit_type') : __('masters.new_unit_type')">
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <x-form.field :label="__('masters.name')" name="name" required>
                        <x-form.input wire:model="name" />
                    </x-form.field>

                    <x-form.field :label="__('masters.name_bn')" name="nameBn">
                        <x-form.input wire:model="nameBn" />
                    </x-form.field>

                    <x-form.field :label="__('masters.sort_order')" name="sortOrder" required>
                        <x-form.input type="number" min="0" max="999" wire:model="sortOrder" />
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
