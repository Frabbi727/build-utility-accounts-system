@props(['label' => null])

<label class="inline-flex items-center gap-2 text-sm text-slate-700">
    <input type="checkbox" {{ $attributes->class('h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500') }}>
    @if ($label !== null)<span>{{ $label }}</span>@endif
</label>
