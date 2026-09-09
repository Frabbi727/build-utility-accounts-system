<div class="space-y-6">
    <x-ui.page-header :title="__('masters.owners')" :description="__('masters.owners_help')">
        <x-slot:actions>
            @can('create', App\Models\Owner::class)
                <x-ui.button wire:click="create">{{ __('masters.new_owner') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('livewire.partials.crud-feedback')

    @if ($owners->isEmpty() && $search === '')
        <x-ui.empty-state :title="__('masters.no_owners')" :description="__('masters.no_owners_help')">
            <x-slot:actions>
                @can('create', App\Models\Owner::class)
                    <x-ui.button wire:click="create">{{ __('masters.new_owner') }}</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-ui.empty-state>
    @else
        <div class="flex justify-end">
            <input type="search" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('masters.search') }}" class="w-full sm:w-64">
        </div>

        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-2">{{ __('masters.name') }}</th>
                <th class="px-4 py-2">{{ __('masters.phone') }}</th>
                <th class="px-4 py-2">{{ __('masters.email') }}</th>
                <th class="px-4 py-2">{{ __('masters.login_account') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.flats_owned') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
            </x-slot:head>

            @foreach ($owners as $owner)
                <tr>
                    <td class="px-4 py-2 font-medium text-slate-900">{{ $owner->name }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $owner->phone ?? '—' }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $owner->email ?? '—' }}</td>
                    <td class="px-4 py-2">
                        @if ($owner->user !== null)
                            <x-ui.badge variant="success">{{ $owner->user->email }}</x-ui.badge>
                        @else
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-slate-400">{{ __('masters.no_login') }}</span>
                                @can('update', $owner)
                                    <button type="button" wire:click="openCreateUserModal({{ $owner->id }})"
                                            class="inline-flex items-center rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100">
                                        {{ __('masters.create_login') }}
                                    </button>
                                @endcan
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $owner->flats_count }}</td>
                    <td class="px-4 py-2 text-right">
                        <div class="flex justify-end gap-3">
                            @can('update', $owner)
                                <button type="button" wire:click="edit({{ $owner->id }})"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900">{{ __('masters.edit') }}</button>
                            @endcan
                            @can('delete', $owner)
                                <button type="button" wire:click="confirmDelete({{ $owner->id }})"
                                        class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('masters.delete') }}</button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div>{{ $owners->links() }}</div>
    @endif

    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('masters.edit_owner') : __('masters.new_owner')">
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <x-form.field :label="__('masters.name')" name="name" required>
                        <x-form.input wire:model="name" />
                    </x-form.field>

                    <x-form.field :label="__('masters.phone')" name="phone">
                        <x-form.input type="tel" wire:model="phone" />
                    </x-form.field>

                    <x-form.field :label="__('masters.email')" name="email">
                        <x-form.input type="email" wire:model="email" />
                    </x-form.field>

                    <x-form.field :label="__('masters.national_id')" name="nationalId">
                        <x-form.input wire:model="nationalId" />
                    </x-form.field>

                    <x-form.field :label="__('masters.login_account')" name="userId" class="sm:col-span-2">
                        <x-form.select wire:model="userId" :placeholder="__('masters.no_login')">
                            @foreach ($linkableUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                    <x-ui.button variant="secondary" wire:click="cancel">{{ __('masters.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('masters.save') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    @if ($showUserModal)
        <x-ui.modal :title="__('masters.create_login')">
            @if ($generatedCredentials)
                <div class="space-y-4 px-6 py-5">
                    <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4">
                        <p class="font-medium text-emerald-800">{{ __('masters.user_provisioned') }}</p>
                        <p class="mt-1 text-sm text-emerald-700">{{ __('masters.copy_credentials') }}</p>
                        <div class="mt-3 rounded bg-white p-3 font-mono text-sm text-slate-900 border border-emerald-300 select-all">
                            {{ $generatedCredentials }}
                        </div>
                    </div>
                </div>
                <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-6 py-3">
                    <x-ui.button wire:click="closeUserModal">{{ __('masters.done') }}</x-ui.button>
                </div>
            @else
                <form wire:submit="provisionUser">
                    <div class="grid gap-4 px-6 py-5">
                        <x-form.field :label="__('masters.name')" name="newUserName" required>
                            <x-form.input wire:model="newUserName" />
                        </x-form.field>

                        <x-form.field :label="__('masters.email')" name="newUserEmail" required>
                            <x-form.input type="email" wire:model="newUserEmail" />
                        </x-form.field>

                        <x-form.field :label="__('masters.password')" name="newUserPassword" required>
                            <x-form.input type="text" wire:model="newUserPassword" />
                        </x-form.field>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                        <x-ui.button variant="secondary" wire:click="closeUserModal">{{ __('masters.cancel') }}</x-ui.button>
                        <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('masters.save') }}</x-ui.button>
                    </div>
                </form>
            @endif
        </x-ui.modal>
    @endif
</div>
