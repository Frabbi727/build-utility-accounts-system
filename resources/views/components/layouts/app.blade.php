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
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
            <div class="flex items-center gap-6">
                <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-slate-900">
                    {{ config('app.name') }}
                </a>
                @auth
                    <div class="flex items-center gap-4 text-sm">
                        @can('viewAny', App\Models\Flat::class)
                            <a href="{{ route('flats.index') }}" class="text-slate-600 hover:text-slate-900">{{ __('nav.flats') }}</a>
                            <a href="{{ route('accounts.index') }}" class="text-slate-600 hover:text-slate-900">{{ __('nav.accounts') }}</a>
                            <a href="{{ route('expenses.index') }}" class="text-slate-600 hover:text-slate-900">{{ __('nav.expenses') }}</a>
                            <a href="{{ route('vendor-bills.index') }}" class="text-slate-600 hover:text-slate-900">{{ __('nav.vendor_bills') }}</a>
                            <a href="{{ route('billing.generate') }}" class="text-slate-600 hover:text-slate-900">{{ __('nav.generate_bills') }}</a>
                            <a href="{{ route('payments.create') }}" class="text-slate-600 hover:text-slate-900">{{ __('nav.record_payment') }}</a>
                            <a href="{{ route('reports.index') }}" class="text-slate-600 hover:text-slate-900">{{ __('nav.reports') }}</a>
                        @endcan
                    </div>
                @endauth
            </div>
            <div class="flex items-center gap-4 text-sm">
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

        {{ $slot }}
    </main>
</div>
@livewireScripts
</body>
</html>
