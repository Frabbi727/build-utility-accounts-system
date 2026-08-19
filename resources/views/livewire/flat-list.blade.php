<div>
    <div class="mb-6 flex items-end justify-between">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">{{ __('nav.flats') }}</h1>
            <p class="text-sm text-slate-500">{{ __('reports.dues_from_ledger') }}</p>
        </div>
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('billing.search_flat') }}"
               class="rounded-md border-slate-300 text-sm shadow-sm">
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('billing.flat') }}</th>
                    <th class="px-4 py-3">{{ __('billing.building') }}</th>
                    <th class="px-4 py-3">{{ __('billing.owner') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('billing.size_sqft') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('reports.outstanding') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($flats as $flat)
                    <tr>
                        <td class="px-4 py-2 font-medium">{{ $flat->number }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $flat->building->name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $flat->owner?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $flat->size_sqft, 2) }}</td>
                        <td @class([
                            'px-4 py-2 text-right tabular-nums',
                            'text-red-600 font-medium' => bccomp($dues[$flat->id], '0', 2) > 0,
                        ])>{{ number_format((float) $dues[$flat->id], 2) }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('flats.statement', $flat) }}" class="text-slate-600 hover:text-slate-900">
                                {{ __('reports.statement') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">{{ __('billing.no_flats') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $flats->links() }}</div>
</div>
