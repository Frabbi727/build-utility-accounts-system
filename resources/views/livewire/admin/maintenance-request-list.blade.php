<div>
    <x-ui.page-header :title="__('maintenance.maintenance_requests')" :description="__('maintenance.maintenance_help')">
        <x-slot:actions>
            @if ($building !== null)
                @can('create', App\Models\MaintenanceRequest::class)
                    <x-ui.button wire:click="create">{{ __('maintenance.new_request') }}</x-ui.button>
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
    @else
        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            <div>
                <x-form.input wire:model.live.debounce.250ms="search" :placeholder="__('maintenance.search_requests')" />
            </div>
            <div>
                <select wire:model.live="statusFilter" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">{{ __('maintenance.all_statuses') }}</option>
                    @foreach ($statuses as $st)
                        <option value="{{ $st->value }}">{{ $st->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select wire:model.live="priorityFilter" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">{{ __('maintenance.all_priorities') }}</option>
                    @foreach ($priorities as $pr)
                        <option value="{{ $pr->value }}">{{ $pr->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($requests->isEmpty())
            <x-ui.empty-state :title="__('maintenance.no_requests')" :description="__('maintenance.no_requests_help')">
                <x-slot:actions>
                    @can('create', App\Models\MaintenanceRequest::class)
                        <x-ui.button wire:click="create">{{ __('maintenance.new_request') }}</x-ui.button>
                    @endcan
                </x-slot:actions>
            </x-ui.empty-state>
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-2">{{ __('maintenance.flat') }}</th>
                    <th class="px-4 py-2">{{ __('maintenance.issue') }}</th>
                    <th class="px-4 py-2">{{ __('maintenance.priority') }}</th>
                    <th class="px-4 py-2">{{ __('maintenance.status') }}</th>
                    <th class="px-4 py-2">{{ __('maintenance.assigned_to') }}</th>
                    <th class="px-4 py-2">{{ __('maintenance.date') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
                </x-slot:head>

                @foreach ($requests as $requestItem)
                    <tr>
                        <td class="px-4 py-2 font-medium text-slate-900">
                            {{ $requestItem->flat->number }}
                            <div class="text-xs text-slate-500">{{ $requestItem->user->name }}</div>
                        </td>
                        <td class="px-4 py-2">
                            <div class="font-medium text-slate-900">{{ $requestItem->title }}</div>
                            <div class="text-xs text-slate-500">
                                <span class="font-semibold">{{ $requestItem->category->label() }}:</span>
                                {{ Str::limit($requestItem->description, 60) }}
                            </div>
                        </td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium {{ $requestItem->priority->badgeClasses() }}">
                                {{ $requestItem->priority->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium {{ $requestItem->status->badgeClasses() }}">
                                {{ $requestItem->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-sm text-slate-600">
                            @if ($requestItem->assignedStaff)
                                <div class="text-xs font-medium text-slate-900">Staff: {{ $requestItem->assignedStaff->name }}</div>
                            @endif
                            @if ($requestItem->assignedVendor)
                                <div class="text-xs text-slate-600">Vendor: {{ $requestItem->assignedVendor->name }}</div>
                            @endif
                            @if (! $requestItem->assignedStaff && ! $requestItem->assignedVendor)
                                <span class="text-xs italic text-slate-400">{{ __('maintenance.unassigned') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-sm text-slate-600">
                            {{ $requestItem->created_at->format('M d, Y') }}
                        </td>
                        <td class="px-4 py-2 text-right">
                            <div class="flex justify-end gap-3">
                                @can('update', $requestItem)
                                    <button type="button" wire:click="edit({{ $requestItem->id }})"
                                            class="text-sm font-medium text-slate-600 hover:text-slate-900">
                                        {{ __('masters.edit') }}
                                    </button>
                                @endcan
                                @can('delete', $requestItem)
                                    <button type="button" wire:click="confirmDelete({{ $requestItem->id }})"
                                            class="text-sm font-medium text-red-600 hover:text-red-800">
                                        {{ __('masters.delete') }}
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="mt-4">{{ $requests->links() }}</div>
        @endif
    @endif

    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('maintenance.edit_request') : __('maintenance.new_request')">
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <x-form.field :label="__('maintenance.flat')" name="flatId" required>
                        <select wire:model="flatId" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                            <option value="">{{ __('masters.select') }}</option>
                            @foreach ($flats as $flatOption)
                                <option value="{{ $flatOption->id }}">{{ $flatOption->number }}</option>
                            @endforeach
                        </select>
                    </x-form.field>

                    <x-form.field :label="__('maintenance.category')" name="category" required>
                        <select wire:model="category" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                            @endforeach
                        </select>
                    </x-form.field>

                    <x-form.field :label="__('maintenance.title')" name="title" required class="sm:col-span-2">
                        <x-form.input wire:model="title" required />
                    </x-form.field>

                    <x-form.field :label="__('maintenance.priority')" name="priority" required>
                        <select wire:model="priority" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @foreach ($priorities as $pr)
                                <option value="{{ $pr->value }}">{{ $pr->label() }}</option>
                            @endforeach
                        </select>
                    </x-form.field>

                    <x-form.field :label="__('maintenance.status')" name="status" required>
                        <select wire:model="status" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @foreach ($statuses as $st)
                                <option value="{{ $st->value }}">{{ $st->label() }}</option>
                            @endforeach
                        </select>
                    </x-form.field>

                    <x-form.field :label="__('maintenance.description')" name="description" required class="sm:col-span-2">
                        <textarea wire:model="description" rows="3" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required></textarea>
                    </x-form.field>

                    <x-form.field :label="__('maintenance.assigned_staff')" name="assignedStaffId">
                        <select wire:model="assignedStaffId" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">{{ __('maintenance.unassigned') }}</option>
                            @foreach ($staffMembers as $staffItem)
                                <option value="{{ $staffItem->id }}">{{ $staffItem->name }} ({{ $staffItem->designation }})</option>
                            @endforeach
                        </select>
                    </x-form.field>

                    <x-form.field :label="__('maintenance.assigned_vendor')" name="assignedVendorId">
                        <select wire:model="assignedVendorId" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">{{ __('maintenance.unassigned') }}</option>
                            @foreach ($vendors as $vendorItem)
                                <option value="{{ $vendorItem->id }}">{{ $vendorItem->name }}</option>
                            @endforeach
                        </select>
                    </x-form.field>

                    <x-form.field :label="__('maintenance.resolution_notes')" name="resolutionNotes" class="sm:col-span-2">
                        <textarea wire:model="resolutionNotes" rows="2" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Resolution details or repair notes..."></textarea>
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
