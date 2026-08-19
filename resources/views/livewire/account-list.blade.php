<div class="space-y-6">
    <x-ui.page-header :title="__('accounting.accounts')" :description="__('accounting.accounts_help')">
        <x-slot:actions>
            @can('create', App\Models\Account::class)
                <x-ui.button wire:click="create">{{ __('accounting.new_account') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.table>
        <x-slot:head>
            <th class="px-4 py-2">{{ __('accounting.code') }}</th>
            <th class="px-4 py-2">{{ __('accounting.account_name') }}</th>
            <th class="px-4 py-2">{{ __('accounting.type') }}</th>
            <th class="px-4 py-2 text-right">{{ __('accounting.balance') }}</th>
            <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
        </x-slot:head>

        @foreach ($groups as $group)
            <tr class="bg-slate-50/70">
                <td class="px-4 py-2 font-semibold text-slate-900">{{ $group->code }}</td>
                <td class="px-4 py-2 font-semibold text-slate-900">
                    {{ app()->getLocale() === 'bn' ? ($group->name_bn ?? $group->name) : $group->name }}
                </td>
                <td class="px-4 py-2 text-slate-600">{{ __('accounting.type_'.$group->type->value) }}</td>
                <td class="px-4 py-2"></td>
                <td class="px-4 py-2 text-right">
                    @can('update', $group)
                        <button type="button" wire:click="edit({{ $group->id }})"
                                class="text-sm font-medium text-slate-600 hover:text-slate-900">{{ __('masters.edit') }}</button>
                    @endcan
                </td>
            </tr>

            @foreach ($group->children->sortBy('code') as $child)
                <tr @class(['opacity-50' => ! $child->is_active])>
                    <td class="px-4 py-2 pl-8 text-slate-600">{{ $child->code }}</td>
                    <td class="px-4 py-2 text-slate-700">
                        {{ app()->getLocale() === 'bn' ? ($child->name_bn ?? $child->name) : $child->name }}
                        @if (App\Policies\AccountPolicy::isReserved($child))
                            <x-ui.badge class="ml-1">{{ __('accounting.reserved_account') }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-slate-600">{{ __('accounting.type_'.$child->type->value) }}</td>
                    <td class="px-4 py-2 text-right">
                        @isset($balances[$child->id])
                            <x-money :amount="$balances[$child->id]" />
                        @endisset
                    </td>
                    <td class="px-4 py-2 text-right">
                        <div class="flex justify-end gap-3">
                            @can('update', $child)
                                <button type="button" wire:click="edit({{ $child->id }})"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900">{{ __('masters.edit') }}</button>
                            @endcan
                            @can('delete', $child)
                                <button type="button" wire:click="delete({{ $child->id }})"
                                        class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('masters.delete') }}</button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        @endforeach
    </x-ui.table>

    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('accounting.edit_account') : __('accounting.new_account')">
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    @if ($editingReserved)
                        <p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 sm:col-span-2">
                            {{ __('accounting.reserved_help') }}
                        </p>
                    @endif

                    <x-form.field :label="__('accounting.code')" name="code" required>
                        <x-form.input wire:model="code" :disabled="$editingReserved" />
                    </x-form.field>

                    <x-form.field :label="__('accounting.type')" name="type" required>
                        <x-form.select wire:model="type" :disabled="$editingReserved">
                            @foreach ($types as $case)
                                <option value="{{ $case->value }}">{{ __('accounting.type_'.$case->value) }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    <x-form.field :label="__('accounting.account_name')" name="name" required>
                        <x-form.input wire:model="name" />
                    </x-form.field>

                    <x-form.field :label="__('masters.name_bn')" name="nameBn">
                        <x-form.input wire:model="nameBn" />
                    </x-form.field>

                    <x-form.field :label="__('accounting.parent')" name="parentId" class="sm:col-span-2">
                        <x-form.select wire:model="parentId" :placeholder="__('accounting.no_parent')">
                            @foreach ($parents as $parent)
                                @continue($parent->id === $editingId)
                                <option value="{{ $parent->id }}">
                                    {{ $parent->code }} —
                                    {{ app()->getLocale() === 'bn' ? ($parent->name_bn ?? $parent->name) : $parent->name }}
                                </option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    <x-form.field name="isPostable" :hint="__('accounting.postable_help')">
                        <x-form.checkbox wire:model="isPostable" :label="__('accounting.postable')" />
                    </x-form.field>

                    <x-form.field name="isActive">
                        <x-form.checkbox wire:model="isActive" :label="__('masters.active')" :disabled="$editingReserved" />
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
