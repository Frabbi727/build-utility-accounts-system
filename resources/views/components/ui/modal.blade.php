{{-- Server-rendered dialog: the parent decides whether to include it at all. --}}
@props([
    'title',
    'maxWidth' => '2xl',
    'zIndex' => 'z-40',
    'closeAction' => 'cancel',
])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '3xl' => 'max-w-3xl',
        '4xl' => 'max-w-4xl',
        '5xl' => 'max-w-5xl',
        'full' => 'max-w-full',
        default => 'max-w-2xl',
    };
@endphp

<div class="fixed inset-0 {{ $zIndex }} flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4 sm:p-8">
    <div class="w-full {{ $maxWidthClass }} rounded-lg bg-white shadow-xl my-auto">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
            <h2 class="text-sm font-semibold text-slate-900">{{ $title }}</h2>

            <button type="button" wire:click="{{ $closeAction }}" class="text-slate-400 hover:text-slate-700 text-lg font-bold leading-none p-1 rounded hover:bg-slate-100" aria-label="{{ __('masters.cancel') }}">
                &times;
            </button>
        </div>

        {{ $slot }}
    </div>
</div>
