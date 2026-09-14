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
        <div class="mb-4 grid gap-3 sm:grid-cols-4">
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
            <div>
                <select wire:model.live="slaFilter" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">{{ __('maintenance.all_sla') }}</option>
                    <option value="overdue">{{ __('maintenance.overdue') }}</option>
                    <option value="due_soon">{{ __('maintenance.due_soon') }}</option>
                    <option value="on_track">{{ __('maintenance.on_track') }}</option>
                    <option value="resolved">{{ __('maintenance.resolved_on_time') }} / {{ __('maintenance.resolved_late') }}</option>
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
                    <th class="px-4 py-2">{{ __('maintenance.priority') }} / {{ __('maintenance.sla_status') }}</th>
                    <th class="px-4 py-2">{{ __('maintenance.status') }}</th>
                    <th class="px-4 py-2">{{ __('maintenance.assigned_to') }}</th>
                    <th class="px-4 py-2">{{ __('maintenance.repair_cost') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
                </x-slot:head>

                @foreach ($requests as $requestItem)
                    <tr>
                        <td class="px-4 py-2 font-medium text-slate-900">
                            {{ $requestItem->flat->number }}
                            <div class="text-xs text-slate-500">{{ $requestItem->user->name }}</div>
                            @if ($requestItem->rating)
                                <div class="mt-1 flex items-center text-amber-500 text-xs font-semibold" title="{{ $requestItem->rating_comment }}">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <span>{{ $i <= $requestItem->rating ? '★' : '☆' }}</span>
                                    @endfor
                                    <span class="ml-1 text-[10px] text-slate-600">({{ $requestItem->rating }}/5)</span>
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <div class="font-medium text-slate-900">{{ $requestItem->title }}</div>
                            <div class="text-xs text-slate-500">
                                <span class="font-semibold">{{ $requestItem->category->label() }}:</span>
                                {{ Str::limit($requestItem->description, 60) }}
                            </div>
                            @if ($requestItem->before_photo_path || $requestItem->after_photo_path)
                                <div class="mt-1">
                                    <button type="button" wire:click="openPhotos({{ $requestItem->id }})" class="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-600 hover:underline">
                                        <x-ui.icon name="image" class="w-3 h-3" />
                                        <span>{{ __('maintenance.view_photos') }}</span>
                                    </button>
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex flex-col gap-1 items-start">
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium {{ $requestItem->priority->badgeClasses() }}">
                                    {{ $requestItem->priority->label() }}
                                </span>

                                @if ($requestItem->isOverdue())
                                    <span class="inline-flex items-center rounded-md bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-800">
                                        ⚠️ {{ __('maintenance.hours_overdue', ['hours' => abs($requestItem->hoursRemaining())]) }}
                                    </span>
                                @elseif ($requestItem->due_by && ! $requestItem->status->isClosed())
                                    <span class="text-[10px] text-slate-500">
                                        ⏱️ {{ __('maintenance.hours_remaining', ['hours' => max(0, $requestItem->hoursRemaining())]) }}
                                    </span>
                                @endif
                            </div>
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
                        <td class="px-4 py-2 text-sm">
                            <div class="font-medium text-slate-900 tabular-nums">
                                <x-money :amount="$requestItem->cost" />
                            </div>
                            @if ($requestItem->vendorBills->isNotEmpty())
                                <div class="text-[10px] text-indigo-600 font-mono">
                                    {{ $requestItem->vendorBills->count() }} bill(s)
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <div class="flex items-center justify-end gap-2 text-xs">
                                <button type="button" wire:click="openTimeline({{ $requestItem->id }})"
                                        class="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-800" title="{{ __('maintenance.view_timeline') }}">
                                    <x-ui.icon name="clock" class="w-4 h-4" />
                                </button>

                                @can('update', $requestItem)
                                    @if ($requestItem->assigned_vendor_id)
                                        <button type="button" wire:click="openBillModal({{ $requestItem->id }})"
                                                class="rounded p-1 text-indigo-600 hover:bg-indigo-50 hover:text-indigo-800" title="{{ __('maintenance.create_vendor_bill') }}">
                                            <x-ui.icon name="receipt" class="w-4 h-4" />
                                        </button>
                                    @endif

                                    <button type="button" wire:click="edit({{ $requestItem->id }})"
                                            class="font-medium text-slate-600 hover:text-slate-900">
                                        {{ __('masters.edit') }}
                                    </button>
                                @endcan
                                @can('delete', $requestItem)
                                    <button type="button" wire:click="confirmDelete({{ $requestItem->id }})"
                                            class="font-medium text-red-600 hover:text-red-800">
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

    {{-- Edit / Create Modal --}}
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

                    <x-form.field :label="__('maintenance.before_photo')" name="beforePhoto">
                        <input type="file" wire:model="beforePhoto" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                    </x-form.field>

                    <x-form.field :label="__('maintenance.after_photo')" name="afterPhoto">
                        <input type="file" wire:model="afterPhoto" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
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

    {{-- Activity Timeline Modal --}}
    @if ($showTimelineModal && $timelineRecord)
        <x-ui.modal :title="__('maintenance.timeline') . ' — #' . $timelineRecord->id">
            <div class="px-6 py-4 max-h-96 overflow-y-auto space-y-4">
                @if ($timelineRecord->activities->isEmpty())
                    <p class="text-sm text-slate-500">{{ __('maintenance.no_activities') }}</p>
                @else
                    <ol class="relative border-l border-slate-200 ml-3 space-y-4">
                        @foreach ($timelineRecord->activities as $act)
                            <li class="mb-4 ml-6">
                                <span class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 ring-4 ring-white text-slate-600">
                                    <x-ui.icon name="circle" class="w-3 h-3" />
                                </span>
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-semibold text-slate-900">{{ $act->description }}</h4>
                                    <time class="text-[10px] text-slate-400">{{ $act->created_at->diffForHumans() }}</time>
                                </div>
                                @if ($act->user)
                                    <p class="text-[11px] text-slate-500 mt-0.5">By: {{ $act->user->name }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
            <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-6 py-3">
                <x-ui.button variant="secondary" wire:click="closeTimeline">{{ __('masters.close') }}</x-ui.button>
            </div>
        </x-ui.modal>
    @endif

    {{-- Photos Modal --}}
    @if ($showPhotosModal && $photosRecord)
        <x-ui.modal :title="__('maintenance.view_photos') . ' — #' . $photosRecord->id">
            <div class="grid gap-4 p-6 sm:grid-cols-2">
                <div class="rounded-lg border border-slate-200 p-3 bg-slate-50 text-center">
                    <h4 class="text-xs font-bold uppercase text-slate-700 mb-2">{{ __('maintenance.before_photo') }}</h4>
                    @if ($photosRecord->before_photo_path)
                        <img src="{{ asset('storage/' . $photosRecord->before_photo_path) }}" alt="Before" class="rounded-md mx-auto max-h-64 object-cover border border-slate-300">
                    @else
                        <div class="py-12 text-xs text-slate-400">{{ __('maintenance.no_photos') }}</div>
                    @endif
                </div>
                <div class="rounded-lg border border-slate-200 p-3 bg-slate-50 text-center">
                    <h4 class="text-xs font-bold uppercase text-slate-700 mb-2">{{ __('maintenance.after_photo') }}</h4>
                    @if ($photosRecord->after_photo_path)
                        <img src="{{ asset('storage/' . $photosRecord->after_photo_path) }}" alt="After" class="rounded-md mx-auto max-h-64 object-cover border border-slate-300">
                    @else
                        <div class="py-12 text-xs text-slate-400">{{ __('maintenance.no_photos') }}</div>
                    @endif
                </div>
            </div>
            <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-6 py-3">
                <x-ui.button variant="secondary" wire:click="closePhotos">{{ __('masters.close') }}</x-ui.button>
            </div>
        </x-ui.modal>
    @endif

    {{-- Generate Vendor Bill Modal --}}
    @if ($showBillModal)
        <x-ui.modal :title="__('maintenance.create_vendor_bill') . ' (#' . $billTicketId . ')'">
            <form wire:submit="generateVendorBill">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <x-form.field :label="__('maintenance.assigned_vendor')" name="billVendorId" required>
                        <select wire:model="billVendorId" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                            <option value="">{{ __('masters.select') }}</option>
                            @foreach ($vendors as $vendorOption)
                                <option value="{{ $vendorOption->id }}">{{ $vendorOption->name }}</option>
                            @endforeach
                        </select>
                    </x-form.field>

                    <x-form.field :label="__('maintenance.bill_amount')" name="billAmount" required>
                        <x-form.input wire:model="billAmount" type="number" step="0.01" required placeholder="0.00" />
                    </x-form.field>

                    <x-form.field :label="__('maintenance.expense_account')" name="billExpenseAccountId" required class="sm:col-span-2">
                        <select wire:model="billExpenseAccountId" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                            @foreach ($expenseAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </x-form.field>

                    <x-form.field :label="__('maintenance.description')" name="billDescription" required class="sm:col-span-2">
                        <x-form.input wire:model="billDescription" required />
                    </x-form.field>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                    <x-ui.button variant="secondary" wire:click="closeBillModal">{{ __('masters.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('masters.save') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
