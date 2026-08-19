@props(['title', 'subtitle' => null])

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-lg font-semibold text-slate-900">{{ $title }}</h1>
        <p class="text-sm text-slate-500">{{ $subtitle ?? __('reports.derived_from_ledger') }}</p>
    </div>
    <div class="flex flex-wrap items-end gap-3 text-sm">
        {{ $slot }}
    </div>
</div>
