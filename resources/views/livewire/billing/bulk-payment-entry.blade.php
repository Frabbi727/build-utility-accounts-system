<div class="space-y-6" x-data="{
    calculateTotal() {
        let total = 0;
        document.querySelectorAll('input[data-amount-input]').forEach(el => {
            let row = el.closest('tr');
            let checkbox = row ? row.querySelector('input[type=checkbox]') : null;
            if (checkbox && checkbox.checked) {
                let val = parseFloat(el.value);
                if (!isNaN(val) && val > 0) {
                    total += val;
                }
            }
        });
        return total.toFixed(2);
    }
}">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ __('billing.bulk_payment_entry') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('billing.bulk_payment_help') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('payments.create') }}" wire:navigate class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                <x-ui.icon name="plus" class="w-4 h-4" />
                {{ __('billing.record_payment') }}
            </a>
            <a href="{{ route('payments.index') }}" wire:navigate class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                <x-ui.icon name="billing" class="w-4 h-4" />
                {{ __('billing.payments') }}
            </a>
        </div>
    </div>

    <x-ui.notice :message="$notice" :type="$noticeType" />

    {{-- Control Toolbar --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
                    {{ __('billing.received_on') }}
                </label>
                <input type="date"
                       wire:model="defaultReceivedOn"
                       class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900" />
                @error('defaultReceivedOn') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
                    {{ __('billing.method') }}
                </label>
                <select wire:model.live="defaultMethod"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900">
                    @foreach ($methods as $m)
                        <option value="{{ $m->value }}">{{ __('billing.methods.'.$m->value) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
                    {{ __('billing.search_flat') }}
                </label>
                <input type="text"
                       wire:model.live.debounce.250ms="search"
                       placeholder="{{ __('billing.search_flat') }}..."
                       class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900" />
            </div>

            <div class="flex items-center gap-2">
                <button type="button"
                        wire:click="autoFillDues"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-md bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 transition-colors cursor-pointer">
                    <x-ui.icon name="zap" class="w-4 h-4 text-indigo-600" />
                    {{ __('billing.auto_fill_dues') }}
                </button>
                <button type="button"
                        wire:click="clearAll"
                        class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-md transition-colors cursor-pointer">
                    {{ __('billing.clear_all') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Main Table --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-700">
                    <tr>
                        <th class="w-12 px-4 py-3 text-center">
                            <span class="sr-only">Select</span>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold">{{ __('billing.flat') }}</th>
                        <th class="px-4 py-3 text-left font-semibold">{{ __('billing.owner') }}</th>
                        <th class="px-4 py-3 text-right font-semibold">{{ __('billing.outstanding_after') }}</th>
                        <th class="px-4 py-3 text-right font-semibold">{{ __('billing.advance_held') }}</th>
                        <th class="px-4 py-3 text-right font-semibold">{{ __('billing.net_due') }}</th>
                        <th class="px-4 py-3 text-left font-semibold w-40">{{ __('billing.amount') }}</th>
                        <th class="px-4 py-3 text-left font-semibold w-36">{{ __('billing.method') }}</th>
                        <th class="px-4 py-3 text-left font-semibold w-48">{{ __('billing.reference') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($flatsData as $row)
                        @php
                            $flatId = $row['flat']->id;
                            $isEnabled = $entries[$flatId]['enabled'] ?? false;
                        @endphp
                        <tr class="transition-colors hover:bg-slate-50/80 {{ $isEnabled ? 'bg-indigo-50/30' : '' }}">
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox"
                                       wire:model.live="entries.{{ $flatId }}.enabled"
                                       class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 cursor-pointer" />
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-900">
                                {{ $row['flat']->number }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $row['flat']->owner?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium {{ bccomp($row['receivable'], '0', 2) > 0 ? 'text-rose-600' : 'text-slate-500' }}">
                                <x-money :amount="$row['receivable']" />
                            </td>
                            <td class="px-4 py-3 text-right font-medium {{ bccomp($row['advance'], '0', 2) > 0 ? 'text-emerald-600' : 'text-slate-500' }}">
                                <x-money :amount="$row['advance']" />
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-slate-900">
                                <x-money :amount="$row['netDue']" />
                            </td>
                            <td class="px-4 py-2">
                                <input type="text"
                                       data-amount-input
                                       wire:model="entries.{{ $flatId }}.amount"
                                       placeholder="0.00"
                                       class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-right font-mono text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900 {{ $isEnabled ? 'bg-white font-semibold' : 'bg-slate-50 text-slate-400' }}" />
                                @error("entries.{$flatId}.amount")
                                    <span class="text-xs text-rose-600 block mt-0.5">{{ $message }}</span>
                                @enderror
                            </td>
                            <td class="px-4 py-2">
                                <select wire:model="entries.{{ $flatId }}.method"
                                        class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900 {{ $isEnabled ? 'bg-white' : 'bg-slate-50 text-slate-400' }}">
                                    @foreach ($methods as $m)
                                        <option value="{{ $m->value }}">{{ __('billing.methods.'.$m->value) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-2">
                                <input type="text"
                                       wire:model="entries.{{ $flatId }}.reference"
                                       placeholder="{{ __('billing.reference') }}..."
                                       class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-xs focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900 {{ $isEnabled ? 'bg-white' : 'bg-slate-50 text-slate-400' }}" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-slate-500">
                                {{ __('billing.no_flats') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Submission Bottom Bar --}}
        <div class="border-t border-slate-200 bg-slate-50 px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-sm text-slate-600">
                <span>{{ count(array_filter($entries, fn($e) => !empty($e['enabled']))) }} {{ __('billing.flats_to_bill') }}</span>
            </div>

            <div class="flex items-center gap-3">
                <button type="button"
                        wire:click="save"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 disabled:opacity-50 transition-all cursor-pointer">
                    <x-ui.icon name="check" class="w-4 h-4" />
                    <span>{{ __('billing.save_bulk_payments') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>
