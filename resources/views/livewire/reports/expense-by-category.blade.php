<div>
    <x-report-header :title="__('reports.expense_by_category')">
        <label>
            <span class="mr-2 text-slate-600">{{ __('reports.from') }}</span>
            <input type="date" wire:model.live="from" class="rounded-md border-slate-300 text-sm shadow-sm">
        </label>
        <label>
            <span class="mr-2 text-slate-600">{{ __('reports.to') }}</span>
            <input type="date" wire:model.live="to" class="rounded-md border-slate-300 text-sm shadow-sm">
        </label>
    </x-report-header>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('reports.code') }}</th>
                    <th class="px-4 py-3">{{ __('reports.category') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('reports.amount') }}</th>
                    <th class="px-4 py-3 w-1/3">{{ __('reports.share') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $row['account']->code }}</td>
                        <td class="px-4 py-2">{{ app()->getLocale() === 'bn' ? ($row['account']->name_bn ?? $row['account']->name) : $row['account']->name }}</td>
                        <td class="px-4 py-2 text-right"><x-money :amount="$row['amount']" /></td>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-slate-400" style="width: {{ min(100, (float) $row['share']) }}%"></div>
                                </div>
                                <span class="w-12 text-right text-xs tabular-nums text-slate-500">{{ number_format((float) $row['share'], 1) }}%</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">{{ __('reports.no_entries') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                <tr>
                    <td colspan="2" class="px-4 py-3">{{ __('reports.total') }}</td>
                    <td class="px-4 py-3 text-right"><x-money :amount="$total" /></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
