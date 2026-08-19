<div>
    <x-report-header :title="__('reports.balance_sheet')">
        <label>
            <span class="mr-2 text-slate-600">{{ __('reports.as_of') }}</span>
            <input type="date" wire:model.live="asOf" class="rounded-md border-slate-300 text-sm shadow-sm">
        </label>
    </x-report-header>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                {{ __('reports.assets') }}
            </h2>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($assets as $row)
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $row['account']->code }}</td>
                            <td class="px-4 py-2">{{ app()->getLocale() === 'bn' ? ($row['account']->name_bn ?? $row['account']->name) : $row['account']->name }}</td>
                            <td class="px-4 py-2 text-right"><x-money :amount="$row['amount']" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-slate-400">{{ __('reports.no_entries') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                    <tr>
                        <td colspan="2" class="px-4 py-3">{{ __('reports.total_assets') }}</td>
                        <td class="px-4 py-3 text-right"><x-money :amount="$totalAssets" /></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                {{ __('reports.liabilities_and_funds') }}
            </h2>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($liabilities->concat($equity) as $row)
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $row['account']->code }}</td>
                            <td class="px-4 py-2">{{ app()->getLocale() === 'bn' ? ($row['account']->name_bn ?? $row['account']->name) : $row['account']->name }}</td>
                            <td class="px-4 py-2 text-right"><x-money :amount="$row['amount']" /></td>
                        </tr>
                    @endforeach
                    <tr>
                        <td class="px-4 py-2"></td>
                        <td class="px-4 py-2 italic text-slate-600">{{ __('reports.accumulated_surplus') }}</td>
                        <td class="px-4 py-2 text-right"><x-money :amount="$surplus" /></td>
                    </tr>
                </tbody>
                <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                    <tr>
                        <td colspan="2" class="px-4 py-3">{{ __('reports.total_liabilities_and_funds') }}</td>
                        <td class="px-4 py-3 text-right"><x-money :amount="$totalFunded" /></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <p @class([
        'mt-6 rounded-md px-4 py-3 text-sm',
        'bg-emerald-50 text-emerald-800' => $isBalanced,
        'bg-red-50 text-red-800' => ! $isBalanced,
    ])>
        {{ $isBalanced ? __('reports.sheet_balanced') : __('reports.sheet_out_of_balance') }}
    </p>
</div>
