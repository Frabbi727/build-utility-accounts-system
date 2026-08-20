<div class="space-y-6">
    <x-ui.page-header :title="__('billing.payments')" :description="__('billing.payment_history_hint')" />

    <div class="flex flex-wrap items-end justify-end gap-3 text-sm">
        <label>
            <span class="mr-2 text-slate-600">{{ __('billing.flat') }}</span>
            <select wire:model.live="flatId" class="rounded-md border-slate-300 text-sm shadow-sm">
                <option value="">{{ __('billing.all_flats') }}</option>
                @foreach ($flats as $flat)
                    <option value="{{ $flat->id }}">{{ $flat->number }}</option>
                @endforeach
            </select>
        </label>

        <label>
            <span class="mr-2 text-slate-600">{{ __('reports.method') }}</span>
            <select wire:model.live="method" class="rounded-md border-slate-300 text-sm shadow-sm">
                <option value="">{{ __('billing.all_methods') }}</option>
                @foreach ($methods as $option)
                    <option value="{{ $option->value }}">{{ __('billing.methods.'.$option->value) }}</option>
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

        <input type="search" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('billing.search_payment') }}"
               class="w-full rounded-md border-slate-300 text-sm shadow-sm sm:w-64">
    </div>

    <x-ui.table>
        <x-slot:head>
            <th class="px-4 py-3">{{ __('reports.date') }}</th>
            <th class="px-4 py-3">{{ __('reports.receipt_no') }}</th>
            <th class="px-4 py-3">{{ __('billing.flat') }}</th>
            <th class="px-4 py-3">{{ __('billing.owner') }}</th>
            <th class="px-4 py-3">{{ __('reports.method') }}</th>
            <th class="px-4 py-3">{{ __('billing.reference') }}</th>
            <th class="px-4 py-3">{{ __('billing.received_by') }}</th>
            <th class="px-4 py-3">{{ __('billing.allocated_to') }}</th>
            <th class="px-4 py-3 text-right">{{ __('billing.amount') }}</th>
        </x-slot:head>

        @forelse ($payments as $payment)
            <tr>
                <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ $payment->received_on->format('d M Y') }}</td>
                <td class="px-4 py-2">
                    <a href="{{ route('payments.receipt', $payment) }}"
                       class="font-mono text-xs text-slate-600 underline hover:text-slate-900">{{ $payment->receipt_no }}</a>
                </td>
                <td class="px-4 py-2">
                    <a href="{{ route('flats.statement', $payment->flat) }}" class="font-medium hover:underline">
                        {{ $payment->flat->number }}
                    </a>
                </td>
                <td class="px-4 py-2 text-slate-600">{{ $payment->flat->owner?->name ?? '—' }}</td>
                <td class="px-4 py-2 text-slate-600">{{ __('billing.methods.'.$payment->method->value) }}</td>
                <td class="px-4 py-2 text-slate-500">{{ $payment->reference ?? '—' }}</td>
                <td class="px-4 py-2 text-slate-600">{{ $payment->receivedBy?->name ?? '—' }}</td>
                <td class="px-4 py-2 text-xs text-slate-500">
                    @if ($payment->allocations->isEmpty())
                        {{ __('billing.held_as_advance') }}
                    @else
                        @foreach ($payment->allocations as $allocation)
                            <span class="block">
                                {{ $allocation->bill->bill_no }}
                                <x-money :amount="$allocation->amount" class="ml-1 text-slate-700" />
                            </span>
                        @endforeach
                    @endif
                </td>
                <td class="px-4 py-2 text-right font-medium"><x-money :amount="$payment->amount" /></td>
            </tr>
        @empty
            <tr><td colspan="9" class="px-4 py-6 text-center text-slate-400">{{ __('billing.no_payments') }}</td></tr>
        @endforelse
    </x-ui.table>

    {{ $payments->links() }}
</div>
