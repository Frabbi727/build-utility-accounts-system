<div class="space-y-6">
    <x-ui.page-header :title="__('utilities.meters')" :description="__('utilities.no_meters_description')">
        <x-slot:actions>
            @can('create', App\Models\Meter::class)
                <div class="flex items-center gap-2">
                    <x-ui.button variant="secondary" wire:click="startAutoGenerate">
                        {{ __('utilities.auto_generate_meters') }}
                    </x-ui.button>
                    <x-ui.button wire:click="create">{{ __('utilities.new_meter') }}</x-ui.button>
                </div>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('livewire.partials.crud-feedback')

    @if ($utilities->isNotEmpty())
        <div class="max-w-xs">
            <x-form.field :label="__('utilities.utility')" name="filterUtilityId">
                <x-form.select wire:model.live="filterUtilityId" :placeholder="__('masters.all')">
                    @foreach ($utilities as $utility)
                        <option value="{{ $utility->id }}">{{ $utility->displayName() }}</option>
                    @endforeach
                </x-form.select>
            </x-form.field>
        </div>
    @endif

    @if ($meters->isEmpty())
        <x-ui.empty-state :title="__('utilities.no_meters')" :description="__('utilities.no_meters_description')">
            <x-slot:actions>
                @can('create', App\Models\Meter::class)
                    <div class="flex items-center gap-2">
                        <x-ui.button variant="secondary" wire:click="startAutoGenerate">
                            {{ __('utilities.auto_generate_meters') }}
                        </x-ui.button>
                        <x-ui.button wire:click="create">{{ __('utilities.new_meter') }}</x-ui.button>
                    </div>
                @endcan
            </x-slot:actions>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-2">{{ __('utilities.meter_no') }}</th>
                <th class="px-4 py-2">{{ __('utilities.utility') }}</th>
                <th class="px-4 py-2">{{ __('masters.flat') }}</th>
                <th class="px-4 py-2 text-right">{{ __('utilities.digits') }}</th>
                <th class="px-4 py-2 text-right">{{ __('utilities.initial_reading') }}</th>
                <th class="px-4 py-2">{{ __('masters.status') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
            </x-slot:head>

            @foreach ($meters as $meter)
                <tr>
                    <td class="px-4 py-2 font-medium text-slate-900">{{ $meter->meter_no }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $meter->utility->displayName() }}</td>
                    <td class="px-4 py-2 text-slate-600">
                        @if ($meter->isCommon())
                            <x-ui.badge variant="warning">{{ __('utilities.common_meter') }}</x-ui.badge>
                        @else
                            {{ $meter->flat->number }}
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right text-slate-600">{{ $meter->digits }}</td>
                    <td class="px-4 py-2 text-right text-slate-600">{{ $meter->initial_reading }}</td>
                    <td class="px-4 py-2">
                        @if ($meter->is_active)
                            <x-ui.badge variant="success">{{ __('masters.active') }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral">{{ __('masters.inactive') }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right">
                        <div class="flex justify-end gap-3">
                            @can('update', $meter)
                                <button type="button" wire:click="edit({{ $meter->id }})"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900">{{ __('masters.edit') }}</button>
                            @endcan
                            @can('delete', $meter)
                                <button type="button" wire:click="confirmDelete({{ $meter->id }})"
                                        class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('masters.delete') }}</button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    {{-- Manual Meter CRUD Modal --}}
    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('utilities.edit_meter') : __('utilities.new_meter')">
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <x-form.field :label="__('utilities.utility')" name="utilityId" required>
                        <x-form.select wire:model="utilityId" :placeholder="__('masters.select')">
                            @foreach ($utilities as $utility)
                                <option value="{{ $utility->id }}">{{ $utility->displayName() }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    <x-form.field :label="__('masters.flat')" name="flatId" :hint="__('utilities.common_meter_hint')">
                        <x-form.select wire:model="flatId" :placeholder="__('utilities.common_meter')">
                            @foreach ($flats as $flat)
                                <option value="{{ $flat->id }}">{{ $flat->number }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    <x-form.field :label="__('utilities.meter_no')" name="meterNo" required>
                        <x-form.input wire:model="meterNo" />
                    </x-form.field>

                    <x-form.field :label="__('utilities.digits')" name="digits" :hint="__('utilities.digits_hint')" required>
                        <x-form.input type="number" min="1" max="12" wire:model="digits" />
                    </x-form.field>

                    <x-form.field :label="__('utilities.initial_reading')" name="initialReading"
                                  :hint="__('utilities.initial_reading_hint')" required>
                        <x-form.input type="number" step="0.001" min="0" wire:model="initialReading" />
                    </x-form.field>

                    <x-form.field :label="__('utilities.installed_on')" name="installedOn">
                        <x-form.input type="date" wire:model="installedOn" />
                    </x-form.field>

                    <x-form.field name="isActive">
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

    {{-- Auto Generate Utility Meters Wizard Modal --}}
    @if ($showAutoGenerateModal)
        <x-ui.modal :title="__('utilities.auto_generate_meters_title')" maxWidth="4xl" closeAction="cancelAutoGenerate">
            <div class="space-y-6 px-6 py-5">
                <p class="text-sm text-slate-500">{{ __('utilities.auto_generate_meters_description') }}</p>

                {{-- Step 1: Scope & Utilities --}}
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        {{-- Scope selection --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700">{{ __('utilities.scope') }}</label>
                            <div class="mt-2 flex flex-wrap gap-3">
                                <label class="inline-flex items-center gap-1.5 text-sm text-slate-700 cursor-pointer">
                                    <input type="radio" wire:model.live="scope" value="all" class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-slate-500">
                                    {{ __('utilities.all_flats') }}
                                </label>
                                <label class="inline-flex items-center gap-1.5 text-sm text-slate-700 cursor-pointer">
                                    <input type="radio" wire:model.live="scope" value="floor" class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-slate-500">
                                    {{ __('utilities.by_floor') }}
                                </label>
                                <label class="inline-flex items-center gap-1.5 text-sm text-slate-700 cursor-pointer">
                                    <input type="radio" wire:model.live="scope" value="selected" class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-slate-500">
                                    {{ __('utilities.selected_flats') }}
                                </label>
                            </div>

                            @if ($scope === 'floor')
                                <div class="mt-3">
                                    <x-form.select wire:model.live="scopeFloorId" :placeholder="__('utilities.select_floor')">
                                        @foreach ($floors as $floor)
                                            <option value="{{ $floor->id }}">{{ $floor->name }} (Level {{ $floor->level }})</option>
                                        @endforeach
                                    </x-form.select>
                                </div>
                            @elseif ($scope === 'selected')
                                <div class="mt-3 max-h-36 overflow-y-auto rounded-md border border-slate-200 bg-white p-2">
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                        @foreach ($flats as $flat)
                                            <label class="inline-flex items-center gap-1.5 text-xs text-slate-700 cursor-pointer">
                                                <input type="checkbox" wire:model.live="selectedFlatIds" value="{{ $flat->id }}" class="h-3.5 w-3.5 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                                <span>{{ $flat->number }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Utilities selection & prefixes --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700">{{ __('utilities.select_utilities') }}</label>
                            <div class="mt-2 space-y-2">
                                @foreach ($utilities as $utility)
                                    <div class="flex items-center justify-between gap-3 rounded-md bg-white p-2 border border-slate-200">
                                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                            <input type="checkbox" wire:model.live="selectedUtilityIds" value="{{ $utility->id }}" class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                            <span class="font-medium">{{ $utility->displayName() }}</span>
                                        </label>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-xs text-slate-500">{{ __('utilities.utility_prefix') }}:</span>
                                            <input type="text" wire:model="utilityPrefixes.{{ $utility->id }}" class="w-20 rounded border-slate-300 px-2 py-1 text-xs font-mono uppercase shadow-xs focus:border-slate-900 focus:ring-slate-900">
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Step 2: Per-flat utility inclusion/exclusion matrix --}}
                @php
                    $scopedFlats = match ($scope) {
                        'all' => $flats,
                        'floor' => $scopeFloorId !== null ? $flats->where('floor_id', $scopeFloorId) : collect(),
                        'selected' => $flats->whereIn('id', $selectedFlatIds),
                        default => $flats,
                    };
                    $selectedUtilities = $utilities->whereIn('id', $selectedUtilityIds);
                @endphp

                @if ($scopedFlats->isNotEmpty() && $selectedUtilities->isNotEmpty())
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-900">{{ __('utilities.flat_matrix_title') }}</h3>
                            <label class="inline-flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer">
                                <input type="checkbox" wire:model="persistOverrides" class="h-3.5 w-3.5 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                <span>{{ __('utilities.persist_exclusions') }}</span>
                            </label>
                        </div>
                        <p class="text-xs text-slate-500">{{ __('utilities.flat_matrix_help') }}</p>

                        <div class="max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-50 font-medium text-slate-500 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2 text-left">{{ __('masters.flat') }}</th>
                                        @foreach ($selectedUtilities as $utility)
                                            <th class="px-3 py-2 text-center">{{ $utility->displayName() }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($scopedFlats as $flat)
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-3 py-2 font-medium text-slate-900">
                                                {{ $flat->number }}
                                            </td>
                                            @foreach ($selectedUtilities as $utility)
                                                @php
                                                    $overrideKey = "{$flat->id}-{$utility->id}";
                                                    $isEnabled = $flatUtilityOverrides[$overrideKey] ?? true;
                                                @endphp
                                                <td class="px-3 py-2 text-center">
                                                    <input type="checkbox"
                                                           wire:click="toggleFlatUtility({{ $flat->id }}, {{ $utility->id }})"
                                                           @checked($isEnabled)
                                                           class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500 cursor-pointer">
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Step 3: Preview KPI cards & Warnings --}}
                @if ($previewSummary !== null)
                    <div class="space-y-4 pt-2">
                        <h3 class="text-sm font-semibold text-slate-900">{{ __('utilities.preview_summary') }}</h3>

                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-xs">
                                <p class="text-xs font-medium text-slate-500">{{ __('utilities.total_flats') }}</p>
                                <p class="mt-1 text-xl font-bold text-slate-900 tabular-nums">
                                    {{ $previewSummary['totalFlatsCount'] }}
                                </p>
                            </div>

                            <div class="rounded-lg border border-emerald-200 bg-emerald-50/50 p-3 shadow-xs">
                                <p class="text-xs font-medium text-emerald-700">{{ __('utilities.eligible_meters') }}</p>
                                <p class="mt-1 text-xl font-bold text-emerald-800 tabular-nums">
                                    {{ $previewSummary['eligibleMetersCount'] }}
                                </p>
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-xs">
                                <p class="text-xs font-medium text-slate-500">{{ __('utilities.skipped_existing') }}</p>
                                <p class="mt-1 text-xl font-bold text-slate-700 tabular-nums">
                                    {{ $previewSummary['skippedMetersCount'] }}
                                </p>
                            </div>

                            <div class="rounded-lg border border-amber-200 bg-amber-50/50 p-3 shadow-xs">
                                <p class="text-xs font-medium text-amber-700">{{ __('utilities.flats_excluded') }}</p>
                                <p class="mt-1 text-xl font-bold text-amber-800 tabular-nums">
                                    {{ $previewSummary['excludedFlatsCount'] }}
                                </p>
                            </div>
                        </div>

                        {{-- Inactive Warnings Alert --}}
                        @if (! empty($previewSummary['inactiveWarnings']))
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 space-y-1">
                                <div class="font-semibold flex items-center gap-1.5 text-amber-900">
                                    <x-ui.icon name="exclamation-triangle" class="h-4 w-4 text-amber-600" />
                                    {{ __('utilities.inactive_warnings') }} ({{ count($previewSummary['inactiveWarnings']) }})
                                </div>
                                <p>{{ __('utilities.inactive_warnings_help') }}</p>
                                <div class="flex flex-wrap gap-2 pt-1">
                                    @foreach ($previewSummary['inactiveWarnings'] as $warning)
                                        <span class="rounded bg-amber-200/60 px-2 py-0.5 font-medium text-amber-900">
                                            Flat {{ $warning['flat_number'] }} ({{ $warning['utility_name'] }}: {{ $warning['meter_no'] }})
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Skipped Details Details/Accordion --}}
                        @if (! empty($previewSummary['skippedDetails']))
                            <details class="rounded-lg border border-slate-200 bg-slate-50/50 text-xs text-slate-700">
                                <summary class="px-3 py-2 font-semibold cursor-pointer select-none">
                                    {{ __('utilities.skipped_details_title') }} ({{ count($previewSummary['skippedDetails']) }})
                                </summary>
                                <div class="px-3 pb-3 max-h-40 overflow-y-auto">
                                    <ul class="divide-y divide-slate-200">
                                        @foreach ($previewSummary['skippedDetails'] as $skipped)
                                            <li class="py-1.5 flex justify-between items-center">
                                                <span>
                                                    <strong class="text-slate-900">Flat {{ $skipped['flat_number'] }}</strong>
                                                    — {{ $skipped['utility_name'] }}
                                                </span>
                                                <span class="text-slate-500">{{ $skipped['reason'] }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </details>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                <x-ui.button variant="secondary" wire:click="cancelAutoGenerate">{{ __('masters.cancel') }}</x-ui.button>
                <x-ui.button wire:click="executeAutoGenerate" wire:loading.attr="disabled">
                    {{ __('utilities.confirm_generate_meters') }}
                </x-ui.button>
            </div>
        </x-ui.modal>
    @endif
</div>
