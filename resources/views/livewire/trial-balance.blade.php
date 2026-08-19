<div>
    <div class="mb-6 flex items-end justify-between">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">{{ __('reports.trial_balance') }}</h1>
            <p class="text-sm text-slate-500">{{ __('reports.derived_from_ledger') }}</p>
        </div>
        <label class="text-sm">
            <span class="mr-2 text-slate-600">{{ __('reports.as_of') }}</span>
            <input type="date" wire:model.live="asOf" class="rounded-md border-slate-300 text-sm shadow-sm">
        </label>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('reports.code') }}</th>
                    <th class="px-4 py-3">{{ __('reports.account') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('reports.debit') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('reports.credit') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $row['account']->code }}</td>
                        <td class="px-4 py-2">{{ app()->getLocale() === 'bn' ? ($row['account']->name_bn ?? $row['account']->name) : $row['account']->name }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $row['debit'] !== '0.00' ? number_format((float) $row['debit'], 2) : '' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $row['credit'] !== '0.00' ? number_format((float) $row['credit'], 2) : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">{{ __('reports.no_entries') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                <tr>
                    <td colspan="2" class="px-4 py-3">{{ __('reports.total') }}</td>
                    <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $totalDebit, 2) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $totalCredit, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <p @class([
        'mt-4 rounded-md px-4 py-3 text-sm',
        'bg-emerald-50 text-emerald-800' => $isBalanced,
        'bg-red-50 text-red-800' => ! $isBalanced,
    ])>
        {{ $isBalanced ? __('reports.balanced') : __('reports.out_of_balance') }}
    </p>
</div>
