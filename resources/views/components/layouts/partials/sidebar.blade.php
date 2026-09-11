@php
    $menu = app(\App\Support\Navigation::class)->for(auth()->user());
    $currentBuilding = app(\App\Support\CurrentBuilding::class)->get();
@endphp

@persist('main-sidebar')
{{-- Desktop Sidebar --}}
<aside class="hidden lg:flex lg:flex-col shrink-0 transition-all duration-300 ease-in-out border-r border-slate-200 bg-white min-h-screen sticky top-0 h-screen select-none"
       :class="collapsed ? 'w-18' : 'w-64'">
    {{-- Brand Header --}}
    <div class="h-16 flex items-center border-b border-slate-200 shrink-0 px-4"
         :class="collapsed ? 'justify-center px-0' : 'px-4'">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 min-w-0" wire:navigate>
            <x-ui.icon name="building" class="w-6 h-6 text-slate-900 shrink-0" />
            <div x-show="!collapsed" class="min-w-0 flex-1 truncate">
                <span class="font-bold text-slate-900 text-sm truncate block leading-tight">{{ config('app.name') }}</span>
                @if ($currentBuilding)
                    <span class="inline-block text-[11px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-medium truncate max-w-full" title="{{ $currentBuilding->name }}">{{ $currentBuilding->name }}</span>
                @endif
            </div>
        </a>
    </div>

    {{-- Navigation Links / Categories --}}
    <nav class="flex-1 px-3 py-4 space-y-1"
         :class="collapsed ? 'overflow-visible' : 'overflow-y-auto'">
        @foreach ($menu as $entry)
            @if ($entry['url'] !== null)
                {{-- Standalone Item --}}
                <div x-show="!collapsed">
                    <a href="{{ $entry['url'] }}"
                       wire:navigate
                       @class([
                           'flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md transition-colors',
                           'bg-slate-900 text-white font-medium' => $entry['active'],
                           'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $entry['active'],
                       ])>
                        <x-ui.icon :name="$entry['icon']" class="w-5 h-5 shrink-0" />
                        <span class="truncate">{{ $entry['label'] }}</span>
                    </a>
                </div>
                <div x-show="collapsed" class="relative group flex justify-center">
                    <a href="{{ $entry['url'] }}"
                       wire:navigate
                       @class([
                           'flex items-center justify-center p-2 rounded-md transition-colors',
                           'bg-slate-900 text-white font-medium' => $entry['active'],
                           'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $entry['active'],
                       ])>
                        <x-ui.icon :name="$entry['icon']" class="w-5 h-5 shrink-0" />
                        <span class="sr-only">{{ $entry['label'] }}</span>
                    </a>
                    <div class="pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-2 hidden group-hover:block z-50 whitespace-nowrap rounded bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-md">
                        {{ $entry['label'] }}
                    </div>
                </div>
            @else
                {{-- Grouped Category --}}
                <div x-data="{ open: {{ $entry['active'] ? 'true' : 'false' }}, flyoutOpen: false }" class="relative">
                    {{-- Expanded Mode --}}
                    <div x-show="!collapsed">
                        <button type="button"
                                @click="open = !open"
                                @class([
                                    'flex items-center justify-between w-full px-3 py-2 text-sm font-medium rounded-md transition-colors cursor-pointer',
                                    'text-slate-900 font-semibold' => $entry['active'],
                                    'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $entry['active'],
                                ])>
                            <div class="flex items-center gap-3 min-w-0">
                                <x-ui.icon :name="$entry['icon']" class="w-5 h-5 shrink-0" />
                                <span class="truncate">{{ $entry['label'] }}</span>
                            </div>
                            <x-ui.icon name="chevron-right"
                                       class="w-4 h-4 shrink-0 transition-transform duration-200"
                                       ::class="open ? 'rotate-90' : ''" />
                        </button>
                        <div x-show="open" x-transition class="mt-1 space-y-1 pl-8 pr-1">
                            @foreach ($entry['items'] as $item)
                                <a href="{{ $item['url'] }}"
                                   wire:navigate
                                   @class([
                                       'block px-2.5 py-1.5 text-sm rounded-md transition-colors',
                                       'bg-slate-100 text-slate-900 font-semibold' => $item['active'],
                                       'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! $item['active'],
                                   ])>
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    {{-- Collapsed Mode --}}
                    <div x-show="collapsed"
                         class="relative flex justify-center"
                         @mouseenter="if (collapsed) flyoutOpen = true"
                         @mouseleave="if (collapsed) flyoutOpen = false">
                        <button type="button"
                                @class([
                                    'flex items-center justify-center p-2 rounded-md transition-colors cursor-pointer',
                                    'bg-slate-100 text-slate-900 font-semibold' => $entry['active'],
                                    'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $entry['active'],
                                ])>
                            <x-ui.icon :name="$entry['icon']" class="w-5 h-5 shrink-0" />
                            <span class="sr-only">{{ $entry['label'] }}</span>
                        </button>
                        <div x-show="flyoutOpen"
                             x-cloak
                             x-transition
                             class="absolute left-full top-0 ml-2 w-52 bg-white rounded-lg shadow-xl border border-slate-200 p-2 z-50">
                            <div class="px-2 py-1.5 text-xs font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-100 mb-1">
                                {{ $entry['label'] }}
                            </div>
                            <div class="space-y-1">
                                @foreach ($entry['items'] as $item)
                                    <a href="{{ $item['url'] }}"
                                       wire:navigate
                                       @class([
                                           'block px-2.5 py-1.5 text-sm rounded-md transition-colors',
                                           'bg-slate-100 text-slate-900 font-semibold' => $item['active'],
                                           'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! $item['active'],
                                       ])>
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </nav>

    {{-- Sidebar Bottom --}}
    <div class="p-3 border-t border-slate-200 shrink-0">
        <button type="button"
                @click="toggleCollapse()"
                class="flex items-center gap-3 w-full px-3 py-2.5 text-sm font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-900 rounded-md transition-colors cursor-pointer"
                :class="collapsed ? 'justify-center px-0' : ''">
            <div x-show="!collapsed" class="flex items-center gap-3">
                <x-ui.icon name="chevron-left" class="w-5 h-5 shrink-0" />
                <span>Collapse</span>
            </div>
            <div x-show="collapsed">
                <x-ui.icon name="chevron-right" class="w-5 h-5 shrink-0" />
            </div>
        </button>
    </div>
