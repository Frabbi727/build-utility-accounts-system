{{-- `head` holds <th> cells, the default slot holds <tr> rows. --}}
@props(['head' => null])

<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        @isset($head)
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>{{ $head }}</tr>
            </thead>
        @endisset

        <tbody class="divide-y divide-slate-100">{{ $slot }}</tbody>
    </table>
</div>
