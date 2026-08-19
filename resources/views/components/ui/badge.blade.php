@props(['variant' => 'neutral'])

@php
    $classes = match ($variant) {
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        'danger' => 'bg-red-50 text-red-700 ring-red-600/20',
        default => 'bg-slate-100 text-slate-700 ring-slate-500/20',
    };
@endphp

<span {{ $attributes->class("inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {$classes}") }}>
    {{ $slot }}
</span>
