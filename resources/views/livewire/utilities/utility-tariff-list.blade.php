<div class="space-y-6">
    <x-ui.page-header :title="__('utilities.tariffs')" :description="__('utilities.no_tariffs_description')">
        <x-slot:actions>
            @can('create', App\Models\UtilityTariff::class)
                <x-ui.button wire:click="create">{{ __('utilities.new_tariff') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('livewire.partials.crud-feedback')

    @if ($utilities->isNotEmpty())
        <div class="max-w-xs">
            <x-form.field :label="__('utilities.utility')" name="filterUtilityId">
                <x-form.select wire:model.live="filterUtilityId" :placeholder="__('masters.all')">
                    @foreach ($utilities as $utility)
                        <option value="{{ $utility->id }}">{{ $utility->displayName() }}</option>
                    @endforeach
                </x-form.select>
            </x-form.field>
        </div>
    @endif

    @if ($tariffs->isEmpty())
        <x-ui.empty-state :title="__('utilities.no_tariffs')" :description="__('utilities.no_tariffs_description')">
            <x-slot:actions>
                @can('create', App\Models\UtilityTariff::class)
                    <x-ui.button wire:click="create">{{ __('utilities.new_tariff') }}</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-2">{{ __('utilities.utility') }}</th>
                <th class="px-4 py-2">{{ __('utilities.effective_from') }}</th>
                <th class="px-4 py-2">{{ __('utilities.effective_to') }}</th>
                <th class="px-4 py-2">{{ __('utilities.slabs') }}</th>
                <th class="px-4 py-2 text-right">{{ __('utilities.fixed_charge') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
            </x-slot:head>

            @foreach ($tariffs as $tariff)
                <tr>
                    <td class="px-4 py-2 font-medium text-slate-900">{{ $tariff->utility->displayName() }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $tariff->effective_from->format('d M Y') }}</td>
                    <td class="px-4 py-2 text-slate-600">
                        @if ($tariff->effective_to)
                            {{ $tariff->effective_to->format('d M Y') }}
                        @else
                            <x-ui.badge variant="success">{{ __('utilities.still_current') }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-sm text-slate-600">
                        @foreach ($tariff->slabs as $slab)
                            <div>
                                {{ $slab->from_unit }}–{{ $slab->to_unit ?? __('utilities.slab_open_ended') }}
                                @ {{ $slab->rate_per_unit }}
                            </div>
                        @endforeach
                    </td>
                    <td class="px-4 py-2 text-right"><x-money :amount="$tariff->fixed_charge" blank-zero /></td>
                    <td class="px-4 py-2 text-right">
                        @if ($tariff->readings_count > 0)
                            <span class="text-xs text-slate-500">{{ __('utilities.tariff_in_use') }}</span>
                        @else
                            <div class="flex justify-end gap-3">
                                @can('update', $tariff)
                                    <button type="button" wire:click="edit({{ $tariff->id }})"
                                            class="text-sm font-medium text-slate-600 hover:text-slate-900">{{ __('masters.edit') }}</button>
                                @endcan
                                @can('delete', $tariff)
                                    <button type="button" wire:click="confirmDelete({{ $tariff->id }})"
                                            class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('masters.delete') }}</button>
                                @endcan
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('utilities.edit_tariff') : __('utilities.new_tariff')">
            <form wire:submit="save">
                <div class="space-y-5 px-6 py-5">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-form.field :label="__('utilities.utility')" name="utilityId" required>
                            <x-form.select wire:model="utilityId" :placeholder="__('masters.select')">
                                @foreach ($utilities as $utility)
                                    <option value="{{ $utility->id }}">{{ $utility->displayName() }}</option>
                                @endforeach
                            </x-form.select>
                        </x-form.field>

                        <x-form.field :label="__('utilities.effective_from')" name="effectiveFrom" required>
                            <x-form.input type="date" wire:model="effectiveFrom" />
                        </x-form.field>

                        <x-form.field :label="__('utilities.fixed_charge')" name="fixedCharge"
                                      :hint="__('utilities.fixed_charge_hint')" required>
                            <x-form.input type="number" step="0.01" min="0" wire:model="fixedCharge" />
                        </x-form.field>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-900">{{ __('utilities.slabs') }}</h3>
                            <x-ui.button variant="secondary" wire:click="addSlab">{{ __('utilities.add_slab') }}</x-ui.button>
                        </div>

                        <p class="text-xs text-slate-500">{{ __('utilities.slab_hint') }}</p>

                        @error('slabs')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <div class="space-y-2">
                            @foreach ($slabs as $index => $slab)
                                <div class="grid items-end gap-3 sm:grid-cols-[1fr_1fr_1fr_auto]">
                                    <x-form.field :label="__('utilities.slab_from')" name="slabs.{{ $index }}.from_unit">
                                        <x-form.input type="number" step="0.001" min="0"
                                                      wire:model="slabs.{{ $index }}.from_unit" />
                                    </x-form.field>

                                    <x-form.field :label="__('utilities.slab_to')" name="slabs.{{ $index }}.to_unit">
                                        <x-form.input type="number" step="0.001" min="0"
                                                      :placeholder="__('utilities.slab_open_ended')"
                                                      wire:model="slabs.{{ $index }}.to_unit" />
                                    </x-form.field>

                                    <x-form.field :label="__('utilities.slab_rate')" name="slabs.{{ $index }}.rate_per_unit">
                                        <x-form.input type="number" step="0.0001" min="0"
                                                      wire:model="slabs.{{ $index }}.rate_per_unit" />
                                    </x-form.field>

                                    <button type="button" wire:click="removeSlab({{ $index }})"
                                            @disabled(count($slabs) === 1)
                                            class="pb-2 text-sm font-medium text-red-600 hover:text-red-800 disabled:cursor-not-allowed disabled:text-slate-300">
                                        {{ __('utilities.remove_slab') }}
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                    <x-ui.button variant="secondary" wire:click="cancel">{{ __('masters.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('masters.save') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
