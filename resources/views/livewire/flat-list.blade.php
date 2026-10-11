<div class="space-y-6">
    <x-ui.page-header :title="__('masters.flats')" :description="__('reports.dues_from_ledger')">
        <x-slot:actions>
            @if ($building !== null)
                @can('manage', App\Models\Flat::class)
                    <x-ui.button variant="secondary" wire:click="startBulk">{{ __('masters.bulk_generate') }}</x-ui.button>
                    <x-ui.button wire:click="create">{{ __('masters.new_flat') }}</x-ui.button>
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
        @if ($showBulkForm)
            <x-ui.card :title="__('masters.bulk_generate')">
                <p class="mb-4 text-sm text-slate-500">{{ __('masters.bulk_generate_help') }}</p>

                <form wire:submit="generateFlats" class="grid gap-4 sm:grid-cols-4">
                    <x-form.field :label="__('masters.from_level')" name="fromLevel" required>
                        <x-form.input type="number" min="0" wire:model="fromLevel" />
                    </x-form.field>

                    <x-form.field :label="__('masters.to_level')" name="toLevel" required>
                        <x-form.input type="number" min="0" wire:model="toLevel" />
                    </x-form.field>

                    <x-form.field :label="__('masters.flat_suffixes')" name="suffixes"
                                  :hint="__('masters.flat_suffixes_help')" class="sm:col-span-2" required>
                        <x-form.input wire:model="suffixes" />
                    </x-form.field>

                    <x-form.field :label="__('masters.default_size_sqft')" name="bulkSizeSqft" required>
                        <x-form.input type="number" step="0.01" min="0" wire:model="bulkSizeSqft" />
                    </x-form.field>

                    <x-form.field name="createMissingFloors" class="flex items-end">
                        <x-form.checkbox wire:model="createMissingFloors" :label="__('masters.create_missing_floors')" />
                    </x-form.field>

                    <div class="flex items-end gap-2 sm:col-span-2">
                        <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('masters.bulk_generate') }}</x-ui.button>
                        <x-ui.button variant="secondary" wire:click="cancelBulk">{{ __('masters.cancel') }}</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        @endif

        <div class="flex justify-end">
            <input type="search" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('billing.search_flat') }}" class="w-full sm:w-64">
        </div>

        @if ($flats->isEmpty() && $search === '')
            <x-ui.empty-state :title="__('billing.no_flats')" :description="__('masters.bulk_generate_help')">
                <x-slot:actions>
                    @can('manage', App\Models\Flat::class)
                        <x-ui.button wire:click="startBulk">{{ __('masters.bulk_generate') }}</x-ui.button>
                        <x-ui.button variant="secondary" wire:click="create">{{ __('masters.new_flat') }}</x-ui.button>
                    @endcan
                </x-slot:actions>
            </x-ui.empty-state>
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-2">{{ __('masters.flat_number') }}</th>
                    <th class="px-4 py-2">{{ __('masters.floor') }}</th>
                    <th class="px-4 py-2">{{ __('masters.owner') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('masters.size_sqft') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('masters.monthly_charge') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('reports.outstanding') }}</th>
                    <th class="px-4 py-2">{{ __('masters.status') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
                </x-slot:head>

                @forelse ($flats as $flat)
                    <tr>
                        <td class="px-4 py-2 font-medium text-slate-900">{{ $flat->number }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $flat->floor?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $flat->owner?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-right"><x-money :amount="$flat->size_sqft" /></td>
                        <td class="px-4 py-2 text-right text-slate-600">
                            <x-money :amount="$monthlyCharges[$flat->id] ?? '0.00'" />
                        </td>
                        <td @class([
                            'px-4 py-2 text-right',
                            'font-medium text-red-600' => bccomp($dues[$flat->id], '0', 2) > 0,
                        ])><x-money :amount="$dues[$flat->id]" /></td>
                        <td class="px-4 py-2">
                            <x-ui.badge :variant="$flat->is_active ? 'success' : 'neutral'">
                                {{ $flat->is_active ? __('masters.active') : __('masters.inactive') }}
                            </x-ui.badge>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <div x-data="{ open: false, dropUp: false }"
                                 @click.outside="open = false"
                                 @keydown.escape.window="open = false"
                                 class="relative inline-block text-left">
                                <button type="button"
                                        @click="
                                            open = !open;
                                            if (open) {
                                                const rect = $el.getBoundingClientRect();
                                                dropUp = (window.innerHeight - rect.bottom) < 220;
                                            }
                                        "
                                        title="{{ __('masters.actions') }}"
                                        aria-label="{{ __('masters.actions') }}"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition focus:outline-none focus:ring-2 focus:ring-slate-300 cursor-pointer">
                                    <x-ui.icon name="dots-vertical" class="w-4 h-4" />
                                </button>

                                <div x-show="open"
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="transform opacity-0 scale-95"
                                     x-transition:enter-end="transform opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="transform opacity-100 scale-100"
                                     x-transition:leave-end="transform opacity-0 scale-95"
                                     :class="dropUp ? 'bottom-full mb-1 origin-bottom-right' : 'top-full mt-1 origin-top-right'"
                                     class="absolute right-0 z-50 w-48 rounded-lg bg-white py-1 shadow-lg ring-1 ring-slate-900/10 divide-y divide-slate-100 focus:outline-none text-left"
                                     style="display: none;">
                                    @php
                                        $hasDue = bccomp($dues[$flat->id], '0', 2) > 0;
                                        $reminder = $hasDue ? \App\Support\DuesReminder::for($flat, $dues[$flat->id]) : null;
                                    @endphp

                                    <div class="py-1">
                                        @if ($reminder && $reminder['whatsapp_url'] !== '')
                                            <a href="{{ $reminder['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer"
                                               class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-emerald-600 hover:bg-emerald-50 transition">
                                                <x-ui.icon name="whatsapp" class="w-4 h-4 shrink-0" />
                                                <span>{{ __('reminders.whatsapp') }}</span>
                                            </a>
                                        @endif

                                        @can('create', App\Models\Payment::class)
                                            <a href="{{ route('payments.create', ['flat_id' => $flat->id]) }}"
                                               class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-emerald-600 hover:bg-emerald-50 transition">
                                                <x-ui.icon name="collect" class="w-4 h-4 shrink-0" />
                                                <span>{{ __('billing.collect') }}</span>
                                            </a>
                                        @endcan

                                        <a href="{{ route('flats.statement', $flat) }}"
                                           class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 transition">
                                            <x-ui.icon name="statement" class="w-4 h-4 shrink-0 text-indigo-600" />
                                            <span>{{ __('reports.statement') }}</span>
                                        </a>
                                    </div>

                                    @can('manage', App\Models\Flat::class)
                                        <div class="py-1">
                                            <a href="{{ route('flats.charges', $flat) }}"
                                               class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 transition">
                                                <x-ui.icon name="overrides" class="w-4 h-4 shrink-0 text-slate-500" />
                                                <span>{{ __('masters.overrides') }}</span>
                                            </a>

                                            <button type="button"
                                                    @click="open = false; $wire.edit({{ $flat->id }})"
                                                    class="flex w-full items-center gap-2 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 transition text-left cursor-pointer">
                                                <x-ui.icon name="edit" class="w-4 h-4 shrink-0 text-slate-500" />
                                                <span>{{ __('masters.edit') }}</span>
                                            </button>

                                            <button type="button"
                                                    @click="open = false; $wire.toggleActive({{ $flat->id }})"
                                                    @class([
                                                        'flex w-full items-center gap-2 px-3 py-1.5 text-xs font-medium transition text-left cursor-pointer',
                                                        'text-amber-600 hover:bg-amber-50' => $flat->is_active,
                                                        'text-emerald-600 hover:bg-emerald-50' => ! $flat->is_active,
                                                    ])>
                                                @if ($flat->is_active)
                                                    <x-ui.icon name="ban" class="w-4 h-4 shrink-0" />
                                                    <span>{{ __('masters.deactivate') }}</span>
                                                @else
                                                    <x-ui.icon name="check-circle" class="w-4 h-4 shrink-0" />
                                                    <span>{{ __('masters.activate') }}</span>
                                                @endif
                                            </button>
                                        </div>
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-6 text-center text-slate-400">{{ __('billing.no_flats') }}</td></tr>
                @endforelse
            </x-ui.table>

            <div>{{ $flats->links() }}</div>
        @endif
    @endif

    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('masters.edit_flat') : __('masters.new_flat')">
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <x-form.field :label="__('masters.flat_number')" name="number" required>
                        <x-form.input wire:model="number" />
                    </x-form.field>

                    <x-form.field :label="__('masters.size_sqft')" name="sizeSqft" required>
                        <x-form.input type="number" step="0.01" min="0" wire:model="sizeSqft" />
                    </x-form.field>

                    <x-form.field :label="__('masters.floor')" name="floorId">
                        <x-form.select wire:model="floorId" :placeholder="__('masters.no_floor')">
                            @foreach ($floors as $floor)
                                <option value="{{ $floor->id }}">{{ $floor->name }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    <x-form.field :label="__('masters.owner')" name="ownerId">
                        <x-form.select wire:model="ownerId" :placeholder="__('masters.no_owner')">
                            @foreach ($owners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    <x-form.field :label="__('masters.unit_type')" name="unitTypeId">
                        <x-form.select wire:model="unitTypeId" :placeholder="__('masters.unit_type_any')">
                            @foreach ($unitTypes as $unitType)
                                <option value="{{ $unitType->id }}">{{ $unitType->displayName() }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    <x-form.field name="isActive" class="sm:col-span-2">
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
