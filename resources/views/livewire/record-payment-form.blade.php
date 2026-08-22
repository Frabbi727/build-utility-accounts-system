<div class="max-w-6xl">
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-slate-900">{{ __('billing.record_payment') }}</h1>
        <p class="text-sm text-slate-500">{{ __('billing.allocation_help') }}</p>
    </div>

    @if ($lastPaymentId !== null)
        <div class="mb-6 flex items-center justify-between rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
            <span class="text-sm text-slate-600">{{ __('billing.receipt_ready') }}</span>
            <a href="{{ route('payments.receipt', $lastPaymentId) }}" target="_blank"
               class="text-sm font-medium text-slate-900 underline">{{ __('billing.view_receipt') }}</a>
        </div>
    @endif

    <x-ui.notice :message="$notice" :type="$noticeType" class="mb-6" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <!-- Left Column: Record Payment Form -->
        <div class="lg:col-span-5 space-y-4">
            <form wire:submit="askSave" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('billing.flat') }}</label>
                    <select wire:model.live="flatId" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                        <option value="">{{ __('billing.select_flat') }}</option>
                        @foreach ($flats as $flat)
                            <option value="{{ $flat->id }}">{{ $flat->building->name }} — {{ $flat->number }}</option>
                        @endforeach
                    </select>
                    @error('flatId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('billing.amount') }}</label>
                    <input type="number" step="0.01" min="0" wire:model.live.debounce.250ms="amount"
                           class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                    @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('billing.method') }}</label>
                    <select wire:model="method" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                        @foreach ($methods as $m)
                            <option value="{{ $m->value }}">{{ __('billing.methods.'.$m->value) }}</option>
                        @endforeach
                    </select>
                    @error('method') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('billing.received_on') }}</label>
                    <input type="date" wire:model="receivedOn" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                    @error('receivedOn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('billing.reference') }}</label>
                    <input type="text" wire:model="reference" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                    @error('reference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50 w-full sm:w-auto">
                    {{ __('billing.save_payment') }}
                </button>
            </form>
        </div>

        <!-- Right Column: Interactive Billing Details -->
        <div class="lg:col-span-7">
            @if ($billingDetails === null)
                <!-- Empty State / Selection Hint -->
                <div class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 p-12 text-center h-full min-h-[300px]">
                    <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                    <h3 class="mt-4 text-sm font-semibold text-slate-900">{{ __('billing.billing_summary') }}</h3>
                    <p class="mt-2 text-sm text-slate-500 max-w-xs">{{ __('billing.select_flat_hint') }}</p>
                </div>
            @else
                <!-- Active Billing Details -->
                <div class="space-y-6">
                    <div class="rounded-lg border border-slate-200 bg-white p-6">
                        <div class="border-b border-slate-100 pb-4 mb-4">
                            <h2 class="text-base font-semibold text-slate-900">{{ __('billing.billing_summary') }}</h2>
                            <p class="text-xs text-slate-500 mt-1">
                                {{ __('billing.flat_details') }}: <span class="font-medium text-slate-700">{{ $billingDetails['flat']->building->name }} — {{ $billingDetails['flat']->number }}</span>
                                @if ($billingDetails['flat']->owner)
                                    <span class="mx-2">•</span>
                                    {{ __('billing.owner') }}: <span class="font-medium text-slate-700">{{ $billingDetails['flat']->owner->name }}</span>
                                @endif
                            </p>
                        </div>

                        <!-- Stats Grid -->
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="rounded-lg bg-slate-50 p-4 border border-slate-100">
                                <span class="block text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('billing.outstanding_dues') }}</span>
                                <span class="mt-1 block text-lg font-bold text-slate-950 tabular-nums"><x-money :amount="$billingDetails['outstanding']" /></span>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-4 border border-slate-100">
                                <span class="block text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('billing.advance_balance') }}</span>
                                <span class="mt-1 block text-lg font-bold text-emerald-700 tabular-nums"><x-money :amount="$billingDetails['advance']" /></span>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-4 border border-slate-100">
                                <span class="block text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('billing.net_payable') }}</span>
                                @php
                                    $isNetCredit = bccomp($billingDetails['netDue'], '0', 2) < 0;
                                    $netDueClass = 'mt-1 block text-lg font-bold tabular-nums';
                                    if ($isNetCredit) {
                                        $netDueClass .= ' text-emerald-700';
                                    } elseif (bccomp($billingDetails['netDue'], '0', 2) > 0) {
                                        $netDueClass .= ' text-red-600';
                                    } else {
                                        $netDueClass .= ' text-slate-950';
                                    }
                                @endphp
                                <span class="{{ $netDueClass }}">
                                    @if ($isNetCredit)
                                        <x-money :amount="bcsub('0', $billingDetails['netDue'], 2)" /> ({{ __('reports.advance') }})
                                    @else
                                        <x-money :amount="$billingDetails['netDue']" />
                                    @endif
                                </span>
                            </div>
                        </div>

                        <!-- Outstanding Bills Table -->
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 mb-3">{{ __('billing.unpaid_bills') }}</h3>
                            @if ($billingDetails['unpaidBills']->isEmpty())
                                <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
                                    {{ __('billing.no_outstanding_bills') }}
                                </div>
                            @else
                                <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                                        <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                                            <tr>
                                                <th class="px-4 py-3">{{ __('billing.bill_no') }}</th>
                                                <th class="px-4 py-3">{{ __('billing.billing_month') }}</th>
                                                <th class="px-4 py-3 text-right">{{ __('reports.amount') }}</th>
                                                <th class="px-4 py-3 text-right">{{ __('reports.paid') }}</th>
                                                <th class="px-4 py-3 text-right">{{ __('reports.outstanding') }}</th>
                                                @if ($billingDetails['allocation'] !== null)
                                                    <th class="px-4 py-3 text-right text-emerald-800 font-bold bg-emerald-50">{{ __('billing.allocating') }}</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach ($billingDetails['unpaidBills'] as $item)
                                                @php
                                                    $bill = $item['bill'];
                                                    $allocatedAmount = $item['allocated'];
                                                    $outstanding = $item['outstanding'];
                                                @endphp
                                                <tr class="hover:bg-slate-50/50">
                                                    <td class="px-4 py-2.5 font-medium text-slate-900">{{ $bill->bill_no }}</td>
                                                    <td class="px-4 py-2.5 text-slate-600">{{ $bill->billing_month->translatedFormat('F Y') }}</td>
                                                    <td class="px-4 py-2.5 text-right text-slate-600 tabular-nums"><x-money :amount="$bill->total_amount" /></td>
                                                    <td class="px-4 py-2.5 text-right text-slate-600 tabular-nums"><x-money :amount="$bill->allocatedAmount()" /></td>
                                                    <td class="px-4 py-2.5 text-right text-slate-900 font-medium tabular-nums"><x-money :amount="$outstanding" /></td>
                                                    @if ($billingDetails['allocation'] !== null)
                                                        <td class="px-4 py-2.5 text-right font-bold bg-emerald-50 text-emerald-700 tabular-nums">
                                                            @if (bccomp($allocatedAmount, '0', 2) > 0)
                                                                +<x-money :amount="$allocatedAmount" />
                                                            @else
                                                                —
                                                            @endif
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                @if ($billingDetails['allocation'] !== null && bccomp($billingDetails['allocation']['advance'], '0', 2) > 0)
                                    <div class="mt-4 flex items-center justify-between rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                        <span class="font-medium">{{ __('billing.remaining_advance') }}</span>
                                        <span class="font-bold tabular-nums">+<x-money :amount="$billingDetails['allocation']['advance']" /></span>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @php($preview = $this->isConfirming('save') ? $this->allocationPreview() : null)

    @if ($preview !== null)
        <x-ui.confirm-dialog
            :title="__('billing.payment_confirm_title')"
            :message="__('billing.payment_confirm_message')"
            :confirm-label="__('billing.save_payment')"
            variant="primary"
        >
            <div class="space-y-2">
                <div>
                    {{ __('billing.flat') }}:
                    <span class="font-medium">{{ $preview['flat']->building->name }} — {{ $preview['flat']->number }}</span>
                </div>
                <div>
                    {{ __('billing.amount') }}:
                    <span class="font-medium tabular-nums"><x-money :amount="$amount" /></span>
                    ({{ __('billing.methods.'.$method) }})
                </div>

                @if ($preview['lines'] !== [])
                    <div class="pt-2 font-medium">{{ __('billing.will_settle') }}</div>
                    <ul class="space-y-1">
                        @foreach ($preview['lines'] as $line)
                            <li class="flex justify-between gap-4">
                                <span>{{ $line['bill']->bill_no }}
                                    ({{ $line['bill']->billing_month->translatedFormat('F Y') }})</span>
                                <span class="tabular-nums"><x-money :amount="$line['amount']" /></span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="pt-2">{{ __('billing.settles_nothing') }}</p>
                @endif

                @if (bccomp($preview['advance'], '0', 2) > 0)
                    <p class="pt-2 text-amber-700">
                        {{ __('billing.goes_to_advance', ['amount' => $preview['advance']]) }}
                    </p>
                @endif
            </div>
        </x-ui.confirm-dialog>
    @endif
</div>
