@props(['bill'])

{{--
    A bill's lines, with the working shown for anything metered.

    The quantity, rate and unit come off the line itself rather than being looked up:
    tariffs are versioned and utility names change, but a bill an owner already holds
    must render the same way forever.

    The meter readings behind a metered line come from the line's `source`, which bill
    generation stamped as the MeterReading. That is what lets an owner check the bill
    against the meter on their wall.
--}}
<table class="min-w-full text-sm">
    <tbody class="divide-y divide-slate-100">
        @foreach ($bill->items as $item)
            @php($reading = $item->source instanceof App\Models\MeterReading ? $item->source : null)
            <tr>
                <td class="py-1.5 pr-4">
                    {{ $item->description }}
                    @if ($item->isMetered())
                        <span class="block text-xs text-slate-500">
                            {{ rtrim(rtrim($item->quantity, '0'), '.') }} {{ $item->unit_label }}
                            &times; {{ rtrim(rtrim($item->unit_rate, '0'), '.') }}
                        </span>
                    @endif
                    @if ($reading !== null)
                        <span class="block text-xs text-slate-500">
                            {{ __('billing.reading_previous') }}
                            {{ rtrim(rtrim($reading->previous_reading, '0'), '.') }}
                            &rarr;
                            {{ __('billing.reading_current') }}
                            {{ rtrim(rtrim($reading->current_reading, '0'), '.') }}
                            &middot;
                            {{ __('billing.consumption') }}
                            {{ rtrim(rtrim($reading->consumption, '0'), '.') }} {{ $item->unit_label }}
                            @if ($reading->is_estimated)
                                &middot; {{ __('billing.estimated_reading') }}
                            @endif
                        </span>
                    @endif
                </td>
                <td class="py-1.5 text-right tabular-nums"><x-money :amount="$item->amount" /></td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="border-t-2 border-slate-900">
            <td class="py-2 pr-4 font-semibold">{{ __('billing.month_total') }}</td>
            <td class="py-2 text-right text-base font-semibold tabular-nums">
                <x-money :amount="$bill->total_amount" />
            </td>
        </tr>
    </tfoot>
</table>
