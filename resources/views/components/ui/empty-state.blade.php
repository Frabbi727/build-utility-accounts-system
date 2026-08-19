@props(['title', 'description' => null])

<div class="rounded-lg border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
    <p class="text-sm font-medium text-slate-900">{{ $title }}</p>

    @if ($description !== null)
        <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">{{ $description }}</p>
    @endif

    @isset($actions)
        <div class="mt-4 flex items-center justify-center gap-2">{{ $actions }}</div>
    @endisset
</div>
