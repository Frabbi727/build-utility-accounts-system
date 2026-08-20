<div class="space-y-6">
    <x-ui.page-header :title="__('utilities.readings')" :description="__('utilities.no_meters_for_month_description')">
        <x-slot:actions>
            @if ($pendingCount > 0)
                @can('create', App\Models\MeterReading::class)
                    <x-ui.button wire:click="askConfirm('confirmAll')">{{ __('utilities.confirm_all') }}</x-ui.button>
                @endcan
            @endif
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
                    <th class="px-4 py-2">{{ __('utilities.meter_no') }}</th>
                    <th class="px-4 py-2">{{ __('masters.flat') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('utilities.previous_reading') }}</th>
                    <th class="px-4 py-2">{{ __('utilities.current_reading') }}</th>
                    <th class="px-4 py-2">{{ __('utilities.reading_date') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('utilities.consumption') }}</th>
                    <th class="px-4 py-2">{{ __('masters.status') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
                </x-slot:head>

                @foreach ($meters as $meter)
                    @php($reading = $readings->get($meter->id))
                    <tr>
                        <td class="px-4 py-2 font-medium text-slate-900">
                            {{ $meter->meter_no }}
                            <span class="block text-xs text-slate-500">{{ $meter->utility->displayName() }}</span>
                        </td>
                        <td class="px-4 py-2 text-slate-600">
                            @if ($meter->isCommon())
                                <x-ui.badge variant="warning">{{ __('utilities.common_meter') }}</x-ui.badge>
                            @else
                                {{ $meter->flat->number }}
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right text-slate-600">{{ $previous->get($meter->id) }}</td>
                        <td class="px-4 py-2">
                            @if ($reading?->isApplied())
                                <span class="text-slate-600">{{ $reading->current_reading }}</span>
                            @else
                                <x-form.input type="number" step="0.001" min="0"
                                              wire:model="rows.{{ $meter->id }}.current" />
                                @error("rows.{$meter->id}.current")
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @if ($reading?->isApplied())
                                <span class="text-slate-600">{{ $reading->reading_date->format('d M Y') }}</span>
                            @else
                                <x-form.input type="date" wire:model="rows.{{ $meter->id }}.date" />
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right text-slate-600">
                            @if ($reading)
                                {{ $reading->consumption }} {{ $meter->utility->unit_label }}
                                @if ($reading->rolledOver())
                                    <span class="block text-xs text-amber-600">{{ __('utilities.rolled_over') }}</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @if ($reading?->isApplied())
                                <x-ui.badge variant="neutral">{{ $reading->bill?->bill_no ?? __('utilities.reading_billed') }}</x-ui.badge>
                            @elseif ($reading?->isConfirmed())
                                <x-ui.badge variant="success">{{ __('utilities.reading_status_confirmed') }}</x-ui.badge>
                            @elseif ($reading)
                                <x-ui.badge variant="warning">{{ __('utilities.reading_status_draft') }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            @if ($reading && ! $reading->isApplied())
                                <div class="flex justify-end gap-3">
                                    @if ($reading->isConfirmed())
                                        @can('update', $reading)
                                            <button type="button" wire:click="askConfirm('revert', {{ $reading->id }})"
                                                    class="text-sm font-medium text-slate-600 hover:text-slate-900">{{ __('utilities.revert_reading') }}</button>
                                        @endcan
                                    @else
                                        @can('confirm', $reading)
                                            <button type="button" wire:click="confirm({{ $reading->id }})"
                                                    class="text-sm font-medium text-emerald-600 hover:text-emerald-800">{{ __('utilities.confirm_reading') }}</button>
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
