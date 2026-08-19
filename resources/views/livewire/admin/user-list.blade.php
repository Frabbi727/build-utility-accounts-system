<div class="space-y-6">
    <x-ui.page-header :title="__('admin.users')" :description="__('admin.users_help')">
        <x-slot:actions>
            @can('create', App\Models\User::class)
                <x-ui.button wire:click="create">{{ __('admin.new_user') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex justify-end">
        <input type="search" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('masters.search') }}" class="w-full sm:w-64">
    </div>

    <x-ui.table>
        <x-slot:head>
            <th class="px-4 py-2">{{ __('masters.name') }}</th>
            <th class="px-4 py-2">{{ __('masters.email') }}</th>
            <th class="px-4 py-2">{{ __('admin.role') }}</th>
            <th class="px-4 py-2">{{ __('admin.linked_owner') }}</th>
            <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
        </x-slot:head>

        @foreach ($users as $user)
            @php $roleName = $user->roles->first()?->name; @endphp
            <tr>
                <td class="px-4 py-2 font-medium text-slate-900">{{ $user->name }}</td>
                <td class="px-4 py-2 text-slate-600">{{ $user->email }}</td>
                <td class="px-4 py-2">
                    @if ($roleName !== null)
                        <x-ui.badge :variant="$roleName === 'admin' ? 'warning' : 'neutral'">
                            {{ __('admin.role_'.$roleName) }}
                        </x-ui.badge>
                    @else
                        <span class="text-xs text-slate-400">{{ __('admin.no_role') }}</span>
                    @endif
                </td>
                <td class="px-4 py-2 text-slate-600">{{ $user->owner?->name ?? '—' }}</td>
                <td class="px-4 py-2 text-right">
                    <div class="flex justify-end gap-3">
                        @can('update', $user)
                            <button type="button" wire:click="edit({{ $user->id }})"
                                    class="text-sm font-medium text-slate-600 hover:text-slate-900">{{ __('masters.edit') }}</button>
                        @endcan
                        @can('delete', $user)
                            <button type="button" wire:click="delete({{ $user->id }})"
                                    class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('masters.delete') }}</button>
                        @else
                            <span class="text-xs text-slate-400">{{ __('admin.cannot_delete_self') }}</span>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-ui.table>

    <div>{{ $users->links() }}</div>

    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('admin.edit_user') : __('admin.new_user')">
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <x-form.field :label="__('masters.name')" name="name" required>
                        <x-form.input wire:model="name" />
                    </x-form.field>

                    <x-form.field :label="__('masters.email')" name="email" required>
                        <x-form.input type="email" wire:model="email" />
                    </x-form.field>

                    <x-form.field :label="__('admin.password')" name="password"
                                  :hint="__('admin.password_help')" :required="! $this->isEditing()">
                        <x-form.input type="password" wire:model="password" autocomplete="new-password" />
                    </x-form.field>

                    <x-form.field :label="__('admin.role')" name="role" required>
                        <x-form.select wire:model.live="role">
                            @foreach ($roles as $case)
                                <option value="{{ $case->value }}">{{ __('admin.role_'.$case->value) }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    <p class="text-sm text-slate-500 sm:col-span-2">
                        {{ __('admin.role_'.$role.'_help') }}
                    </p>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                    <x-ui.button variant="secondary" wire:click="cancel">{{ __('masters.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('masters.save') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
