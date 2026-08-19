@props(['bill'])

{{--
    A bill's lines, with the working shown for anything metered.

    The quantity, rate and unit come off the line itself rather than being looked up:
    tariffs are versioned and utility names change, but a bill an owner already holds
    must render the same way forever.
--}}
<table class="min-w-full text-sm">
    <tbody class="divide-y divide-slate-100">
        @foreach ($bill->items as $item)
            <tr>
                <td class="py-1.5 pr-4">
                    {{ $item->description }}
                    @if ($item->isMetered())
                        <span class="block text-xs text-slate-500">
                            {{ rtrim(rtrim($item->quantity, '0'), '.') }} {{ $item->unit_label }}
                            &times; {{ rtrim(rtrim($item->unit_rate, '0'), '.') }}
                        </span>
                    @endif
                </td>
                <td class="py-1.5 text-right tabular-nums"><x-money :amount="$item->amount" /></td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="border-t-2 border-slate-900">
            <td class="py-2 pr-4 font-semibold">{{ __('reports.total') }}</td>
            <td class="py-2 text-right text-base font-semibold tabular-nums">
                <x-money :amount="$bill->total_amount" />
            </td>
        </tr>
    </tfoot>
</table>
