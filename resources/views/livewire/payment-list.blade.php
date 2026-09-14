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
            <span class="mr-2 text-slate-600">{{ __('billing.status_and_alerts') }}</span>
            <select wire:model.live="statusFilter" class="rounded-md border-slate-300 text-sm shadow-sm">
                <option value="all">{{ __('billing.all_statuses') }}</option>
                <option value="active">{{ __('billing.active') }}</option>
                <option value="reversed">{{ __('billing.reversed') }}</option>
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
            <th class="px-4 py-3 text-center">{{ __('billing.status_and_alerts') }}</th>
            <th class="px-4 py-3 text-right">{{ __('billing.actions') }}</th>
        </x-slot:head>

        @forelse ($payments as $payment)
            <tr class="{{ $payment->isReversed() ? 'bg-rose-50/40 text-slate-400' : '' }}">
                <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ $payment->received_on->format('d M Y') }}</td>
                <td class="px-4 py-2">
                    <a href="{{ route('payments.receipt', $payment) }}"
                       class="font-mono text-xs text-slate-600 underline hover:text-slate-900 {{ $payment->isReversed() ? 'line-through text-rose-600' : '' }}">{{ $payment->receipt_no }}</a>
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
                <td class="px-4 py-2 text-right font-medium {{ $payment->isReversed() ? 'line-through text-slate-400' : '' }}">
                    <x-money :amount="$payment->amount" />
                </td>
                <td class="px-4 py-2 text-center">
                    @if ($payment->isReversed())
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800"
                              title="{{ $payment->reversal_reason }} ({{ $payment->reversed_at?->format('d M Y') }})">
                            {{ __('billing.reversed') }}
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                            {{ __('billing.active') }}
                        </span>
                    @endif
                </td>
                <td class="px-4 py-2 text-right whitespace-nowrap space-x-2">
                    <a href="{{ route('payments.receipt', $payment) }}"
                       title="{{ __('billing.print') }}"
                       class="inline-flex items-center text-slate-500 hover:text-slate-900">
                        <x-ui.icon name="print" class="w-4 h-4" />
                    </a>
                    @can('reverse', $payment)
                        <button type="button"
                                wire:click="openReversalModal({{ $payment->id }})"
                                title="{{ __('billing.reverse_payment') }}"
                                class="inline-flex items-center text-rose-600 hover:text-rose-900 cursor-pointer">
                            <x-ui.icon name="trash" class="w-4 h-4" />
                        </button>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="11" class="px-4 py-6 text-center text-slate-400">{{ __('billing.no_payments') }}</td></tr>
        @endforelse
    </x-ui.table>

    {{ $payments->links() }}

    {{-- Reversal Confirmation Modal --}}
    @if ($showReversalModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs" wire:click="closeReversalModal"></div>
                <div class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl space-y-4">
                    <div class="flex items-center gap-3 text-rose-600">
                        <div class="rounded-full bg-rose-100 p-2">
                            <x-ui.icon name="alert-triangle" class="w-6 h-6" />
                        </div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ __('billing.confirm_reversal_title') }}</h3>
                    </div>

                    <p class="text-sm text-slate-600">
                        {{ __('billing.confirm_reversal_message') }}
                    </p>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
                            {{ __('billing.reversal_reason') }} <span class="text-rose-600">*</span>
                        </label>
                        <input type="text"
                               wire:model="reversalReason"
                               placeholder="{{ __('billing.reversal_reason_placeholder') }}"
                               class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-rose-600 focus:outline-none focus:ring-1 focus:ring-rose-600" />
                        @error('reversalReason') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button"
                                wire:click="closeReversalModal"
                                class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 cursor-pointer">
                            {{ __('billing.cancel') }}
                        </button>
                        <button type="button"
                                wire:click="reversePayment"
                                class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 cursor-pointer">
                            {{ __('billing.reverse_payment') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
