<div>
    <x-report-header :title="__('reports.collection_report')">
        <label>
            <span class="mr-2 text-slate-600">{{ __('reports.from') }}</span>
            <input type="date" wire:model.live="from" class="rounded-md border-slate-300 text-sm shadow-sm">
        </label>
        <label>
            <span class="mr-2 text-slate-600">{{ __('reports.to') }}</span>
            <input type="date" wire:model.live="to" class="rounded-md border-slate-300 text-sm shadow-sm">
        </label>
    </x-report-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($byMethod as $row)
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                <div class="text-xs uppercase tracking-wide text-slate-500">{{ __('billing.methods.'.$row['method']->value) }}</div>
                <div class="mt-1 text-lg font-semibold text-slate-900"><x-money :amount="$row['amount']" /></div>
                <div class="text-xs text-slate-500">{{ __('reports.receipt_count', ['count' => $row['count']]) }}</div>
            </div>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('reports.date') }}</th>
                    <th class="px-4 py-3">{{ __('reports.receipt_no') }}</th>
                    <th class="px-4 py-3">{{ __('billing.flat') }}</th>
                    <th class="px-4 py-3">{{ __('billing.owner') }}</th>
                    <th class="px-4 py-3">{{ __('reports.method') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('reports.paid') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($payments as $payment)
                    <tr>
                        <td class="px-4 py-2 text-slate-600">{{ $payment->received_on->toDateString() }}</td>
                        <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $payment->receipt_no }}</td>
                        <td class="px-4 py-2">{{ $payment->flat->number }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $payment->flat->owner?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ __('billing.methods.'.$payment->method->value) }}</td>
                        <td class="px-4 py-2 text-right"><x-money :amount="$payment->amount" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">{{ __('reports.no_collections') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                <tr>
                    <td colspan="5" class="px-4 py-3">{{ __('reports.total') }}</td>
                    <td class="px-4 py-3 text-right"><x-money :amount="$total" /></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <p @class([
        'mt-4 rounded-md px-4 py-3 text-sm',
        'bg-emerald-50 text-emerald-800' => $reconciles,
        'bg-red-50 text-red-800' => ! $reconciles,
    ])>
        {{ $reconciles
            ? __('reports.collections_reconcile')
            : __('reports.collections_mismatch', ['ledger' => number_format((float) $ledgerTotal, 2)]) }}
    </p>
</div>
