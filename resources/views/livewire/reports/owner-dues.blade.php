<div>
    <x-report-header :title="__('reports.owner_dues')" :subtitle="__('reports.dues_from_ledger')">
        <label>
            <span class="mr-2 text-slate-600">{{ __('reports.as_of') }}</span>
            <input type="date" wire:model.live="asOf" class="rounded-md border-slate-300 text-sm shadow-sm">
        </label>
    </x-report-header>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('billing.flat') }}</th>
                    <th class="px-4 py-3">{{ __('billing.owner') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('reports.bucket_current') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('reports.bucket_31_60') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('reports.bucket_61_90') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('reports.bucket_90_plus') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('reports.outstanding') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-4 py-2 font-medium">
                            <a href="{{ route('flats.statement', $row['flat']) }}" class="hover:underline">{{ $row['flat']->number }}</a>
                        </td>
                        <td class="px-4 py-2 text-slate-600">{{ $row['flat']->owner?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-right"><x-money :amount="$row['current']" blank-zero /></td>
                        <td class="px-4 py-2 text-right"><x-money :amount="$row['days_31_60']" blank-zero /></td>
                        <td class="px-4 py-2 text-right text-amber-700"><x-money :amount="$row['days_61_90']" blank-zero /></td>
                        <td class="px-4 py-2 text-right text-red-600"><x-money :amount="$row['days_90_plus']" blank-zero /></td>
                        <td class="px-4 py-2 text-right font-medium"><x-money :amount="$row['outstanding']" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">{{ __('reports.no_dues') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                <tr>
                    <td colspan="2" class="px-4 py-3">{{ __('reports.total') }}</td>
                    <td class="px-4 py-3 text-right"><x-money :amount="$totals['current']" /></td>
                    <td class="px-4 py-3 text-right"><x-money :amount="$totals['days_31_60']" /></td>
                    <td class="px-4 py-3 text-right"><x-money :amount="$totals['days_61_90']" /></td>
                    <td class="px-4 py-3 text-right"><x-money :amount="$totals['days_90_plus']" /></td>
                    <td class="px-4 py-3 text-right"><x-money :amount="$totals['outstanding']" /></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