</aside>

{{-- Mobile Drawer --}}
<div class="lg:hidden">
    {{-- Drawer Backdrop --}}
    <div x-show="mobileOpen"
         x-cloak
         x-transition.opacity
         @click="mobileOpen = false"
         class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-xs"></div>

    {{-- Drawer Slide-out Panel --}}
    <div x-show="mobileOpen"
         x-cloak
         x-transition:enter="transition ease-in-out duration-300 transform"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in-out duration-300 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full"
         class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-2xl flex flex-col">
        {{-- Header --}}
        <div class="h-16 flex items-center justify-between border-b border-slate-200 shrink-0 px-4">
            <div class="flex items-center gap-3 min-w-0">
                <x-ui.icon name="building" class="w-6 h-6 text-slate-900 shrink-0" />
                <div class="min-w-0 flex-1 truncate">
                    <span class="font-bold text-slate-900 text-sm truncate block leading-tight">{{ config('app.name') }}</span>
                    @if ($currentBuilding)
                        <span class="inline-block text-[11px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-medium truncate max-w-full" title="{{ $currentBuilding->name }}">{{ $currentBuilding->name }}</span>
                    @endif
                </div>
            </div>
            <button type="button"
                    @click="mobileOpen = false"
                    class="p-2 text-slate-500 hover:text-slate-900 rounded-md hover:bg-slate-100 cursor-pointer"
                    aria-label="Close mobile menu">
                <x-ui.icon name="x" class="w-5 h-5" />
            </button>
        </div>

        {{-- Navigation Links --}}
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            @foreach ($menu as $entry)
                @if ($entry['url'] !== null)
                    <a href="{{ $entry['url'] }}"
                       wire:navigate
                       @click="mobileOpen = false"
                       @class([
                           'flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md transition-colors',
                           'bg-slate-900 text-white font-medium' => $entry['active'],
                           'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $entry['active'],
                       ])>
                        <x-ui.icon :name="$entry['icon']" class="w-5 h-5 shrink-0" />
                        <span class="truncate">{{ $entry['label'] }}</span>
                    </a>
                @else
                    <div x-data="{ open: {{ $entry['active'] ? 'true' : 'false' }} }">
                        <button type="button"
                                @click="open = !open"
                                @class([
                                    'flex items-center justify-between w-full px-3 py-2 text-sm font-medium rounded-md transition-colors cursor-pointer',
                                    'text-slate-900 font-semibold' => $entry['active'],
                                    'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $entry['active'],
                                ])>
                            <div class="flex items-center gap-3 min-w-0">
                                <x-ui.icon :name="$entry['icon']" class="w-5 h-5 shrink-0" />
                                <span class="truncate">{{ $entry['label'] }}</span>
                            </div>
                            <x-ui.icon name="chevron-right"
                                       class="w-4 h-4 shrink-0 transition-transform duration-200"
                                       ::class="open ? 'rotate-90' : ''" />
                        </button>
                        <div x-show="open" x-transition class="mt-1 space-y-1 pl-8 pr-1">
                            @foreach ($entry['items'] as $item)
                                <a href="{{ $item['url'] }}"
                                   wire:navigate
                                   @click="mobileOpen = false"
                                   @class([
                                       'block px-2.5 py-1.5 text-sm rounded-md transition-colors',
                                       'bg-slate-100 text-slate-900 font-semibold' => $item['active'],
                                       'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! $item['active'],
                                   ])>
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </nav>
    </div>
</div>
@endpersist
