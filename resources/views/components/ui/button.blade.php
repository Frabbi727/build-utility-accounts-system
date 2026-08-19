@props(['variant' => 'primary', 'type' => 'button', 'href' => null])

@php
    $classes = match ($variant) {
        'primary' => 'bg-slate-900 text-white hover:bg-slate-700',
        'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
        'danger' => 'border border-red-300 bg-white text-red-700 hover:bg-red-50',
        default => 'bg-slate-900 text-white hover:bg-slate-700',
    };

    $base = "inline-flex items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition disabled:opacity-50 {$classes}";
@endphp

@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->class($base) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($base) }}>{{ $slot }}</button>
@endif
