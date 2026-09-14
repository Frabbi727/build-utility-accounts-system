<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ __('reports.trial_balance') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('reports.derived_from_ledger') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <label class="text-sm flex items-center gap-2">
                <span class="text-slate-600">{{ __('reports.from') }}</span>
                <input type="date" wire:model.live="from" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm shadow-xs focus:border-slate-900 focus:outline-none">
            </label>

            <label class="text-sm flex items-center gap-2">
                <span class="text-slate-600">{{ $isRanged ? __('reports.to') : __('reports.as_of') }}</span>
                <input type="date" wire:model.live="to" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm shadow-xs focus:border-slate-900 focus:outline-none">
            </label>

            <a href="{{ route('reports.export', ['type' => 'trial-balance', 'from' => $from, 'to' => $to, 'as_of' => $asOf]) }}"
               target="_blank"
               class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                <x-ui.icon name="download" class="w-4 h-4 text-slate-500" />
                <span>CSV</span>
            </a>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-xs">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('reports.code') }}</th>
                    <th class="px-4 py-3">{{ __('reports.account') }}</th>
                    @if ($isRanged)
                        <th class="px-4 py-3 text-right">Opening Balance</th>
                        <th class="px-4 py-3 text-right">Period Debit</th>
                        <th class="px-4 py-3 text-right">Period Credit</th>
                        <th class="px-4 py-3 text-right">Closing Balance</th>
                    @else
                        <th class="px-4 py-3 text-right">{{ __('reports.debit') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('reports.credit') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="px-4 py-2.5 font-mono text-xs text-slate-500 font-semibold">{{ $row['account']->code }}</td>
                        <td class="px-4 py-2.5 font-medium text-slate-900">
                            {{ app()->getLocale() === 'bn' ? ($row['account']->name_bn ?? $row['account']->name) : $row['account']->name }}
                        </td>
                        @if ($isRanged)
                            <td class="px-4 py-2.5 text-right tabular-nums text-slate-600">
                                <x-money :amount="$row['opening'] ?? '0.00'" />
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-slate-900 font-medium">
                                {{ bccomp($row['debit'], '0', 2) !== 0 ? number_format((float) $row['debit'], 2) : '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-slate-900 font-medium">
                                {{ bccomp($row['credit'], '0', 2) !== 0 ? number_format((float) $row['credit'], 2) : '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums font-bold text-slate-950">
                                <x-money :amount="$row['closing'] ?? '0.00'" />
                            </td>
                        @else
                            <td class="px-4 py-2.5 text-right tabular-nums text-slate-900 font-medium">
                                {{ $row['debit'] !== '0.00' ? number_format((float) $row['debit'], 2) : '' }}
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-slate-900 font-medium">
                                {{ $row['credit'] !== '0.00' ? number_format((float) $row['credit'], 2) : '' }}
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isRanged ? 6 : 4 }}" class="px-4 py-8 text-center text-slate-400">
                            {{ __('reports.no_entries') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                <tr>
                    <td colspan="{{ $isRanged ? 3 : 2 }}" class="px-4 py-3">{{ __('reports.total') }}</td>
                    <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $totalDebit, 2) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $totalCredit, 2) }}</td>
                    @if ($isRanged)
                        <td></td>
                    @endif
                </tr>
            </tfoot>
        </table>
    </div>

    <p @class([
        'rounded-lg px-4 py-3 text-sm font-medium shadow-xs',
        'bg-emerald-50 text-emerald-800 border border-emerald-200' => $isBalanced,
        'bg-red-50 text-red-800 border border-red-200' => ! $isBalanced,
    ])>
        {{ $isBalanced ? __('reports.balanced') : __('reports.out_of_balance') }}
    </p>
</div>
