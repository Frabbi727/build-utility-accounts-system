@php
    $current = app(App\Support\CurrentBuilding::class)->get();
    $buildings = $current === null ? collect() : App\Models\Building::orderBy('name')->get();
@endphp

@if ($buildings->count() > 1)
    <form method="POST" action="{{ route('buildings.switch') }}" class="flex items-center">
        @csrf
        <select name="building_id" onchange="this.form.submit()" class="py-1 text-xs">
            @foreach ($buildings as $building)
                <option value="{{ $building->id }}" @selected($building->id === $current->id)>
                    {{ app()->getLocale() === 'bn' ? ($building->name_bn ?? $building->name) : $building->name }}
                </option>
            @endforeach
        </select>
    </form>
@elseif ($current !== null)
    <span class="text-xs text-slate-500">
        {{ app()->getLocale() === 'bn' ? ($current->name_bn ?? $current->name) : $current->name }}
    </span>
@endif
