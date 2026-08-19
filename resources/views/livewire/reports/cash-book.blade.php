<div>
    <x-report-header :title="__('reports.cash_book')">
        <label>
            <span class="mr-2 text-slate-600">{{ __('reports.account') }}</span>
            <select wire:model.live="account" class="rounded-md border-slate-300 text-sm shadow-sm">
                @foreach ($accounts as $code)
                    <option value="{{ $code->value }}">{{ __('reports.'.($code->value === '1010' ? 'cash_in_hand' : 'bank')) }}</option>
                @endforeach
            </select>
        </label>
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
                    <th class="px-4 py-3">{{ __('reports.date') }}</th>
                    <th class="px-4 py-3">{{ __('reports.ref_no') }}</th>
                    <th class="px-4 py-3">{{ __('reports.description') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('reports.receipt') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('reports.payment') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('reports.balance') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr class="bg-slate-50/60">
                    <td colspan="5" class="px-4 py-2 text-slate-600">{{ __('reports.opening_balance') }}</td>
                    <td class="px-4 py-2 text-right font-medium"><x-money :amount="$opening" /></td>
                </tr>
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-4 py-2 text-slate-600">{{ $row['date']->toDateString() }}</td>
                        <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $row['ref_no'] }}</td>
                        <td class="px-4 py-2">{{ $row['description'] }}</td>
                        <td class="px-4 py-2 text-right text-emerald-700"><x-money :amount="$row['debit']" blank-zero /></td>
                        <td class="px-4 py-2 text-right text-red-600"><x-money :amount="$row['credit']" blank-zero /></td>
                        <td class="px-4 py-2 text-right"><x-money :amount="$row['balance']" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">{{ __('reports.no_entries') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                <tr>
                    <td colspan="5" class="px-4 py-3">{{ __('reports.closing_balance') }}</td>
                    <td class="px-4 py-3 text-right"><x-money :amount="$closing" /></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
