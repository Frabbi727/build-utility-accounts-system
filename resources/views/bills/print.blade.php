<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .sheet { box-shadow: none; margin: 0 auto; max-width: none; }
        }
    </style>
</head>
<body class="bg-slate-100 py-8 text-slate-900">
    <div class="no-print mx-auto mb-4 flex max-w-2xl justify-end gap-2 px-6">
        <button type="button" onclick="window.print()"
                class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">
            {{ __('billing.print') }}
        </button>
    </div>

    @forelse ($summaries as $summary)
        <x-bill-sheet :summary="$summary" />
    @empty
        <p class="mx-auto max-w-2xl bg-white p-8 text-center text-sm text-slate-500 shadow">
            {{ __('billing.no_bills_for_month') }}
        </p>
    @endforelse
</body>
</html>
