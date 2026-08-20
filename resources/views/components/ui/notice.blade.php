{{-- Rendered inside the Livewire root so a message set during an action is actually seen.
     The layout's session-flash block only survives a full page load. --}}
@props(['message' => null, 'type' => 'status'])

@if ($message !== null && $message !== '')
    @php
        $classes = $type === 'error'
            ? 'border-red-200 bg-red-50 text-red-800'
            : 'border-emerald-200 bg-emerald-50 text-emerald-800';
    @endphp

    <div {{ $attributes->class("rounded-md border px-4 py-3 text-sm {$classes}") }} role="alert">
        {{ $message }}
    </div>
@endif
