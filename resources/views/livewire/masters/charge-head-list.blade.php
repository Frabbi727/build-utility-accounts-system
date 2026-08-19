<div class="space-y-8">
    <x-ui.page-header :title="__('masters.charge_heads')" :description="__('masters.charge_heads_help')">
        <x-slot:actions>
            @if ($building !== null)
                @can('create', App\Models\ChargeHead::class)
                    <x-ui.button wire:click="create">{{ __('masters.new_charge_head') }}</x-ui.button>
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
    @elseif ($heads->isEmpty())
        <x-ui.empty-state :title="__('masters.no_charge_heads')" :description="__('masters.no_charge_heads_help')">
            <x-slot:actions>
                @can('create', App\Models\ChargeHead::class)
                    <x-ui.button wire:click="create">{{ __('masters.new_charge_head') }}</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-2">{{ __('masters.name') }}</th>
                <th class="px-4 py-2">{{ __('masters.basis') }}</th>
                <th class="px-4 py-2">{{ __('masters.applies_to') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.amount') }}</th>
                <th class="px-4 py-2">{{ __('masters.income_account') }}</th>
                <th class="px-4 py-2">{{ __('masters.status') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
            </x-slot:head>

            @foreach ($heads as $head)
                <tr>
                    <td class="px-4 py-2 font-medium text-slate-900">{{ $head->displayName() }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $head->basis->label() }}</td>
                    <td class="px-4 py-2 text-slate-600">
                        @if ($head->unitTypes->isEmpty())
                            <span class="text-slate-400">{{ __('masters.unit_type_any') }}</span>
                        @else
                            {{ $head->unitTypes->map(fn ($type) => $type->displayName())->join(', ') }}
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right"><x-money :amount="$head->amount" /></td>
                    <td class="px-4 py-2 text-slate-600">
                        {{ $head->account->code }} —
                        {{ app()->getLocale() === 'bn' ? ($head->account->name_bn ?? $head->account->name) : $head->account->name }}
                    </td>
                    <td class="px-4 py-2">
                        <x-ui.badge :variant="$head->is_active ? 'success' : 'neutral'">
                            {{ $head->is_active ? __('masters.active') : __('masters.inactive') }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 py-2 text-right">
                        <div class="flex justify-end gap-3">
                            @can('update', $head)
                                <button type="button" wire:click="edit({{ $head->id }})"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900">
                                    {{ __('masters.edit') }}
                                </button>
                                <button type="button" wire:click="toggleActive({{ $head->id }})"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900">
                                    {{ $head->is_active ? __('masters.deactivate') : __('masters.activate') }}
                                </button>
                            @endcan
                            @can('delete', $head)
                                <button type="button" wire:click="delete({{ $head->id }})"
                                        class="text-sm font-medium text-red-600 hover:text-red-800">
                                    {{ __('masters.delete') }}
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        @if ($preview->isNotEmpty())
            <div>
                <h2 class="text-sm font-semibold text-slate-900">{{ __('masters.preview') }}</h2>
                <p class="mb-3 text-sm text-slate-500">{{ __('masters.preview_help') }}</p>

                <x-ui.table>
                    <x-slot:head>
                        <th class="px-4 py-2">{{ __('masters.flat') }}</th>
                        @foreach ($heads->where('is_active', true) as $head)
                            <th class="px-4 py-2 text-right">{{ $head->displayName() }}</th>
                        @endforeach
                        <th class="px-4 py-2 text-right">{{ __('masters.preview_total') }}</th>
                    </x-slot:head>

                    @foreach ($preview as $row)
                        <tr>
                            <td class="px-4 py-2 font-medium text-slate-900">{{ $row['flat']->number }}</td>
                            @foreach ($heads->where('is_active', true) as $head)
                                <td class="px-4 py-2 text-right text-slate-600">
                                    @if (isset($row['lines'][$head->id]))
                                        <x-money :amount="$row['lines'][$head->id]" />
                                    @else
                                        <span class="text-xs text-slate-400">{{ __('masters.exempt') }}</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-4 py-2 text-right font-semibold text-slate-900">
                                <x-money :amount="$row['total']" />
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>
        @endif
    @endif

    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('masters.edit_charge_head') : __('masters.new_charge_head')">
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <x-form.field :label="__('masters.name')" name="name" required>
                        <x-form.input wire:model="name" />
                    </x-form.field>

                    <x-form.field :label="__('masters.name_bn')" name="nameBn">
                        <x-form.input wire:model="nameBn" />
                    </x-form.field>

                    <x-form.field :label="__('masters.basis')" name="basis" required>
                        <x-form.select wire:model.live="basis">
                            @foreach ($bases as $case)
                                <option value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    <x-form.field :label="__('masters.amount')" name="amount"
                                  :hint="App\Enums\ChargeBasis::from($basis)->amountLabel()" required>
                        <x-form.input type="number" step="0.01" min="0" wire:model="amount" />
                    </x-form.field>

                    <x-form.field :label="__('masters.income_account')" name="accountId" class="sm:col-span-2" required>
                        <x-form.select wire:model="accountId" :placeholder="__('masters.income_account')">
                            @foreach ($incomeAccounts as $account)
                                <option value="{{ $account->id }}">
                                    {{ $account->code }} —
                                    {{ app()->getLocale() === 'bn' ? ($account->name_bn ?? $account->name) : $account->name }}
                                </option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    <x-form.field :label="__('masters.sort_order')" name="sortOrder">
                        <x-form.input type="number" min="0" wire:model="sortOrder" />
                    </x-form.field>

                    <x-form.field name="isActive" class="flex items-end">
                        <x-form.checkbox wire:model="isActive" :label="__('masters.active')" />
                    </x-form.field>

                    @if ($unitTypes->isNotEmpty())
                        <x-form.field :label="__('masters.applies_to')" name="unitTypeIds"
                                      :hint="__('masters.applies_to_help')" class="sm:col-span-2">
                            <div class="flex flex-wrap gap-4">
                                @foreach ($unitTypes as $unitType)
                                    <x-form.checkbox wire:model="unitTypeIds" value="{{ $unitType->id }}"
                                                     :label="$unitType->displayName()" />
                                @endforeach
                            </div>
                        </x-form.field>
                    @endif
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                    <x-ui.button variant="secondary" wire:click="cancel">{{ __('masters.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('masters.save') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
