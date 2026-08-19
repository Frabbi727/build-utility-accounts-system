<div>
    <x-ui.page-header :title="__('masters.buildings')" :description="__('masters.buildings_help')">
        <x-slot:actions>
            @can('create', App\Models\Building::class)
                <x-ui.button wire:click="create">{{ __('masters.new_building') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($buildings->isEmpty())
        <x-ui.empty-state :title="__('masters.no_buildings')" :description="__('masters.no_buildings_help')">
            <x-slot:actions>
                @can('create', App\Models\Building::class)
                    <x-ui.button wire:click="create">{{ __('masters.new_building') }}</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-2">{{ __('masters.name') }}</th>
                <th class="px-4 py-2">{{ __('masters.address') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.flat_count') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.charge_heads') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.due_day_of_month') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
            </x-slot:head>

            @foreach ($buildings as $building)
                <tr>
                    <td class="px-4 py-2 font-medium text-slate-900">
                        {{ $building->displayName() }}
                    </td>
                    <td class="px-4 py-2 text-slate-600">{{ $building->address }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $building->flats_count }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $building->charge_heads_count }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $building->due_day_of_month }}</td>
                    <td class="px-4 py-2 text-right">
                        @can('update', $building)
                            <button type="button" wire:click="edit({{ $building->id }})"
                                    class="text-sm font-medium text-slate-600 hover:text-slate-900">
                                {{ __('masters.edit') }}
                            </button>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('masters.edit_building') : __('masters.new_building')">
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <x-form.field :label="__('masters.name')" name="name" required>
                        <x-form.input wire:model="name" />
                    </x-form.field>

                    <x-form.field :label="__('masters.name_bn')" name="nameBn">
                        <x-form.input wire:model="nameBn" />
                    </x-form.field>

                    <x-form.field :label="__('masters.address')" name="address" class="sm:col-span-2">
                        <x-form.input wire:model="address" />
                    </x-form.field>

                    <x-form.field :label="__('masters.due_day_of_month')" name="dueDayOfMonth"
                                  :hint="__('masters.due_day_help')" required>
                        <x-form.input type="number" min="1" max="28" wire:model="dueDayOfMonth" />
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
