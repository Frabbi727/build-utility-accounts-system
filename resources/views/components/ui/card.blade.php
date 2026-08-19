@props(['title' => null])

<div {{ $attributes->class('rounded-lg border border-slate-200 bg-white') }}>
    @if ($title !== null)
        <div class="border-b border-slate-200 px-6 py-3">
            <h2 class="text-sm font-semibold text-slate-900">{{ $title }}</h2>
        </div>
    @endif

    <div class="p-6">{{ $slot }}</div>
</div>
