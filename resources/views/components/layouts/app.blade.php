<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 text-slate-800 antialiased">
<div class="min-h-full">
    <nav class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex flex-wrap items-center gap-4">
                <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-slate-900">
                    {{ config('app.name') }}
                </a>

                @auth
                    <x-layouts.partials.nav />
                @endauth
            </div>

            <div class="flex items-center gap-4 text-sm">
                @auth
                    <button type="button"
                            wire:click="$dispatch('open-spotlight')"
                            class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-500 hover:border-slate-300 hover:bg-white hover:text-slate-700 transition-colors">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ __('billing.search_flat') }}...</span>
                        <kbd class="hidden sm:inline rounded bg-white px-1 py-0.5 text-[10px] font-mono border border-slate-200 text-slate-400">⌘K</kbd>
                    </button>
                    <x-layouts.partials.building-switcher />
                @endauth

                <form method="POST" action="{{ route('locale.switch') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ app()->getLocale() === 'bn' ? 'en' : 'bn' }}">
                    <button type="submit" class="text-slate-500 hover:text-slate-900">
                        {{ app()->getLocale() === 'bn' ? 'English' : 'বাংলা' }}
                    </button>
                </form>

                @auth
                    <span class="text-slate-500">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-slate-500 hover:text-slate-900">{{ __('nav.logout') }}</button>
                    </form>
                @endauth
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-7xl px-4 py-8">
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
@auth
    <livewire:spotlight-search />
@endauth
@livewireScripts
</body>
</html>
