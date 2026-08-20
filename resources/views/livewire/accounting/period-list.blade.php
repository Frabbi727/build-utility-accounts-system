<div class="space-y-6">
    <x-ui.page-header :title="__('accounting.periods')" :description="__('accounting.periods_help')" />

    <x-ui.notice :message="$notice" :type="$noticeType" />

    @if ($periods->isEmpty())
        <x-ui.empty-state :title="__('accounting.no_periods')" :description="__('accounting.no_periods_help')" />
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-2">{{ __('accounting.period') }}</th>
                <th class="px-4 py-2 text-right">{{ __('accounting.entries') }}</th>
                <th class="px-4 py-2">{{ __('masters.status') }}</th>
                <th class="px-4 py-2">{{ __('accounting.locked_at') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
            </x-slot:head>

            @foreach ($periods as $period)
                <tr>
                    <td class="px-4 py-2 font-medium text-slate-900">
                        {{ \Illuminate\Support\Carbon::create($period->year, $period->month)->format('F Y') }}
                    </td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $period->journal_entries_count }}</td>
                    <td class="px-4 py-2">
                        <x-ui.badge :variant="$period->isLocked() ? 'warning' : 'success'">
                            {{ $period->isLocked() ? __('accounting.locked') : __('accounting.open') }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 py-2 text-slate-600">{{ $period->locked_at?->toDayDateTimeString() ?? '—' }}</td>
                    <td class="px-4 py-2 text-right">
                        @can('update', $period)
                            @if ($period->isLocked())
                                <button type="button" wire:click="askConfirm('unlock', {{ $period->id }})"
                                        class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('accounting.unlock') }}</button>
                            @else
                                <button type="button" wire:click="askConfirm('lock', {{ $period->id }})"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900">{{ __('accounting.lock') }}</button>
                            @endif
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    @php($pending = $this->pendingPeriod())

    @if ($pending !== null)
        <x-ui.confirm-dialog :title="$pending['title']" :message="$pending['message']">
            <div class="space-y-1">
                <div>{{ __('accounting.period') }}: <span class="font-medium">{{ $pending['period'] }}</span></div>
                <div>{{ __('accounting.entries') }}: <span class="font-medium tabular-nums">{{ $pending['entries'] }}</span></div>
            </div>
        </x-ui.confirm-dialog>
    @endif
</div>
