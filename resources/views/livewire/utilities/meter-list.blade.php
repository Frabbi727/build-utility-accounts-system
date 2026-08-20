<div class="space-y-6">
    <x-ui.page-header :title="__('utilities.meters')" :description="__('utilities.no_meters_description')">
        <x-slot:actions>
            @can('create', App\Models\Meter::class)
                <x-ui.button wire:click="create">{{ __('utilities.new_meter') }}</x-ui.button>
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

    @if ($meters->isEmpty())
        <x-ui.empty-state :title="__('utilities.no_meters')" :description="__('utilities.no_meters_description')">
            <x-slot:actions>
                @can('create', App\Models\Meter::class)
                    <x-ui.button wire:click="create">{{ __('utilities.new_meter') }}</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-2">{{ __('utilities.meter_no') }}</th>
                <th class="px-4 py-2">{{ __('utilities.utility') }}</th>
                <th class="px-4 py-2">{{ __('masters.flat') }}</th>
                <th class="px-4 py-2 text-right">{{ __('utilities.digits') }}</th>
                <th class="px-4 py-2 text-right">{{ __('utilities.initial_reading') }}</th>
                <th class="px-4 py-2">{{ __('masters.status') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
            </x-slot:head>

            @foreach ($meters as $meter)
                <tr>
                    <td class="px-4 py-2 font-medium text-slate-900">{{ $meter->meter_no }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $meter->utility->displayName() }}</td>
                    <td class="px-4 py-2 text-slate-600">
                        @if ($meter->isCommon())
                            <x-ui.badge variant="warning">{{ __('utilities.common_meter') }}</x-ui.badge>
                        @else
                            {{ $meter->flat->number }}
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right text-slate-600">{{ $meter->digits }}</td>
                    <td class="px-4 py-2 text-right text-slate-600">{{ $meter->initial_reading }}</td>
                    <td class="px-4 py-2">
                        @if ($meter->is_active)
                            <x-ui.badge variant="success">{{ __('masters.active') }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral">{{ __('masters.inactive') }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right">
                        <div class="flex justify-end gap-3">
                            @can('update', $meter)
                                <button type="button" wire:click="edit({{ $meter->id }})"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900">{{ __('masters.edit') }}</button>
                            @endcan
                            @can('delete', $meter)
                                <button type="button" wire:click="confirmDelete({{ $meter->id }})"
                                        class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('masters.delete') }}</button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('utilities.edit_meter') : __('utilities.new_meter')">
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <x-form.field :label="__('utilities.utility')" name="utilityId" required>
                        <x-form.select wire:model="utilityId" :placeholder="__('masters.select')">
                            @foreach ($utilities as $utility)
                                <option value="{{ $utility->id }}">{{ $utility->displayName() }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    <x-form.field :label="__('masters.flat')" name="flatId" :hint="__('utilities.common_meter_hint')">
                        <x-form.select wire:model="flatId" :placeholder="__('utilities.common_meter')">
                            @foreach ($flats as $flat)
                                <option value="{{ $flat->id }}">{{ $flat->number }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    <x-form.field :label="__('utilities.meter_no')" name="meterNo" required>
                        <x-form.input wire:model="meterNo" />
                    </x-form.field>

                    <x-form.field :label="__('utilities.digits')" name="digits" :hint="__('utilities.digits_hint')" required>
                        <x-form.input type="number" min="1" max="12" wire:model="digits" />
                    </x-form.field>

                    <x-form.field :label="__('utilities.initial_reading')" name="initialReading"
                                  :hint="__('utilities.initial_reading_hint')" required>
                        <x-form.input type="number" step="0.001" min="0" wire:model="initialReading" />
                    </x-form.field>

                    <x-form.field :label="__('utilities.installed_on')" name="installedOn">
                        <x-form.input type="date" wire:model="installedOn" />
                    </x-form.field>

                    <x-form.field name="isActive">
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
