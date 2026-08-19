<div>
    <h1 class="mb-6 text-lg font-semibold text-slate-900">{{ __('nav.dashboard') }}</h1>

    @if ($isStaff)
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('reports.total_receivable') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format((float) $totalReceivable, 2) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('reports.cash_in_hand') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format((float) $cash, 2) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('reports.bank') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format((float) $bank, 2) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('reports.active_flats') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $flatCount }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('reports.unpaid_bills') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $unpaidBills }}</p>
            </div>
        </div>
    @else
        @if ($ownFlat)
            <a href="{{ route('flats.statement', $ownFlat) }}"
               class="inline-block rounded-lg border border-slate-200 bg-white px-6 py-4 hover:border-slate-300">
                {{ __('reports.view_my_statement', ['flat' => $ownFlat->number]) }}
            </a>
        @else
            <p class="text-slate-500">{{ __('reports.no_flat_linked') }}</p>
        @endif
    @endif
</div>
