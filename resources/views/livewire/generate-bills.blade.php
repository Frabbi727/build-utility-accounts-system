<div class="space-y-6">
    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900">{{ __('billing.generate_monthly_bills') }}</h1>
            <p class="text-sm text-slate-500">{{ __('billing.generate_help') }}</p>
        </div>
        @if ($month !== '')
            <div class="flex items-center gap-2">
                <x-ui.button variant="secondary" :href="route('bills.print-month', ['month' => $month])">
                    <x-ui.icon name="printer" class="mr-1.5 h-4 w-4" />
                    {{ __('billing.print_all_bills') }}
                </x-ui.button>
            </div>
        @endif
    </div>

    <x-ui.notice :message="$notice" :type="$noticeType" />

    <!-- Configuration & Action Card -->
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="block text-sm font-semibold text-slate-700">{{ __('billing.building') }}</label>
                <select wire:model.live="buildingId" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-xs focus:border-slate-900 focus:ring-slate-900">
                    @foreach ($buildings as $building)
                        <option value="{{ $building->id }}">{{ $building->name }}</option>
                    @endforeach
                </select>
                @error('buildingId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">{{ __('billing.month') }}</label>
                <input type="month" wire:model.live="month" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-xs focus:border-slate-900 focus:ring-slate-900">
                @error('month') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-end gap-2.5">
                <button type="button" wire:click="simulate" wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 disabled:opacity-50">
                    <x-ui.icon name="chart-bar" class="mr-1.5 h-4 w-4 text-indigo-600" />
                    {{ __('billing.simulate_btn') }}
                </button>

                <button type="button" wire:click="askGenerate" wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50 shadow-xs">
                    <x-ui.icon name="bolt" class="mr-1.5 h-4 w-4 text-amber-400" />
                    {{ __('billing.generate') }}
                </button>
            </div>
        </div>

        @if ($pending['readings'] > 0 || $pending['distributions'] > 0)
            <div class="mt-4 flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50/70 p-3 text-xs text-amber-800">
                <x-ui.icon name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                <div>
                    <span class="font-semibold">{{ __('billing.pending_work') }}:</span>
                    {{ __('billing.generate_leaves_behind', [
                        'readings' => $pending['readings'],
                        'distributions' => $pending['distributions'],
                    ]) }}
                </div>
            </div>
        @endif
    </div>

    <!-- Simulation Results Section -->
    @if ($simulation !== null)
        <div class="space-y-4">
            <!-- Simulation Header & KPI Cards -->
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <x-ui.icon name="eye" class="h-5 w-5 text-indigo-600" />
                        {{ __('billing.simulation_title') }}
                        <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-800">
                            {{ $simulation->billingMonth->translatedFormat('F Y') }}
                        </span>
                    </h2>
                    <p class="text-xs text-slate-500">{{ __('billing.simulation_help') }}</p>
                </div>
                <button type="button" wire:click="clearSimulation" class="text-xs font-medium text-slate-500 hover:text-slate-800 underline">
                    Clear Preview
                </button>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
                    <p class="text-xs font-medium text-slate-500">{{ __('billing.flats_to_bill') }}</p>
                    <p class="mt-1 text-xl font-bold text-slate-900 tabular-nums">
                        {{ $simulation->totalFlatsToBill }}
                        <span class="text-xs font-normal text-slate-400">/ {{ $simulation->totalActiveFlats }}</span>
                    </p>
                    <p class="mt-0.5 text-[11px] text-slate-500">
                        {{ $simulation->alreadyBilledCount > 0 ? $simulation->alreadyBilledCount.' already billed' : '0 already billed' }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
                    <p class="text-xs font-medium text-slate-500">{{ __('billing.estimated_invoiced_total') }}</p>
                    <p class="mt-1 text-xl font-bold text-slate-900 tabular-nums">
                        ৳{{ number_format((float) $simulation->estimatedTotalAmount, 2) }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-slate-500">
                        Charge: ৳{{ number_format((float) $simulation->estimatedChargeHeadsAmount, 0) }} | Util: ৳{{ number_format((float) $simulation->estimatedUtilitiesAmount, 0) }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
                    <p class="text-xs font-medium text-slate-500">{{ __('billing.estimated_advances') }}</p>
                    <p class="mt-1 text-xl font-bold text-emerald-700 tabular-nums">
                        ৳{{ number_format((float) $simulation->estimatedAdvancesAmount, 2) }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-emerald-600">Auto-drawn from credits</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
                    <p class="text-xs font-medium text-slate-500">{{ __('billing.estimated_net_receivable') }}</p>
                    <p class="mt-1 text-xl font-bold text-indigo-700 tabular-nums">
                        ৳{{ number_format((float) $simulation->estimatedNetReceivable, 2) }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-indigo-600">Net cash to collect</p>
                </div>
            </div>

            <!-- Simulation Filter Bar -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-lg border border-slate-200 bg-white p-3 shadow-xs">
                <div class="flex flex-wrap items-center gap-1.5">
                    <button type="button" wire:click="$set('filter', 'all')"
                            class="rounded-md px-2.5 py-1 text-xs font-medium {{ $filter === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        {{ __('billing.filter_all') }} ({{ count($simulation->flats) }})
                    </button>
                    <button type="button" wire:click="$set('filter', 'to_bill')"
                            class="rounded-md px-2.5 py-1 text-xs font-medium {{ $filter === 'to_bill' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        {{ __('billing.filter_to_bill') }} ({{ $simulation->totalFlatsToBill }})
                    </button>
                    <button type="button" wire:click="$set('filter', 'warnings')"
                            class="rounded-md px-2.5 py-1 text-xs font-medium {{ $filter === 'warnings' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-700 hover:bg-amber-100' }}">
                        {{ __('billing.filter_warnings') }}
                    </button>
                    <button type="button" wire:click="$set('filter', 'already_billed')"
                            class="rounded-md px-2.5 py-1 text-xs font-medium {{ $filter === 'already_billed' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        {{ __('billing.filter_already_billed') }} ({{ $simulation->alreadyBilledCount }})
                    </button>
                </div>

                <div class="w-full sm:w-64">
                    <input type="search" wire:model.live.debounce.250ms="search" placeholder="{{ __('billing.search_flat') }}..."
                           class="w-full rounded-md border-slate-300 text-xs shadow-xs focus:border-slate-900 focus:ring-slate-900" />
                </div>
            </div>

            <!-- Simulation Flat Breakdown Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-3.5 py-2.5 font-semibold">{{ __('billing.flat') }}</th>
                                <th class="px-3 py-2.5 font-semibold text-right">{{ __('billing.charge_heads_total') }}</th>
                                <th class="px-3 py-2.5 font-semibold text-right">{{ __('billing.utilities_total') }}</th>
                                <th class="px-3 py-2.5 font-semibold text-right">{{ __('billing.cost_distributions_total') }}</th>
                                <th class="px-3 py-2.5 font-semibold text-right">{{ __('billing.adhoc_total') }}</th>
                                <th class="px-3.5 py-2.5 font-semibold text-right">{{ __('billing.total_bill') }}</th>
                                <th class="px-3 py-2.5 font-semibold text-right text-emerald-700">{{ __('billing.advance_applied_est') }}</th>
                                <th class="px-3.5 py-2.5 font-semibold text-right text-indigo-900">{{ __('billing.net_due') }}</th>
                                <th class="px-3.5 py-2.5 font-semibold">{{ __('billing.status_and_alerts') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($filteredFlats as $sim)
                                <tr class="hover:bg-slate-50/80 transition-colors {{ $sim->isAlreadyBilled ? 'opacity-60 bg-slate-50/40' : '' }}">
                                    <td class="px-3.5 py-2.5 font-medium text-slate-900">
                                        <div class="font-bold text-slate-900">{{ $sim->flat->number }}</div>
                                        <div class="text-[11px] text-slate-500 font-normal">
                                            {{ $sim->flat->owner?->name ?? 'No Owner' }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono tabular-nums text-slate-700">
                                        ৳{{ number_format((float) $sim->chargeHeadsAmount, 2) }}
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono tabular-nums text-slate-700">
                                        ৳{{ number_format((float) $sim->utilitiesAmount, 2) }}
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono tabular-nums text-slate-700">
                                        ৳{{ number_format((float) $sim->costDistributionsAmount, 2) }}
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono tabular-nums text-slate-700">
                                        ৳{{ number_format((float) $sim->adHocAmount, 2) }}
                                    </td>
                                    <td class="px-3.5 py-2.5 text-right font-mono font-bold tabular-nums text-slate-900">
                                        ৳{{ number_format((float) $sim->totalAmount, 2) }}
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono tabular-nums text-emerald-700 font-medium">
                                        {{ bccomp($sim->estimatedAdvanceDrawdown, '0', 2) > 0 ? '-৳'.number_format((float) $sim->estimatedAdvanceDrawdown, 2) : '—' }}
                                    </td>
                                    <td class="px-3.5 py-2.5 text-right font-mono font-bold tabular-nums text-indigo-900">
                                        ৳{{ number_format((float) $sim->netReceivable, 2) }}
                                    </td>
                                    <td class="px-3.5 py-2.5">
                                        <div class="flex flex-wrap items-center gap-1">
                                            @if ($sim->isAlreadyBilled)
                                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600">
                                                    {{ __('billing.already_billed_badge') }}
                                                </span>
                                            @elseif (! $sim->flat->is_active)
                                                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600">
                                                    {{ __('billing.inactive_badge') }}
                                                </span>
                                            @elseif (bccomp($sim->totalAmount, '0', 2) <= 0)
                                                <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">
                                                    {{ __('billing.skipped_zero') }}
                                                </span>
                                            @else
                                                <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700">
                                                    {{ __('billing.ready_to_bill') }}
                                                </span>
                                            @endif

                                            @foreach ($sim->warnings as $warning)
                                                @if ($warning !== 'Already billed for this month.' && $warning !== 'Flat is marked inactive.' && $warning !== 'Total billable amount is zero (will be skipped).')
                                                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-800" title="{{ $warning }}">
                                                        ⚠️ {{ $warning }}
                                                    </span>
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-xs text-slate-500">
                                        {{ __('billing.no_flats') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- Confirmation Dialog -->
    @if ($this->isConfirming('generate'))
        <x-ui.confirm-dialog
            :title="__('billing.generate_confirm_title')"
            :message="__('billing.generate_confirm_message')"
            :confirm-label="__('billing.generate')"
            variant="primary"
        >
            <div class="space-y-2 text-xs">
                <div>
                    {{ __('billing.month') }}:
                    <span class="font-semibold">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y') }}</span>
                </div>
                <div>
                    {{ __('billing.flats_to_bill') }}:
                    <span class="font-semibold tabular-nums">{{ $this->flatsToBill() }}</span>
                </div>

                @if ($pending['readings'] > 0 || $pending['distributions'] > 0)
                    <p class="pt-2 text-red-700 font-medium">
                        {{ __('billing.generate_leaves_behind', [
                            'readings' => $pending['readings'],
                            'distributions' => $pending['distributions'],
                        ]) }}
                    </p>
                @endif
            </div>
        </x-ui.confirm-dialog>
    @endif
</div>
