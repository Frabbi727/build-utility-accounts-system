<div class="space-y-6">
    <x-ui.page-header :title="__('masters.ad_hoc_charges')" :description="__('masters.ad_hoc_charges_help')">
        <x-slot:actions>
            @can('create', App\Models\AdHocCharge::class)
                <x-ui.button wire:click="create">{{ __('masters.new_ad_hoc_charge') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($charges->isEmpty())
        <x-ui.empty-state :title="__('masters.no_ad_hoc_charges')" :description="__('masters.no_ad_hoc_charges_help')">
            <x-slot:actions>
                @can('create', App\Models\AdHocCharge::class)
                    <x-ui.button wire:click="create">{{ __('masters.new_ad_hoc_charge') }}</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-2">{{ __('masters.flat') }}</th>
                <th class="px-4 py-2">{{ __('masters.description') }}</th>
                <th class="px-4 py-2">{{ __('masters.account') }}</th>
                <th class="px-4 py-2">{{ __('masters.effective_month') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.amount') }}</th>
                <th class="px-4 py-2">{{ __('masters.status') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
            </x-slot:head>

            @foreach ($charges as $charge)
                <tr>
                    <td class="px-4 py-2 font-medium text-slate-900">{{ $charge->flat->number }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $charge->description }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $charge->account->displayName() }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $charge->effective_month->format('M Y') }}</td>
                    <td class="px-4 py-2 text-right"><x-money :amount="$charge->amount" /></td>
                    <td class="px-4 py-2">
                        @if ($charge->isApplied())
                            <x-ui.badge variant="success">{{ $charge->bill?->bill_no ?? __('masters.ad_hoc_billed') }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="warning">{{ __('masters.ad_hoc_pending') }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right">
                        <div class="flex justify-end gap-3">
                            @can('update', $charge)
                                <button type="button" wire:click="edit({{ $charge->id }})"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900">{{ __('masters.edit') }}</button>
                            @endcan
                            @can('delete', $charge)
                                <button type="button" wire:click="delete({{ $charge->id }})"
                                        class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('masters.delete') }}</button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div>{{ $charges->links() }}</div>
    @endif

    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('masters.edit_ad_hoc_charge') : __('masters.new_ad_hoc_charge')">
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <x-form.field :label="__('masters.flat')" name="flatId" required>
                        <x-form.select wire:model="flatId" :placeholder="__('masters.select_flat')">
                            @foreach ($flats as $flat)
                                <option value="{{ $flat->id }}">{{ $flat->number }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    <x-form.field :label="__('masters.account')" name="accountId" required>
                        <x-form.select wire:model="accountId" :placeholder="__('masters.select_account')">
                            @foreach ($incomeAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->displayName() }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    <x-form.field :label="__('masters.description')" name="description" class="sm:col-span-2" required>
                        <x-form.input wire:model="description" />
                    </x-form.field>

                    <x-form.field :label="__('masters.amount')" name="amount" required>
                        <x-form.input type="number" step="0.01" min="0" wire:model="amount" />
                    </x-form.field>

                    <x-form.field :label="__('masters.effective_month')" name="effectiveMonth"
                                  :hint="__('masters.effective_month_help')" required>
                        <x-form.input type="month" wire:model="effectiveMonth" />
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
