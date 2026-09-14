<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ __('reports.owner_dues') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('reports.dues_from_ledger') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <label class="text-sm flex items-center gap-2">
                <span class="text-slate-600">{{ __('reports.as_of') }}</span>
                <input type="date" wire:model.live="asOf" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm shadow-xs focus:border-slate-900 focus:outline-none">
            </label>

            <a href="{{ route('reports.export', ['type' => 'owner-dues', 'as_of' => $asOf]) }}"
               target="_blank"
               class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                <x-ui.icon name="download" class="w-4 h-4 text-slate-500" />
                <span>CSV</span>
            </a>
        </div>
    </div>

    {{-- Aging Tier Quick Filters --}}
    <div class="flex flex-wrap items-center gap-2">
        <button type="button"
                wire:click="$set('bucketFilter', 'all')"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer {{ $bucketFilter === 'all' ? 'bg-slate-900 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
            {{ __('billing.filter_all') }}
        </button>
        <button type="button"
                wire:click="$set('bucketFilter', 'current')"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer {{ $bucketFilter === 'current' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
            {{ __('reports.bucket_current') }} (0–30)
        </button>
        <button type="button"
                wire:click="$set('bucketFilter', 'days_31_60')"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer {{ $bucketFilter === 'days_31_60' ? 'bg-amber-600 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
            {{ __('reports.bucket_31_60') }}
        </button>
        <button type="button"
                wire:click="$set('bucketFilter', 'days_61_90')"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer {{ $bucketFilter === 'days_61_90' ? 'bg-orange-600 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
            {{ __('reports.bucket_61_90') }}
        </button>
        <button type="button"
                wire:click="$set('bucketFilter', 'days_90_plus')"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer {{ $bucketFilter === 'days_90_plus' ? 'bg-rose-600 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
            {{ __('reports.bucket_90_plus') }}
        </button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-xs">
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
                    <th class="px-4 py-3 text-right">{{ __('masters.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="px-4 py-2.5 font-bold text-slate-900">
                            <a href="{{ route('flats.statement', $row['flat']) }}" class="hover:underline">{{ $row['flat']->number }}</a>
                        </td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $row['flat']->owner?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right"><x-money :amount="$row['current']" blank-zero /></td>
                        <td class="px-4 py-2.5 text-right text-slate-700"><x-money :amount="$row['days_31_60']" blank-zero /></td>
                        <td class="px-4 py-2.5 text-right text-amber-700 font-medium"><x-money :amount="$row['days_61_90']" blank-zero /></td>
                        <td class="px-4 py-2.5 text-right text-rose-600 font-bold"><x-money :amount="$row['days_90_plus']" blank-zero /></td>
                        <td class="px-4 py-2.5 text-right font-bold text-slate-950"><x-money :amount="$row['outstanding']" /></td>
                        <td class="px-4 py-2.5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @php
                                    $hasDue = bccomp($row['outstanding'], '0.00', 2) > 0;
                                    $reminder = $hasDue ? \App\Support\DuesReminder::for($row['flat'], $row['outstanding']) : null;
                                @endphp

                                @if ($reminder && $reminder['whatsapp_url'] !== '')
                                    <a href="{{ $reminder['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer"
                                       title="{{ __('reminders.remind_whatsapp') }}"
                                       class="inline-flex items-center text-xs font-semibold text-emerald-600 hover:text-emerald-800">
                                        {{ __('reminders.whatsapp') }}
                                    </a>
                                @endif

                                @can('create', App\Models\Payment::class)
                                    <a href="{{ route('payments.create', ['flat_id' => $row['flat']->id]) }}"
                                       class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                        {{ __('billing.collect') }}
                                    </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">{{ __('reports.no_dues') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                <tr>
                    <td colspan="2" class="px-4 py-3">{{ __('reports.total') }}</td>
                    <td class="px-4 py-3 text-right"><x-money :amount="$totals['current']" /></td>
                    <td class="px-4 py-3 text-right"><x-money :amount="$totals['days_31_60']" /></td>
                    <td class="px-4 py-3 text-right text-amber-700"><x-money :amount="$totals['days_61_90']" /></td>
                    <td class="px-4 py-3 text-right text-rose-600"><x-money :amount="$totals['days_90_plus']" /></td>
                    <td class="px-4 py-3 text-right font-bold"><x-money :amount="$totals['outstanding']" /></td>
                    <td class="px-4 py-3"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
