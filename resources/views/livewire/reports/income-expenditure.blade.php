<div>
    <x-report-header :title="__('reports.income_expenditure')" :subtitle="__('reports.accrual_basis')">
        <label>
            <span class="mr-2 text-slate-600">{{ __('reports.from') }}</span>
            <input type="date" wire:model.live="from" class="rounded-md border-slate-300 text-sm shadow-sm">
        </label>
        <label>
            <span class="mr-2 text-slate-600">{{ __('reports.to') }}</span>
            <input type="date" wire:model.live="to" class="rounded-md border-slate-300 text-sm shadow-sm">
        </label>
        <a href="{{ route('reports.export', ['type' => 'income-expenditure', 'from' => $from, 'to' => $to]) }}"
           target="_blank"
           class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
            <x-ui.icon name="download" class="w-4 h-4 text-slate-500" />
            <span>CSV</span>
        </a>
    </x-report-header>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ([['income', $income, $totalIncome], ['expenditure', $expenses, $totalExpense]] as [$key, $rows, $sectionTotal])
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {{ __('reports.'.$key) }}
                </h2>
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ $row['account']->code }}</td>
                                <td class="px-4 py-2.5">
                                    <button type="button"
                                            wire:click="openDrillDown({{ $row['account']->id }})"
                                            class="text-left font-medium text-slate-900 hover:text-indigo-600 hover:underline cursor-pointer">
                                        {{ app()->getLocale() === 'bn' ? ($row['account']->name_bn ?? $row['account']->name) : $row['account']->name }}
                                    </button>
                                </td>
                                <td class="px-4 py-2.5 text-right font-medium text-slate-900 tabular-nums"><x-money :amount="$row['amount']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-slate-400">{{ __('reports.no_entries') }}</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                        <tr>
                            <td colspan="2" class="px-4 py-3">{{ __('reports.total') }}</td>
                            <td class="px-4 py-3 text-right font-bold text-slate-950 tabular-nums"><x-money :amount="$sectionTotal" /></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endforeach
    </div>

    <div @class([
        'mt-6 flex items-center justify-between rounded-xl px-5 py-4 text-sm font-semibold shadow-xs',
        'bg-emerald-50 text-emerald-800 border border-emerald-200' => bccomp($surplus, '0', 2) >= 0,
        'bg-red-50 text-red-800 border border-red-200' => bccomp($surplus, '0', 2) < 0,
    ])>
        <span>{{ bccomp($surplus, '0', 2) >= 0 ? __('reports.surplus') : __('reports.deficit') }}</span>
        <x-money :amount="bccomp($surplus, '0', 2) < 0 ? bcmul($surplus, '-1', 2) : $surplus" class="text-base font-bold" />
    </div>

    @include('livewire.reports.partials.drilldown-modal')
</div>
