<div x-data="{
        isOpen: @entangle('isOpen'),
        init() {
            window.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                    e.preventDefault();
                    this.isOpen = !this.isOpen;
                    if (this.isOpen) {
                        $nextTick(() => $refs.searchInput?.focus());
                    }
                }
                if (e.key === 'Escape' && this.isOpen) {
                    this.isOpen = false;
                }
            });
            this.$watch('isOpen', value => {
                if (value) {
                    $nextTick(() => $refs.searchInput?.focus());
                }
            });
        }
    }"
    x-show="isOpen"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto p-4 sm:p-6 md:p-20"
    role="dialog"
    aria-modal="true">

    <!-- Backdrop -->
    <div x-show="isOpen"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity"
         @click="isOpen = false"></div>

    <!-- Modal Dialog -->
    <div x-show="isOpen"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="relative mx-auto max-w-2xl transform divide-y divide-slate-100 overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-black/5 transition-all">

        <!-- Search Header -->
        <div class="relative flex items-center px-4">
            <svg class="pointer-events-none h-5 w-5 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
            </svg>
            <input type="text"
                   x-ref="searchInput"
                   wire:model.live.debounce.200ms="query"
                   placeholder="{{ __('billing.search_flat') }} ({{ __('masters.flat_number') }}, {{ __('billing.owner') }}, {{ __('masters.phone') }})..."
                   class="h-12 w-full border-0 bg-transparent pl-3 pr-10 text-sm text-slate-900 placeholder-slate-400 focus:ring-0 focus:outline-hidden">
            @if ($query !== '')
                <button type="button" wire:click="$set('query', '')" class="text-xs text-slate-400 hover:text-slate-600">
                    ✕
                </button>
            @endif
        </div>

        <!-- Search Content Area -->
        <div class="max-h-96 overflow-y-auto p-2">
            @if ($flats->isNotEmpty())
                <div class="mb-3 px-3 pt-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                    {{ __('nav.flats') }}
                </div>
                <div class="space-y-1">
                    @foreach ($flats as $flat)
                        @php
                            $due = $dues[$flat->id] ?? '0.00';
                            $hasDue = bccomp($due, '0.00', 2) > 0;
                            $reminder = $reminders[$flat->id] ?? null;
                        @endphp
                        <div class="flex items-center justify-between rounded-lg p-2.5 hover:bg-slate-50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-slate-100 font-bold text-slate-700">
                                    {{ $flat->number }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('flats.statement', $flat) }}"
                                           @click="isOpen = false"
                                           class="font-medium text-slate-900 hover:underline">
                                            {{ __('billing.flat') }} {{ $flat->number }}
                                        </a>
                                        @if ($flat->floor)
                                            <span class="text-xs text-slate-400">&bull; {{ $flat->floor->name }}</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-slate-500">
                                        {{ $flat->owner?->name ?? 'No owner' }}
                                        @if ($flat->owner?->phone)
                                            &bull; {{ $flat->owner->phone }}
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-red-50 text-red-700' => $hasDue,
                                    'bg-emerald-50 text-emerald-700' => ! $hasDue,
                                ])>
                                    <x-money :amount="$due" />
                                </span>

                                @if ($reminder && $reminder['whatsapp_url'] !== '')
                                    <a href="{{ $reminder['whatsapp_url'] }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       title="{{ __('reminders.remind_whatsapp') }}"
                                       class="inline-flex items-center rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100">
                                        {{ __('reminders.whatsapp') }}
                                    </a>
                                @endif

                                @can('create', App\Models\Payment::class)
                                    <a href="{{ route('payments.create', ['flat_id' => $flat->id]) }}"
                                       @click="isOpen = false"
                                       class="inline-flex items-center rounded-md border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100">
                                        {{ __('billing.collect') }}
                                    </a>
                                @endcan

                                <a href="{{ route('flats.statement', $flat) }}"
                                   @click="isOpen = false"
                                   class="text-xs text-slate-500 hover:text-slate-800">
                                    {{ __('reports.statement') }}
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if (count($shortcuts) > 0)
                <div class="mb-1 mt-3 px-3 pt-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                    {{ __('nav.general') }}
                </div>
                <div class="space-y-1">
                    @foreach ($shortcuts as $shortcut)
                        <a href="{{ $shortcut['route'] }}"
                           @click="isOpen = false"
                           class="flex items-center justify-between rounded-lg p-2.5 text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition-colors">
                            <div class="flex items-center gap-3">
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-500">
                                    {{ $shortcut['category'] }}
                                </span>
                                <div>
                                    <p class="text-sm font-medium">{{ $shortcut['title'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $shortcut['description'] }}</p>
                                </div>
                            </div>
                            <span class="text-xs text-slate-400">↵</span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($flats->isEmpty() && count($shortcuts) === 0 && $query !== '')
                <div class="py-8 text-center text-sm text-slate-400">
                    {{ __('billing.no_flats') }} "{{ $query }}"
                </div>
            @endif
        </div>

        <!-- Footer Shortcuts Help -->
        <div class="flex items-center justify-between bg-slate-50 px-4 py-2 text-xs text-slate-400">
            <span>{{ __('dashboard.overview') }}</span>
            <div class="flex items-center gap-2">
                <span><kbd class="rounded border border-slate-200 bg-white px-1 font-mono">ESC</kbd> {{ __('masters.cancel') }}</span>
                <span><kbd class="rounded border border-slate-200 bg-white px-1 font-mono">⌘K</kbd> Toggle</span>
            </div>
        </div>
    </div>
</div>
