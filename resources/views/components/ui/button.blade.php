@props(['variant' => 'primary', 'type' => 'button', 'href' => null, 'loadingTarget' => null, 'spinner' => true])

@php
    $classes = match ($variant) {
        'primary' => 'bg-slate-900 text-white hover:bg-slate-700',
        'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
        'danger' => 'border border-red-300 bg-white text-red-700 hover:bg-red-50',
        default => 'bg-slate-900 text-white hover:bg-slate-700',
    };

    $base = "inline-flex items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition disabled:opacity-50 disabled:pointer-events-none disabled:cursor-not-allowed {$classes}";
@endphp

@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->class($base) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}"
            @click="if ($el.disabled || $el.dataset.submitting === '1') { $event.stopImmediatePropagation(); return; }; $el.dataset.submitting = '1'; setTimeout(() => { delete $el.dataset.submitting }, 1500)"
            {{ $attributes->class($base) }}>
        @if ($spinner)
            <svg wire:loading{{ $loadingTarget ? " wire:target={$loadingTarget}" : '' }} class="h-4 w-4 shrink-0 animate-spin text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
        @endif
        {{ $slot }}
    </button>
@endif
