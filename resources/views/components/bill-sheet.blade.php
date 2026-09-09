@props(['summary'])

@php
    $bill = $summary->bill;
    $flat = $bill->flat;
@endphp

<div class="sheet mx-auto mb-8 max-w-2xl bg-white p-8 shadow break-after-page last:mb-0 last:break-after-auto">
    <header class="flex items-start justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-lg font-semibold">{{ $flat->building->displayName() }}</h1>
            @if ($flat->building->address)
                <p class="mt-1 text-sm text-slate-500">{{ $flat->building->address }}</p>
            @endif
        </div>
        <div class="text-right">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('billing.bill') }}</p>
            <p class="font-mono text-sm font-semibold">{{ $bill->bill_no }}</p>
        </div>
    </header>

    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 border-b border-slate-200 py-4 text-sm">
        <div>
            <dt class="text-slate-500">{{ __('billing.flat') }}</dt>
            <dd class="font-medium">{{ $flat->number }}</dd>
        </div>
        <div>
            <dt class="text-slate-500">{{ __('billing.billing_month') }}</dt>
            <dd class="font-medium">{{ $bill->billing_month->format('F Y') }}</dd>
        </div>
        <div>
            <dt class="text-slate-500">{{ __('billing.owner') }}</dt>
            <dd class="font-medium">{{ $flat->owner?->name ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-slate-500">{{ __('billing.due_date') }}</dt>
            <dd class="font-medium">{{ $bill->due_date->format('d M Y') }}</dd>
        </div>
    </dl>

    @if ($summary->hasArrears())
        <div class="border-b border-slate-200 py-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('billing.previous_dues') }}</p>

            <table class="mt-2 w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($summary->priorOpenBills as $prior)
                        <tr>
                            <td class="py-1.5 font-mono text-xs">{{ $prior['bill']->bill_no }}</td>
                            <td class="py-1.5 text-slate-500">{{ $prior['bill']->billing_month->format('F Y') }}</td>
                            <td class="py-1.5 text-right"><x-money :amount="$prior['outstanding']" /></td>
                        </tr>
                    @endforeach

                    @if ($summary->hasUnbilledArrears())
                        <tr>
                            <td class="py-1.5 text-slate-500" colspan="2">{{ __('billing.other_previous_charges') }}</td>
                            <td class="py-1.5 text-right"><x-money :amount="$summary->unbilledArrears" /></td>
                        </tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr class="border-t border-slate-300">
                        <td class="py-1.5 font-medium" colspan="2">{{ __('billing.previous_dues') }}</td>
                        <td class="py-1.5 text-right font-medium"><x-money :amount="$summary->broughtForward" /></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    <div class="border-b border-slate-200 py-4">
        <p class="mb-2 text-xs uppercase tracking-wide text-slate-500">{{ __('billing.this_month_charges') }}</p>

        <x-bill-lines :bill="$bill" />
    </div>

    <div class="border-b border-slate-200 py-4">
        <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('billing.payments_received') }}</p>

        @if ($summary->hasPayments())
            <table class="mt-2 w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($summary->paymentsReceived as $allocation)
                        <tr>
                            <td class="py-1.5 font-mono text-xs">{{ $allocation->payment->receipt_no }}</td>
                            <td class="py-1.5 text-slate-500">{{ $allocation->payment->received_on->format('d M Y') }}</td>
                            <td class="py-1.5 text-slate-500">{{ __('billing.methods.'.$allocation->payment->method->value) }}</td>
                            <td class="py-1.5 text-right"><x-money :amount="$allocation->amount" /></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-slate-300">
                        <td class="py-1.5 font-medium" colspan="3">{{ __('billing.paid_on_this_bill') }}</td>
                        <td class="py-1.5 text-right font-medium"><x-money :amount="$summary->paidOnThisBill" /></td>
                    </tr>
                </tfoot>
            </table>
        @else
            <p class="mt-2 text-sm text-slate-500">{{ __('billing.no_payments_yet') }}</p>
        @endif
    </div>

    <div class="flex items-center justify-between border-t-2 border-slate-900 py-3">
        <span class="font-semibold">{{ __('billing.total_payable_now') }}</span>
        <span class="text-lg font-semibold"><x-money :amount="$summary->totalDue" /></span>
    </div>

    @if ($summary->hasAdvance())
        <div class="flex items-center justify-between border-t border-slate-200 pt-3 text-sm">
            <span class="text-slate-500">{{ __('billing.advance_held') }}</span>
            <span class="font-medium"><x-money :amount="$summary->advanceHeld" /></span>
        </div>
    @endif

    <p class="mt-6 text-center text-xs text-slate-400">{{ __('billing.computer_generated_bill') }}</p>
</div>
