<div class="space-y-6">
    <x-ui.page-header :title="__('utilities.readings')" :description="__('utilities.no_meters_for_month_description')">
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="exportCsv"
                        class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                    <x-ui.icon name="arrow-down-tray" class="mr-1.5 h-4 w-4 text-slate-500" />
                    {{ __('utilities.export_csv') }}
                </button>

                @can('create', App\Models\MeterReading::class)
                    <button type="button" wire:click="openImportModal"
                            class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                        <x-ui.icon name="arrow-up-tray" class="mr-1.5 h-4 w-4 text-indigo-600" />
                        {{ __('utilities.import_csv') }}
                    </button>

                    @if ($pendingCount > 0)
                        <x-ui.button wire:click="askConfirm('confirmAll')">
                            <x-ui.icon name="check-circle" class="mr-1.5 h-4 w-4" />
                            {{ __('utilities.confirm_all') }}
                        </x-ui.button>
                    @endif
                @endcan
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.notice :message="$notice" :type="$noticeType" />

    <div class="grid max-w-xl gap-4 sm:grid-cols-2">
        <x-form.field :label="__('billing.month')" name="month">
            <x-form.input type="month" wire:model.live="month" />
        </x-form.field>

        <x-form.field :label="__('utilities.utility')" name="utilityId">
            <x-form.select wire:model.live="utilityId" :placeholder="__('masters.all')">
                @foreach ($utilities as $utility)
                    <option value="{{ $utility->id }}">{{ $utility->displayName() }}</option>
                @endforeach
            </x-form.select>
        </x-form.field>
    </div>

    @if ($meters->isEmpty())
        <x-ui.empty-state :title="__('utilities.no_meters_for_month')"
                          :description="__('utilities.no_meters_for_month_description')" />
    @else
        <form wire:submit="save" class="space-y-4">
            <x-ui.table>
                <x-slot:head>
                    <th class="px-3.5 py-2.5">{{ __('utilities.meter_no') }}</th>
                    <th class="px-3.5 py-2.5">{{ __('masters.flat') }}</th>
                    <th class="px-3 py-2.5 text-right">{{ __('utilities.previous_reading') }}</th>
                    <th class="px-3 py-2.5">{{ __('utilities.current_reading') }}</th>
                    <th class="px-3 py-2.5">{{ __('utilities.reading_date') }}</th>
                    <th class="px-3 py-2.5 text-right">{{ __('utilities.consumption') }}</th>
                    <th class="px-3 py-2.5 text-center">{{ __('utilities.photo_proof') }}</th>
                    <th class="px-3.5 py-2.5">{{ __('masters.status') }}</th>
                    <th class="px-3.5 py-2.5 text-right">{{ __('masters.actions') }}</th>
                </x-slot:head>

                @foreach ($meters as $meter)
                    @php($reading = $readings->get($meter->id))
                    @php($anomaly = $anomalies[$meter->id] ?? null)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="px-3.5 py-2.5 font-medium text-slate-900">
                            <div class="font-bold text-slate-900">{{ $meter->meter_no }}</div>
                            <span class="block text-[11px] text-slate-500">{{ $meter->utility->displayName() }}</span>
                        </td>
                        <td class="px-3.5 py-2.5 text-slate-600">
                            @if ($meter->isCommon())
                                <x-ui.badge variant="warning">{{ __('utilities.common_meter') }}</x-ui.badge>
                            @else
                                <span class="font-medium text-slate-800">{{ $meter->flat->number }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-right font-mono tabular-nums text-slate-600">
                            {{ $previous[$meter->id] ?? '0.000' }}
                        </td>
                        <td class="px-3 py-2.5">
                            @if ($reading?->isApplied())
                                <span class="font-mono tabular-nums text-slate-600">{{ $reading->current_reading }}</span>
                            @else
                                <x-form.input type="number" step="0.001" min="0"
                                              wire:model.live.debounce.400ms="rows.{{ $meter->id }}.current" class="w-32 font-mono text-xs" />
                                @error("rows.{$meter->id}.current")
                                    <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>
                                @enderror
                            @endif
                        </td>
                        <td class="px-3 py-2.5">
                            @if ($reading?->isApplied())
                                <span class="text-xs text-slate-600">{{ $reading->reading_date->format('d M Y') }}</span>
                            @else
                                <x-form.input type="date" wire:model="rows.{{ $meter->id }}.date" class="w-36 text-xs" />
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-right font-mono tabular-nums text-slate-700">
                            @if ($reading)
                                <span class="font-bold">{{ $reading->consumption }}</span>
                                <span class="text-[10px] text-slate-500">{{ $meter->utility->unit_label }}</span>
                                @if ($reading->rolledOver())
                                    <span class="block text-[10px] font-semibold text-amber-600">{{ __('utilities.rolled_over') }}</span>
                                @endif
                            @endif

                            @if ($anomaly?->averageConsumption !== null)
                                <div class="text-[10px] text-slate-400 font-normal mt-0.5">
                                    {{ __('utilities.average_3mo') }}: {{ $anomaly->averageConsumption }}
                                </div>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <div class="flex items-center justify-center gap-1">
                                @if ($reading?->image_path)
                                    <button type="button" wire:click="viewPhoto('{{ $reading->image_path }}')"
                                            class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-[11px] font-medium text-emerald-700 hover:bg-emerald-100"
                                            title="{{ __('utilities.view_photo') }}">
                                        <x-ui.icon name="photo" class="h-3.5 w-3.5" />
                                    </button>
                                @endif

                                @if (! $reading?->isApplied())
                                    <button type="button" wire:click="openPhotoModal({{ $meter->id }})"
                                            class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-200"
                                            title="{{ __('utilities.upload_photo') }}">
                                        <x-ui.icon name="camera" class="h-3.5 w-3.5" />
                                    </button>
                                @endif
                            </div>
                        </td>
                        <td class="px-3.5 py-2.5">
                            <div class="flex flex-col gap-1">
                                <div>
                                    @if ($reading?->isApplied())
                                        <x-ui.badge variant="neutral">{{ $reading->bill?->bill_no ?? __('utilities.reading_billed') }}</x-ui.badge>
                                    @elseif ($reading?->isConfirmed())
                                        <x-ui.badge variant="success">{{ __('utilities.reading_status_confirmed') }}</x-ui.badge>
                                    @elseif ($reading)
                                        <x-ui.badge variant="warning">{{ __('utilities.reading_status_draft') }}</x-ui.badge>
                                    @endif
                                </div>

                                @if ($anomaly?->hasSpike)
                                    <span class="inline-flex items-center rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800" title="{{ $anomaly->warningMessage }}">
                                        ⚡ {{ __('utilities.spike_alert') }}: +{{ $anomaly->variancePercentage }}%
                                    </span>
                                @endif

                                @if ($anomaly?->isNegative)
                                    <span class="inline-flex items-center rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-800" title="{{ $anomaly->warningMessage }}">
                                        ⚠️ {{ __('utilities.negative_alert') }}
                                    </span>
                                @endif

                                @if ($anomaly?->isZeroOccupied && $reading !== null)
                                    <span class="inline-flex items-center rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-700" title="{{ $anomaly->warningMessage }}">
                                        ℹ️ {{ __('utilities.zero_occupied_alert') }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-3.5 py-2.5 text-right">
                            @if ($reading && ! $reading->isApplied())
                                <div class="flex justify-end gap-2">
                                    @if ($reading->isConfirmed())
                                        @can('update', $reading)
                                            <button type="button" wire:click="askConfirm('revert', {{ $reading->id }})"
                                                    class="text-xs font-semibold text-slate-600 hover:text-slate-900">{{ __('utilities.revert_reading') }}</button>
                                        @endcan
                                    @else
                                        @can('confirm', $reading)
                                            <button type="button" wire:click="confirm({{ $reading->id }})"
                                                    class="rounded bg-emerald-600 px-2 py-1 text-xs font-semibold text-white hover:bg-emerald-700 shadow-xs">{{ __('utilities.confirm_reading') }}</button>
                                        @endcan
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            @error('month')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror

            @can('create', App\Models\MeterReading::class)
                <div class="flex justify-end">
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('utilities.save_readings') }}</x-ui.button>
                </div>
            @endcan
        </form>
    @endif

    <!-- CSV Import Modal -->
    @if ($showImportModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-base font-bold text-slate-900">{{ __('utilities.import_csv_title') }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ __('utilities.import_csv_help') }}</p>

                <form wire:submit="importCsv" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700">{{ __('utilities.choose_csv_file') }}</label>
                        <input type="file" wire:model="csvFile" accept=".csv,text/csv,text/plain"
                               class="mt-1.5 block w-full text-xs text-slate-500 file:mr-3 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-slate-800" />
                        @error('csvFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @if (! empty($importErrors))
                        <div class="max-h-36 overflow-y-auto rounded-lg border border-red-200 bg-red-50 p-2.5 text-xs text-red-700 space-y-1">
                            <p class="font-bold">Errors found during import:</p>
                            @foreach ($importErrors as $err)
                                <p>• {{ $err }}</p>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeImportModal" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            {{ __('masters.cancel') }}
                        </button>
                        <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-slate-900 px-4 py-1.5 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-50">
                            {{ __('utilities.upload_and_import') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Photo Upload Modal -->
    @if ($photoMeterId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-base font-bold text-slate-900">{{ __('utilities.upload_photo') }}</h3>
                <p class="mt-1 text-xs text-slate-500">Attach photo evidence of the physical meter display dial.</p>

                <form wire:submit="savePhoto" class="mt-4 space-y-4">
                    <div>
                        <input type="file" wire:model="readingPhoto" accept="image/*"
                               class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-slate-800" />
                        @error('readingPhoto') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closePhotoModal" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            {{ __('masters.cancel') }}
                        </button>
                        <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-slate-900 px-4 py-1.5 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-50">
                            {{ __('masters.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Photo Preview Modal -->
    @if ($viewingPhotoUrl !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/80 p-4" wire:click="closePhotoView">
            <div class="relative max-w-lg rounded-xl bg-white p-4 shadow-2xl" wire:click.stop>
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h4 class="text-sm font-bold text-slate-900">{{ __('utilities.photo_proof') }}</h4>
                    <button type="button" wire:click="closePhotoView" class="text-slate-400 hover:text-slate-600">
                        <x-ui.icon name="x-mark" class="h-5 w-5" />
                    </button>
                </div>
                <div class="mt-3 flex justify-center">
                    <img src="{{ $viewingPhotoUrl }}" alt="Meter Photo Dial" class="max-h-96 rounded-lg object-contain shadow-xs" />
                </div>
            </div>
        </div>
    @endif

    <!-- Confirm Dialogs -->
    @if ($this->isConfirming('confirmAll'))
        <x-ui.confirm-dialog
            :title="__('utilities.confirm_all_title')"
            :message="__('utilities.confirm_all_warning')"
            :confirm-label="__('utilities.confirm_all')"
            variant="primary"
        >
            <div class="space-y-1">
                <div>{{ __('billing.month') }}:
                    <span class="font-medium">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y') }}</span>
                </div>
                <div>{{ __('utilities.readings_to_confirm') }}:
                    <span class="font-medium tabular-nums">{{ $this->unconfirmedCount() }}</span>
                </div>
            </div>
        </x-ui.confirm-dialog>
    @endif

    @if ($this->isConfirming('revert'))
        <x-ui.confirm-dialog
            :title="__('utilities.revert_title')"
            :message="__('utilities.revert_warning')"
        />
    @endif
</div>
