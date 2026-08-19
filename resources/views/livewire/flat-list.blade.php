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
                            <div class="flex flex-wrap justify-end gap-3">
                                <a href="{{ route('flats.statement', $flat) }}"
                                   class="text-sm text-slate-600 hover:text-slate-900">{{ __('reports.statement') }}</a>

                                @can('manage', App\Models\Flat::class)
                                    <a href="{{ route('flats.charges', $flat) }}"
                                       class="text-sm text-slate-600 hover:text-slate-900">{{ __('masters.overrides') }}</a>
                                    <button type="button" wire:click="edit({{ $flat->id }})"
                                            class="text-sm font-medium text-slate-600 hover:text-slate-900">
                                        {{ __('masters.edit') }}
                                    </button>
                                    <button type="button" wire:click="toggleActive({{ $flat->id }})"
                                            class="text-sm font-medium text-slate-600 hover:text-slate-900">
                                        {{ $flat->is_active ? __('masters.deactivate') : __('masters.activate') }}
                                    </button>
                                @endcan
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
