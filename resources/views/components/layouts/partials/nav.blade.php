@php
    $menu = app(App\Support\Navigation::class)->for(auth()->user());
@endphp

<div class="flex flex-wrap items-center gap-1 text-sm">
    @foreach ($menu as $entry)
        @if ($entry['url'] !== null)
            <a href="{{ $entry['url'] }}"
               @class([
                   'rounded-md px-2.5 py-1.5 transition',
                   'bg-slate-900 text-white' => $entry['active'],
                   'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $entry['active'],
               ])>{{ $entry['label'] }}</a>
        @else
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" x-on:click="open = ! open"
                        @class([
                            'flex items-center gap-1 rounded-md px-2.5 py-1.5 transition',
                            'bg-slate-900 text-white' => $entry['active'],
                            'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $entry['active'],
                        ])>
                    {{ $entry['label'] }}
                    <span aria-hidden="true" class="text-xs">&#9662;</span>
                </button>

                <div x-show="open" x-cloak x-transition.opacity
                     class="absolute left-0 z-30 mt-1 w-56 rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                    @foreach ($entry['items'] as $item)
                        <a href="{{ $item['url'] }}"
                           @class([
                               'block px-4 py-2 text-sm',
                               'bg-slate-50 font-medium text-slate-900' => $item['active'],
                               'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! $item['active'],
                           ])>{{ $item['label'] }}</a>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>
