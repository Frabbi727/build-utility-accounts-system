<div class="space-y-6">
    <x-ui.page-header :title="__('utilities.utilities')" :description="__('utilities.no_utilities_description')">
        <x-slot:actions>
            @can('create', App\Models\Utility::class)
                <x-ui.button wire:click="create">{{ __('utilities.new_utility') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('livewire.partials.crud-feedback')

    @if ($utilities->isEmpty())
        <x-ui.empty-state :title="__('utilities.no_utilities')" :description="__('utilities.no_utilities_description')">
            <x-slot:actions>
                @can('create', App\Models\Utility::class)
                    <x-ui.button wire:click="create">{{ __('utilities.new_utility') }}</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-2">{{ __('utilities.utility') }}</th>
                <th class="px-4 py-2">{{ __('utilities.unit_label') }}</th>
                <th class="px-4 py-2">{{ __('utilities.income_account') }}</th>
                <th class="px-4 py-2 text-right">{{ __('utilities.meters') }}</th>
                <th class="px-4 py-2 text-right">{{ __('utilities.tariffs') }}</th>
                <th class="px-4 py-2">{{ __('masters.status') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
            </x-slot:head>

            @foreach ($utilities as $utility)
                <tr>
                    <td class="px-4 py-2 font-medium text-slate-900">{{ $utility->displayName() }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $utility->unit_label }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $utility->account->code }} — {{ $utility->account->displayName() }}</td>
                    <td class="px-4 py-2 text-right text-slate-600">{{ $utility->meters_count }}</td>
                    <td class="px-4 py-2 text-right text-slate-600">{{ $utility->tariffs_count }}</td>
                    <td class="px-4 py-2">
                        @if ($utility->is_active)
                            <x-ui.badge variant="success">{{ __('masters.active') }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral">{{ __('masters.inactive') }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right">
                        <div class="flex justify-end gap-3">
                            @can('update', $utility)
                                <button type="button" wire:click="toggleActive({{ $utility->id }})"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900">{{ $utility->is_active ? __('masters.deactivate') : __('masters.activate') }}</button>
                                <button type="button" wire:click="edit({{ $utility->id }})"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900">{{ __('masters.edit') }}</button>
                            @endcan
                            @can('delete', $utility)
                                <button type="button" wire:click="confirmDelete({{ $utility->id }})"
                                        class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('masters.delete') }}</button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('utilities.edit_utility') : __('utilities.new_utility')">
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <x-form.field :label="__('masters.name')" name="name" required>
                        <x-form.input wire:model="name" />
                    </x-form.field>

                    <x-form.field :label="__('masters.name_bn')" name="nameBn">
                        <x-form.input wire:model="nameBn" />
                    </x-form.field>

                    <x-form.field :label="__('utilities.unit_label')" name="unitLabel"
                                  :hint="__('utilities.unit_label_hint')" required>
                        <x-form.input wire:model="unitLabel" />
                    </x-form.field>

                    <x-form.field :label="__('utilities.income_account')" name="accountId"
                                  :hint="__('utilities.income_account_hint')" required>
                        <x-form.select wire:model="accountId" :placeholder="__('masters.select_account')">
                            @foreach ($incomeAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->displayName() }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    <x-form.field :label="__('masters.sort_order')" name="sortOrder" required>
                        <x-form.input type="number" min="0" max="999" wire:model="sortOrder" />
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
