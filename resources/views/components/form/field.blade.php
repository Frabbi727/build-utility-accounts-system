@props(['label' => null, 'name' => null, 'hint' => null, 'required' => false])

<div {{ $attributes->only('class') }}>
    @if ($label !== null)
        <label class="block text-sm font-medium text-slate-700">
            {{ $label }}
            @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif

    <div @class(['mt-1' => $label !== null])>{{ $slot }}</div>

    @if ($hint !== null)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif

    @if ($name !== null)
        @error($name)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    @endif
</div>
