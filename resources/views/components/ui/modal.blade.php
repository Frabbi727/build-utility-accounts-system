{{-- Server-rendered dialog: the parent decides whether to include it at all. --}}
@props(['title'])

<div class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4 sm:p-8">
    <div class="w-full max-w-2xl rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
            <h2 class="text-sm font-semibold text-slate-900">{{ $title }}</h2>

            <button type="button" wire:click="cancel" class="text-slate-400 hover:text-slate-700" aria-label="{{ __('masters.cancel') }}">
                &times;
            </button>
        </div>

        {{ $slot }}
    </div>
</div>
