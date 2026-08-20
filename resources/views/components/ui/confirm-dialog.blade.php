{{-- Server-rendered confirmation, same shape as ui.modal: the parent decides whether to
     include it at all. The slot carries the summary of what is about to happen — a dialog
     that only says "are you sure?" tells the operator nothing they did not already know. --}}
@props([
    'title',
    'message' => null,
    'confirmLabel' => null,
    'confirmAction' => 'runConfirmed',
    'cancelAction' => 'cancelConfirm',
    'variant' => 'danger',
])

<div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4 sm:p-8">
    <div class="w-full max-w-lg rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
            <h2 class="text-sm font-semibold text-slate-900">{{ $title }}</h2>

            <button type="button" wire:click="{{ $cancelAction }}" class="text-slate-400 hover:text-slate-700"
                    aria-label="{{ __('confirmations.cancel') }}">
                &times;
            </button>
        </div>

        <div class="space-y-4 px-6 py-5">
            @if ($message !== null)
                <p class="text-sm text-slate-700">{{ $message }}</p>
            @endif

            @if (trim($slot) !== '')
                <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    {{ $slot }}
                </div>
            @endif
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
            <x-ui.button variant="secondary" wire:click="{{ $cancelAction }}">
                {{ __('confirmations.cancel') }}
            </x-ui.button>

            <x-ui.button :variant="$variant" wire:click="{{ $confirmAction }}" wire:loading.attr="disabled">
                {{ $confirmLabel ?? __('confirmations.confirm') }}
            </x-ui.button>
        </div>
    </div>
</div>
