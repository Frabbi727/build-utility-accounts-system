@if ($showDrillDown && ($drillDown = $this->getDrillDownDetails()))
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs" wire:click="closeDrillDown"></div>

            <div class="relative w-full max-w-4xl rounded-2xl bg-white p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-700">{{ $drillDown['account']->code }}</span>
                            <h3 class="text-lg font-bold text-slate-900">{{ $drillDown['account']->name }}</h3>
                            <span class="text-xs text-slate-500">({{ $drillDown['account']->type->name }})</span>
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500">Ledger transactions and running balance</p>
                    </div>

                    <button type="button"
                            wire:click="closeDrillDown"
                            class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors cursor-pointer">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>

                {{-- Summary Header --}}
                <div class="grid grid-cols-4 gap-3">
                    <div class="rounded-lg bg-slate-50 p-3 border border-slate-100">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500">Opening</span>
                        <span class="mt-0.5 block font-bold text-slate-900 tabular-nums"><x-money :amount="$drillDown['opening']" /></span>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 border border-slate-100">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500">Total Debits</span>
                        <span class="mt-0.5 block font-bold text-slate-900 tabular-nums"><x-money :amount="$drillDown['totalDebit']" /></span>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 border border-slate-100">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500">Total Credits</span>
                        <span class="mt-0.5 block font-bold text-slate-900 tabular-nums"><x-money :amount="$drillDown['totalCredit']" /></span>
                    </div>
                    <div class="rounded-lg bg-indigo-50 p-3 border border-indigo-100">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-indigo-700">Closing</span>
                        <span class="mt-0.5 block font-bold text-indigo-950 tabular-nums"><x-money :amount="$drillDown['closing']" /></span>
                    </div>
                </div>

                {{-- Transactions Table --}}
                <div class="max-h-96 overflow-y-auto rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-xs">
                        <thead class="bg-slate-50 text-slate-600 sticky top-0 font-semibold">
                            <tr>
                                <th class="px-3 py-2.5 text-left">Date</th>
                                <th class="px-3 py-2.5 text-left">Entry #</th>
                                <th class="px-3 py-2.5 text-left">Description</th>
                                <th class="px-3 py-2.5 text-left">Reference</th>
                                <th class="px-3 py-2.5 text-right">Debit</th>
                                <th class="px-3 py-2.5 text-right">Credit</th>
                                <th class="px-3 py-2.5 text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($drillDown['movements'] as $m)
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="px-3 py-2 whitespace-nowrap text-slate-600">{{ $m['date'] }}</td>
                                    <td class="px-3 py-2 font-mono text-slate-500">#{{ $m['id'] }}</td>
                                    <td class="px-3 py-2 font-medium text-slate-900">{{ $m['description'] }}</td>
                                    <td class="px-3 py-2 font-mono text-slate-500">{{ $m['reference'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-slate-900 font-medium">
                                        {{ bccomp($m['debit'], '0', 2) !== 0 ? number_format((float) $m['debit'], 2) : '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums text-slate-900 font-medium">
                                        {{ bccomp($m['credit'], '0', 2) !== 0 ? number_format((float) $m['credit'], 2) : '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums font-bold text-slate-950">
                                        <x-money :amount="$m['running']" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-3 py-6 text-center text-slate-400">
                                        No ledger transactions recorded in this period.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="button"
                            wire:click="closeDrillDown"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800 transition-colors cursor-pointer">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
