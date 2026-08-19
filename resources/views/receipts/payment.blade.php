<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('billing.receipt') }} — {{ $payment->receipt_no }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .sheet { box-shadow: none; margin: 0; max-width: none; }
        }
    </style>
</head>
<body class="bg-slate-100 py-8 text-slate-900">
    <div class="no-print mx-auto mb-4 flex max-w-2xl justify-end gap-2 px-6">
        <a href="{{ route('flats.statement', $payment->flat) }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
            {{ __('billing.back_to_statement') }}
        </a>
        <button type="button" onclick="window.print()"
                class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">
            {{ __('billing.print') }}
        </button>
    </div>

    <div class="sheet mx-auto max-w-2xl bg-white p-8 shadow">
        <header class="flex items-start justify-between border-b border-slate-200 pb-4">
            <div>
                <h1 class="text-lg font-semibold">{{ $building->displayName() }}</h1>
                @if ($building->address)
                    <p class="mt-1 text-sm text-slate-500">{{ $building->address }}</p>
                @endif
            </div>
            <div class="text-right">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('billing.receipt') }}</p>
                <p class="font-mono text-sm font-semibold">{{ $payment->receipt_no }}</p>
            </div>
        </header>

        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 border-b border-slate-200 py-4 text-sm">
            <div>
                <dt class="text-slate-500">{{ __('billing.flat') }}</dt>
                <dd class="font-medium">{{ $payment->flat->number }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">{{ __('billing.received_on') }}</dt>
                <dd class="font-medium">{{ $payment->received_on->format('d M Y') }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">{{ __('billing.owner') }}</dt>
                <dd class="font-medium">{{ $payment->flat->owner?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">{{ __('billing.method') }}</dt>
                <dd class="font-medium">{{ __('billing.methods.'.$payment->method->value) }}</dd>
            </div>
            @if ($payment->reference)
                <div class="col-span-2">
                    <dt class="text-slate-500">{{ __('billing.reference') }}</dt>
                    <dd class="font-medium">{{ $payment->reference }}</dd>
                </div>
            @endif
        </dl>

        <div class="py-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('billing.allocated_to') }}</p>

            @if ($payment->allocations->isEmpty())
                <p class="mt-2 text-sm text-slate-500">{{ __('billing.held_as_advance') }}</p>
            @else
                <table class="mt-2 w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($payment->allocations as $allocation)
                            <tr>
                                <td class="py-1.5">{{ $allocation->bill->bill_no }}</td>
                                <td class="py-1.5 text-slate-500">
                                    {{ $allocation->bill->billing_month->format('F Y') }}
                                </td>
                                <td class="py-1.5 text-right"><x-money :amount="$allocation->amount" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="flex items-center justify-between border-t-2 border-slate-900 py-3">
            <span class="font-semibold">{{ __('billing.amount_received') }}</span>
            <span class="text-lg font-semibold"><x-money :amount="$payment->amount" /></span>
        </div>

        <dl class="grid grid-cols-2 gap-4 border-t border-slate-200 pt-3 text-sm">
            <div>
                <dt class="text-slate-500">{{ __('billing.outstanding_after') }}</dt>
                <dd class="font-medium"><x-money :amount="$outstanding" /></dd>
            </div>
            <div>
                <dt class="text-slate-500">{{ __('billing.advance_held') }}</dt>
                <dd class="font-medium"><x-money :amount="$advance" /></dd>
            </div>
        </dl>

        <p class="mt-6 text-center text-xs text-slate-400">{{ __('billing.computer_generated') }}</p>
    </div>
</body>
</html>
