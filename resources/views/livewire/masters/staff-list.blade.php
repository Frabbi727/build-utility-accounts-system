<div class="space-y-6">
    <x-ui.page-header :title="__('masters.staff')" :description="__('masters.staff_help')">
        <x-slot:actions>
            @if ($building !== null)
                @can('create', App\Models\Staff::class)
                    <x-ui.button wire:click="create">{{ __('masters.new_staff') }}</x-ui.button>
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
    @elseif ($staff->isEmpty())
        <x-ui.empty-state :title="__('masters.no_staff')" :description="__('masters.no_staff_help')">
            <x-slot:actions>
                @can('create', App\Models\Staff::class)
                    <x-ui.button wire:click="create">{{ __('masters.new_staff') }}</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-2">{{ __('masters.name') }}</th>
                <th class="px-4 py-2">{{ __('masters.designation') }}</th>
                <th class="px-4 py-2">{{ __('masters.phone') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.monthly_salary') }}</th>
                <th class="px-4 py-2">{{ __('masters.expense_account') }}</th>
                <th class="px-4 py-2">{{ __('masters.status') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
            </x-slot:head>

            @foreach ($staff as $member)
                <tr>
                    <td class="px-4 py-2 font-medium text-slate-900">{{ $member->name }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $member->designation }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $member->phone ?? '—' }}</td>
                    <td class="px-4 py-2 text-right"><x-money :amount="$member->monthly_salary" /></td>
                    <td class="px-4 py-2 text-slate-600">
                        @if ($member->expenseAccount !== null)
                            {{ $member->expenseAccount->code }} —
                            {{ app()->getLocale() === 'bn' ? ($member->expenseAccount->name_bn ?? $member->expenseAccount->name) : $member->expenseAccount->name }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-2">
                        <x-ui.badge :variant="$member->is_active ? 'success' : 'neutral'">
                            {{ $member->is_active ? __('masters.active') : __('masters.inactive') }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 py-2 text-right">
                        <div class="flex justify-end gap-3">
                            @can('update', $member)
                                <button type="button" wire:click="edit({{ $member->id }})"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900">{{ __('masters.edit') }}</button>
                            @endcan
                            @can('delete', $member)
                                <button type="button" wire:click="delete({{ $member->id }})"
                                        class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('masters.delete') }}</button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div>{{ $staff->links() }}</div>
    @endif

    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('masters.edit_staff') : __('masters.new_staff')">
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <x-form.field :label="__('masters.name')" name="name" required>
                        <x-form.input wire:model="name" />
                    </x-form.field>

                    <x-form.field :label="__('masters.designation')" name="designation" required>
                        <x-form.input wire:model="designation" />
                    </x-form.field>

                    <x-form.field :label="__('masters.phone')" name="phone">
                        <x-form.input type="tel" wire:model="phone" />
                    </x-form.field>

                    <x-form.field :label="__('masters.monthly_salary')" name="monthlySalary" required>
                        <x-form.input type="number" step="0.01" min="0" wire:model="monthlySalary" />
                    </x-form.field>

                    <x-form.field :label="__('masters.expense_account')" name="expenseAccountId" class="sm:col-span-2">
                        <x-form.select wire:model="expenseAccountId" :placeholder="__('masters.expense_account')">
                            @foreach ($expenseAccounts as $account)
                                <option value="{{ $account->id }}">
                                    {{ $account->code }} —
                                    {{ app()->getLocale() === 'bn' ? ($account->name_bn ?? $account->name) : $account->name }}
                                </option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    <x-form.field :label="__('masters.joined_on')" name="joinedOn">
                        <x-form.input type="date" wire:model="joinedOn" />
                    </x-form.field>

                    <x-form.field name="isActive" class="flex items-end">
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
