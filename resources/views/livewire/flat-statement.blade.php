<div>
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-slate-900">
            {{ __('reports.statement_for', ['flat' => $flat->number]) }}
        </h1>
        <p class="text-sm text-slate-500">{{ $flat->building->name }} · {{ $flat->owner?->name ?? '—' }}</p>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('reports.outstanding') }}</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ number_format((float) $outstanding, 2) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('reports.advance') }}</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ number_format((float) $advance, 2) }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('reports.date') }}</th>
                    <th class="px-4 py-3">{{ __('reports.description') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('reports.charge') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('reports.paid') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('reports.balance') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ $row['date']->format('d M Y') }}</td>
                        <td class="px-4 py-2">
                            {{ $row['description'] }}
                            @if ($row['memo'])
                                <span class="ml-1 font-mono text-xs text-slate-400">{{ $row['memo'] }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $row['debit'] !== '0.00' ? number_format((float) $row['debit'], 2) : '' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $row['credit'] !== '0.00' ? number_format((float) $row['credit'], 2) : '' }}</td>
                        <td class="px-4 py-2 text-right font-medium tabular-nums">{{ number_format((float) $row['balance'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">{{ __('reports.no_entries') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
