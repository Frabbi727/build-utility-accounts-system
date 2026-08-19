<div>
    <x-ui.page-header :title="__('masters.floors')" :description="__('masters.floors_help')">
        <x-slot:actions>
            @if ($building !== null)
                @can('create', App\Models\Floor::class)
                    <x-ui.button wire:click="create">{{ __('masters.new_floor') }}</x-ui.button>
                @endcan
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($building === null)
        <x-ui.empty-state :title="__('masters.no_building')" :description="__('masters.no_building_help')">
            <x-slot:actions>
                <x-ui.button :href="route('buildings.index')">{{ __('masters.new_building') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.empty-state>
    @elseif ($floors->isEmpty())
        <x-ui.empty-state :title="__('masters.no_floors')" :description="__('masters.no_floors_help')">
            <x-slot:actions>
                @can('create', App\Models\Floor::class)
                    <x-ui.button wire:click="create">{{ __('masters.new_floor') }}</x-ui.button>
                @endcan
                <x-ui.button variant="secondary" :href="route('flats.index')">{{ __('masters.flats') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-2 text-right">{{ __('masters.level') }}</th>
                <th class="px-4 py-2">{{ __('masters.name') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.flat_count') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
            </x-slot:head>

            @foreach ($floors as $floor)
                <tr>
                    <td class="px-4 py-2 text-right tabular-nums text-slate-600">{{ $floor->level }}</td>
                    <td class="px-4 py-2 font-medium text-slate-900">{{ $floor->name }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $floor->flats_count }}</td>
                    <td class="px-4 py-2 text-right">
                        <div class="flex justify-end gap-3">
                            @can('update', $floor)
                                <button type="button" wire:click="edit({{ $floor->id }})"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900">
                                    {{ __('masters.edit') }}
                                </button>
                            @endcan
                            @can('delete', $floor)
                                <button type="button" wire:click="delete({{ $floor->id }})"
                                        class="text-sm font-medium text-red-600 hover:text-red-800">
                                    {{ __('masters.delete') }}
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('masters.edit_floor') : __('masters.new_floor')">
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <x-form.field :label="__('masters.level')" name="level" :hint="__('masters.level_help')" required>
                        <x-form.input type="number" min="0" wire:model="level" />
                    </x-form.field>

                    <x-form.field :label="__('masters.name')" name="name" required>
                        <x-form.input wire:model="name" />
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
