<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    <script>
        if (localStorage.getItem('sidebar_collapsed') === 'true') {
            document.documentElement.classList.add('sidebar-collapsed');
        }

        document.addEventListener('alpine:init', () => {
            Alpine.store('sidebar', {
                collapsed: localStorage.getItem('sidebar_collapsed') === 'true',
                mobileOpen: false,
                toggleCollapse() {
                    this.collapsed = !this.collapsed;
                    localStorage.setItem('sidebar_collapsed', this.collapsed);
                    document.documentElement.classList.toggle('sidebar-collapsed', this.collapsed);
                },
                openMobile() {
                    this.mobileOpen = true;
                },
                closeMobile() {
                    this.mobileOpen = false;
                }
            });
        });

        document.addEventListener('livewire:navigated', () => {
            const isCollapsed = localStorage.getItem('sidebar_collapsed') === 'true';
            document.documentElement.classList.toggle('sidebar-collapsed', isCollapsed);
            if (window.Alpine && Alpine.store('sidebar')) {
                Alpine.store('sidebar').collapsed = isCollapsed;
            }
        });
    </script>
    <style>
        html.sidebar-collapsed aside[data-sidebar] { width: 4.5rem; }
        html:not(.sidebar-collapsed) aside[data-sidebar] { width: 16rem; }
        html.sidebar-collapsed [data-sidebar-expanded] { display: none; }
        html:not(.sidebar-collapsed) [data-sidebar-collapsed] { display: none; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 text-slate-800 antialiased">
<div class="min-h-screen bg-slate-50 text-slate-800 antialiased flex"
     x-data
     @keydown.window.escape="$store.sidebar.closeMobile()"
>
    @auth
        <x-layouts.partials.sidebar />
    @endauth

    <div class="flex-1 flex flex-col min-w-0 min-h-screen">
        <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center justify-between border-b border-slate-200 bg-white/95 backdrop-blur-xs px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                @auth
                    <button type="button"
                            @click="$store.sidebar.openMobile()"
                            class="lg:hidden p-2 text-slate-500 hover:text-slate-900 rounded-md hover:bg-slate-100 cursor-pointer"
                            aria-label="Open mobile menu">
                        <x-ui.icon name="menu" class="w-5 h-5" />
                    </button>
                    <button type="button"
                            @click="$store.sidebar.toggleCollapse()"
                            class="hidden lg:flex p-2 text-slate-500 hover:text-slate-900 rounded-md hover:bg-slate-100 transition-colors cursor-pointer"
                            aria-label="Toggle sidebar collapse">
                        <x-ui.icon name="menu" class="w-5 h-5" />
                    </button>
                @else
                    <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-slate-900">
                        {{ config('app.name') }}
                    </a>
                @endauth
            </div>

            <div class="flex items-center gap-4 text-sm">
                @auth
                    <button type="button"
                            @click="$dispatch('open-spotlight'); window.Livewire?.dispatch('open-spotlight')"
                            class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-500 hover:border-slate-300 hover:bg-white hover:text-slate-700 transition-colors cursor-pointer">
                        <x-ui.icon name="search" class="h-3.5 w-3.5" />
                        <span class="hidden sm:inline">{{ __('billing.search_flat') }}...</span>
                        <kbd class="hidden sm:inline rounded bg-white px-1.5 py-0.5 text-[10px] font-mono border border-slate-200 text-slate-400">⌘K</kbd>
                    </button>
                    <x-layouts.partials.building-switcher />
                @endauth

                <form method="POST" action="{{ route('locale.switch') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ app()->getLocale() === 'bn' ? 'en' : 'bn' }}">
                    <button type="submit" class="text-slate-500 hover:text-slate-900 cursor-pointer">
                        {{ app()->getLocale() === 'bn' ? 'English' : 'বাংলা' }}
                    </button>
                </form>

                @auth
                    <span class="text-slate-500">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-slate-500 hover:text-slate-900 cursor-pointer">{{ __('nav.logout') }}</button>
                    </form>
                @endauth
            </div>
        </header>

        <main class="flex-1 py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full">
            @if (session('status'))
                <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</div>
@auth
    <livewire:spotlight-search />
@endauth
@livewireScripts
</body>
</html>
