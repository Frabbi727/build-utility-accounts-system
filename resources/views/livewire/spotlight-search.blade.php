<div x-data="{
        isOpen: @entangle('isOpen'),
        selectedIndex: 0,
        init() {
            this.$watch('isOpen', value => {
                if (value) {
                    this.selectedIndex = 0;
                    $nextTick(() => $refs.searchInput?.focus());
                }
            });
            this.$watch('$wire.query', () => {
                this.selectedIndex = 0;
            });
        },
        getItems() {
            return $refs.resultsList ? Array.from($refs.resultsList.querySelectorAll('[data-result-index]')) : [];
        },
        navigateNext() {
            const items = this.getItems();
            if (items.length === 0) return;
            this.selectedIndex = (this.selectedIndex + 1) % items.length;
            this.scrollToSelected();
        },
        navigatePrev() {
            const items = this.getItems();
            if (items.length === 0) return;
            this.selectedIndex = (this.selectedIndex - 1 + items.length) % items.length;
            this.scrollToSelected();
        },
        scrollToSelected() {
            $nextTick(() => {
                const el = $refs.resultsList?.querySelector(`[data-result-index='${this.selectedIndex}']`);
                if (el) el.scrollIntoView({ block: 'nearest' });
            });
        },
        selectCurrent() {
            const el = $refs.resultsList?.querySelector(`[data-result-index='${this.selectedIndex}'] [data-primary-link]`);
            if (el) {
                el.click();
            } else {
                const link = $refs.resultsList?.querySelector(`[data-result-index='${this.selectedIndex}']`);
                if (link && link.tagName === 'A') link.click();
            }
        }
    }"
    x-on:open-spotlight.window="isOpen = true; selectedIndex = 0; $nextTick(() => $refs.searchInput?.focus())"
    @keydown.window.cmd.k.prevent="isOpen = !isOpen; if (isOpen) { selectedIndex = 0; $nextTick(() => $refs.searchInput?.focus()) }"
    @keydown.window.ctrl.k.prevent="isOpen = !isOpen; if (isOpen) { selectedIndex = 0; $nextTick(() => $refs.searchInput?.focus()) }"
    @keydown.window.escape="isOpen = false"
    x-show="isOpen"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto p-4 sm:p-6 md:p-20"
    role="dialog"
    aria-modal="true"
    aria-label="Spotlight Search">

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
            <x-ui.icon name="search" class="pointer-events-none h-5 w-5 text-slate-400 shrink-0" />
            <input type="text"
                   x-ref="searchInput"
                   wire:model.live.debounce.200ms="query"
                   @input="selectedIndex = 0"
                   @keydown.down.prevent="navigateNext()"
                   @keydown.up.prevent="navigatePrev()"
                   @keydown.enter.prevent="selectCurrent()"
                   role="combobox"
                   :aria-expanded="isOpen"
                   aria-autocomplete="list"
                   aria-controls="spotlight-results"
                   placeholder="{{ __('billing.search_flat') }} ({{ __('masters.flat_number') }}, {{ __('billing.owner') }}, {{ __('masters.phone') }})..."
                   class="h-12 w-full border-0 bg-transparent pl-3 pr-10 text-sm text-slate-900 placeholder-slate-400 focus:ring-0 focus:outline-hidden">

            <!-- Loading Spinner -->
            <div wire:loading wire:target="query" class="flex items-center pr-2">
                <svg class="animate-spin h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
            </div>

            <!-- Clear Button -->
            @if ($query !== '')
                <button type="button"
                        wire:click="clear"
                        class="p-1 rounded text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                        aria-label="Clear search">
                    <x-ui.icon name="x" class="w-4 h-4" />
                </button>
            @endif
        </div>

        @if ($errorMessage)
            <div class="m-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 flex items-center gap-2">
                <x-ui.icon name="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0" />
                <span>{{ $errorMessage }}</span>
            </div>
        @endif

        <!-- Search Content Area -->
        <div id="spotlight-results" x-ref="resultsList" role="listbox" class="max-h-96 overflow-y-auto p-2">
            @if ($flats->isNotEmpty())
                <div class="mb-2 px-3 pt-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                    {{ __('nav.flats') }}
                </div>
                <div class="space-y-1">
                    @foreach ($flats as $flat)
                        @php
                            $due = $dues[$flat->id] ?? '0.00';
                            $hasDue = bccomp($due, '0.00', 2) > 0;
                            $reminder = $reminders[$flat->id] ?? null;
                            $resultIndex = $loop->index;
                        @endphp
                        <div data-result-index="{{ $resultIndex }}"
                             role="option"
                             :aria-selected="selectedIndex === {{ $resultIndex }}"
                             :class="selectedIndex === {{ $resultIndex }} ? 'bg-slate-100' : 'hover:bg-slate-50'"
                             @mouseenter="selectedIndex = {{ $resultIndex }}"
                             class="flex items-center justify-between rounded-lg p-2.5 transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-white border border-slate-200 font-bold text-slate-700 text-xs">
                                    {{ $flat->number }}
                                </div>
                                <div class="min-w-0 truncate">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('flats.statement', $flat) }}"
                                           wire:navigate
                                           data-primary-link
                                           @click="isOpen = false"
                                           class="font-medium text-slate-900 hover:underline text-sm truncate">
                                            {{ __('billing.flat') }} {{ $flat->number }}
                                        </a>
                                        @if ($flat->floor)
                                            <span class="text-xs text-slate-400 shrink-0">&bull; {{ $flat->floor->name }}</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-slate-500 truncate">
                                        {{ $flat->owner?->name ?? 'No owner' }}
                                        @if ($flat->owner?->phone)
                                            &bull; {{ $flat->owner->phone }}
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-red-50 text-red-700' => $hasDue,
                                    'bg-emerald-50 text-emerald-700' => ! $hasDue,
                                ])>
                                    <x-money :amount="$due" />
                                </span>

                                @if (auth()->user()?->canManageMoney() && $reminder && $reminder['whatsapp_url'] !== '')
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
                                       wire:navigate
                                       @click="isOpen = false"
                                       class="inline-flex items-center rounded-md border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100">
                                        {{ __('billing.collect') }}
                                    </a>
                                @endcan

                                <a href="{{ route('flats.statement', $flat) }}"
                                   wire:navigate
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
                <div class="mb-2 mt-3 px-3 pt-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                    {{ __('nav.general') }}
                </div>
                <div class="space-y-1">
                    @foreach ($shortcuts as $shortcut)
                        @php
                            $shortcutIndex = $flats->count() + $loop->index;
                        @endphp
                        <a href="{{ $shortcut['route'] }}"
                           wire:navigate
                           data-result-index="{{ $shortcutIndex }}"
                           role="option"
                           :aria-selected="selectedIndex === {{ $shortcutIndex }}"
                           :class="selectedIndex === {{ $shortcutIndex }} ? 'bg-slate-100 text-slate-900' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'"
                           @mouseenter="selectedIndex = {{ $shortcutIndex }}"
                           @click="isOpen = false"
                           class="flex items-center justify-between rounded-lg p-2.5 transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-600">
                                    <x-ui.icon :name="$shortcut['icon']" class="w-4 h-4" />
                                </div>
                                <div class="min-w-0 truncate">
                                    <p class="text-sm font-medium truncate">{{ $shortcut['title'] }}</p>
                                    <p class="text-xs text-slate-400 truncate">{{ $shortcut['category'] }}</p>
                                </div>
                            </div>
                            <span class="text-xs text-slate-400">↵</span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($flats->isEmpty() && count($shortcuts) === 0 && $query !== '')
                <div class="py-12 text-center px-4">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-100 text-slate-400 mb-3">
                        <x-ui.icon name="search" class="w-6 h-6" />
                    </div>
                    <p class="text-sm font-medium text-slate-900 mb-1">
                        {{ __('billing.no_flats') }} "{{ $query }}"
                    </p>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto">
                        Try searching by flat number (e.g. "4B"), owner name, phone number, or page titles like "Billing", "Meters", or "Expenses".
                    </p>
                </div>
            @endif
        </div>

        <!-- Footer Shortcuts Help -->
        <div class="flex items-center justify-between bg-slate-50 px-4 py-2.5 text-xs text-slate-500">
            <div class="flex items-center gap-2">
                <span><kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[11px]">↑↓</kbd> Navigate</span>
                <span><kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[11px]">↵</kbd> Select</span>
            </div>
            <div class="flex items-center gap-2">
                <span><kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[11px]">ESC</kbd> {{ __('masters.cancel') }}</span>
                <span><kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[11px]">⌘K</kbd> Toggle</span>
            </div>
        </div>
    </div>
</div>
