@props(['options' => [], 'placeholder' => null])

<select {{ $attributes->class('block w-full') }}>
    @if ($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif

    @foreach ($options as $value => $label)
        <option value="{{ $value }}">{{ $label }}</option>
    @endforeach

    {{ $slot }}
</select>
