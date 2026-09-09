<div class="space-y-6">
    <x-ui.page-header :title="__('masters.tenants')" :description="__('masters.tenants_help')">
        <x-slot:actions>
            @if ($building !== null)
                @can('create', App\Models\Tenant::class)
                    <x-ui.button wire:click="create">{{ __('masters.new_tenant') }}</x-ui.button>
                @endcan
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @include('livewire.partials.crud-feedback')

    @if ($building === null)
        <x-ui.empty-state :title="__('masters.no_building')" :description="__('masters.no_building_help')">
            <x-slot:actions>
                <x-ui.button :href="route('buildings.index')">{{ __('masters.new_building') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.empty-state>
    @elseif ($tenants->isEmpty())
        <x-ui.empty-state :title="__('masters.no_tenants')" :description="__('masters.no_tenants_help')">
            <x-slot:actions>
                @can('create', App\Models\Tenant::class)
                    <x-ui.button wire:click="create">{{ __('masters.new_tenant') }}</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-2">{{ __('masters.name') }}</th>
                <th class="px-4 py-2">{{ __('masters.flat') }}</th>
                <th class="px-4 py-2">{{ __('masters.phone') }}</th>
                <th class="px-4 py-2">{{ __('masters.login_account') }}</th>
                <th class="px-4 py-2">{{ __('masters.status') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
            </x-slot:head>

            @foreach ($tenants as $tenant)
                @php $isCurrent = $tenant->lease_ended_on === null || $tenant->lease_ended_on->isFuture(); @endphp
                <tr>
                    <td class="px-4 py-2 font-medium text-slate-900">{{ $tenant->name }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $tenant->flat->number }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $tenant->phone ?? '—' }}</td>
                    <td class="px-4 py-2">
                        @if ($tenant->user !== null)
                            <x-ui.badge variant="success">{{ $tenant->user->email }}</x-ui.badge>
                        @else
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-slate-400">{{ __('masters.no_login') }}</span>
                                @can('update', $tenant)
                                    <button type="button" wire:click="openCreateUserModal({{ $tenant->id }})"
                                            class="inline-flex items-center rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100">
                                        {{ __('masters.create_login') }}
                                    </button>
                                @endcan
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-2">
                        <x-ui.badge :variant="$isCurrent ? 'success' : 'neutral'">
                            {{ $isCurrent ? __('masters.current') : __('masters.past') }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 py-2 text-right">
                        <div class="flex justify-end gap-3">
                            @can('update', $tenant)
                                <button type="button" wire:click="edit({{ $tenant->id }})"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900">{{ __('masters.edit') }}</button>
                            @endcan
                            @can('delete', $tenant)
                                <button type="button" wire:click="confirmDelete({{ $tenant->id }})"
                                        class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('masters.delete') }}</button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div>{{ $tenants->links() }}</div>
    @endif

    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('masters.edit_tenant') : __('masters.new_tenant')">
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <x-form.field :label="__('masters.flat')" name="flatId" required>
                        <x-form.select wire:model="flatId" :placeholder="__('masters.flat')">
                            @foreach ($flats as $flat)
                                <option value="{{ $flat->id }}">{{ $flat->number }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    <x-form.field :label="__('masters.name')" name="name" required>
                        <x-form.input wire:model="name" />
                    </x-form.field>

                    <x-form.field :label="__('masters.phone')" name="phone">
                        <x-form.input type="tel" wire:model="phone" />
                    </x-form.field>

                    <x-form.field :label="__('masters.email')" name="email">
                        <x-form.input type="email" wire:model="email" />
                    </x-form.field>

                    <x-form.field :label="__('masters.lease_started_on')" name="leaseStartedOn">
                        <x-form.input type="date" wire:model="leaseStartedOn" />
                    </x-form.field>

                    <x-form.field :label="__('masters.lease_ended_on')" name="leaseEndedOn">
                        <x-form.input type="date" wire:model="leaseEndedOn" />
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
