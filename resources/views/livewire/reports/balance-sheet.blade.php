<div>
    <x-report-header :title="__('reports.balance_sheet')">
        <label>
            <span class="mr-2 text-slate-600">{{ __('reports.as_of') }}</span>
            <input type="date" wire:model.live="asOf" class="rounded-md border-slate-300 text-sm shadow-sm">
        </label>
        <a href="{{ route('reports.export', ['type' => 'balance-sheet', 'as_of' => $asOf]) }}"
           target="_blank"
           class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
            <x-ui.icon name="download" class="w-4 h-4 text-slate-500" />
            <span>CSV</span>
        </a>
    </x-report-header>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
            <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                {{ __('reports.assets') }}
            </h2>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($assets as $row)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ $row['account']->code }}</td>
                            <td class="px-4 py-2.5">
                                <button type="button"
                                        wire:click="openDrillDown({{ $row['account']->id }})"
                                        class="text-left font-medium text-slate-900 hover:text-indigo-600 hover:underline cursor-pointer">
                                    {{ app()->getLocale() === 'bn' ? ($row['account']->name_bn ?? $row['account']->name) : $row['account']->name }}
                                </button>
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums font-medium text-slate-900"><x-money :amount="$row['amount']" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-slate-400">{{ __('reports.no_entries') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                    <tr>
                        <td colspan="2" class="px-4 py-3">{{ __('reports.total_assets') }}</td>
                        <td class="px-4 py-3 text-right font-bold text-slate-950 tabular-nums"><x-money :amount="$totalAssets" /></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
            <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                {{ __('reports.liabilities_and_funds') }}
            </h2>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($liabilities->concat($equity) as $row)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ $row['account']->code }}</td>
                            <td class="px-4 py-2.5">
                                <button type="button"
                                        wire:click="openDrillDown({{ $row['account']->id }})"
                                        class="text-left font-medium text-slate-900 hover:text-indigo-600 hover:underline cursor-pointer">
                                    {{ app()->getLocale() === 'bn' ? ($row['account']->name_bn ?? $row['account']->name) : $row['account']->name }}
                                </button>
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums font-medium text-slate-900"><x-money :amount="$row['amount']" /></td>
                        </tr>
                    @endforeach
                    <tr>
                        <td class="px-4 py-2.5"></td>
                        <td class="px-4 py-2.5 italic text-slate-600">{{ __('reports.accumulated_surplus') }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums font-bold text-slate-900"><x-money :amount="$surplus" /></td>
                    </tr>
                </tbody>
                <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                    <tr>
                        <td colspan="2" class="px-4 py-3">{{ __('reports.total_liabilities_and_funds') }}</td>
                        <td class="px-4 py-3 text-right font-bold text-slate-950 tabular-nums"><x-money :amount="$totalFunded" /></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <p @class([
        'mt-6 rounded-xl px-4 py-3 text-sm font-medium shadow-xs',
        'bg-emerald-50 text-emerald-800 border border-emerald-200' => $isBalanced,
        'bg-red-50 text-red-800 border border-red-200' => ! $isBalanced,
    ])>
        {{ $isBalanced ? __('reports.sheet_balanced') : __('reports.sheet_out_of_balance') }}
    </p>

    @include('livewire.reports.partials.drilldown-modal')
</div>
