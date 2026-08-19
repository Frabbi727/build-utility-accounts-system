@props(['amount', 'blankZero' => false])

@php
    $value = (string) $amount;
@endphp

<span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ $blankZero && bccomp($value, '0', 2) === 0 ? '' : number_format((float) $value, 2) }}</span>
