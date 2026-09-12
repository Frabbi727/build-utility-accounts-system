<div class="space-y-6">
    <x-ui.page-header :title="__('Audit Logs')" :description="__('Centralized system-wide audit trail for financial, security, and administrative operations.')" />

    {{-- Filter Bar --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('Search') }}</label>
                <input type="search" wire:model.live.debounce.300ms="search"
                       placeholder="{{ __('Search description, ID, user...') }}"
                       class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('Module') }}</label>
                <select wire:model.live="module" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('All Modules') }}</option>
                    @foreach ($modules as $m)
                        <option value="{{ $m }}">{{ ucfirst(str_replace('_', ' ', $m)) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('Action') }}</label>
                <select wire:model.live="action" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('All Actions') }}</option>
                    @foreach ($actions as $a)
                        <option value="{{ $a }}">{{ $a }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('Source / Platform') }}</label>
                <select wire:model.live="source" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('All Sources') }}</option>
                    @foreach ($sources as $s)
                        <option value="{{ $s }}">{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('User') }}</label>
                <select wire:model.live="userId" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('All Users') }}</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('Date From') }}</label>
                <input type="date" wire:model.live="dateFrom"
                       class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('Date To') }}</label>
                <input type="date" wire:model.live="dateTo"
                       class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div class="flex items-end gap-2">
                <button type="button" wire:click="resetFilters"
                        class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 shadow-xs">
                    {{ __('Reset Filters') }}
                </button>
            </div>
        </div>

        @if ($requestId !== '')
            <div class="mt-4 flex items-center justify-between rounded-lg bg-indigo-50 px-4 py-2 text-sm text-indigo-900 border border-indigo-200">
                <div class="flex items-center gap-2">
                    <span class="font-semibold">Correlation Filter:</span>
                    <span class="font-mono text-xs">{{ $requestId }}</span>
                </div>
                <button type="button" wire:click="$set('requestId', '')" class="text-xs font-semibold text-indigo-700 hover:underline">
                    ✕ Clear Correlation Filter
                </button>
            </div>
        @endif
    </div>

    {{-- Audit Log Table --}}
    <x-ui.table>
        <x-slot:head>
            <th class="px-4 py-3">{{ __('Timestamp (Dhaka)') }}</th>
            <th class="px-4 py-3">{{ __('User') }}</th>
            <th class="px-4 py-3">{{ __('Module / Action') }}</th>
            <th class="px-4 py-3">{{ __('Entity') }}</th>
            <th class="px-4 py-3">{{ __('Description') }}</th>
            <th class="px-4 py-3">{{ __('Source') }}</th>
            <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
        </x-slot:head>

        @forelse ($logs as $log)
            <tr wire:key="log-{{ $log->id }}" class="hover:bg-slate-50/50">
                <td class="px-4 py-3 whitespace-nowrap text-xs text-slate-600 font-mono">
                    {{ $log->formattedCreatedAt() }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm">
                    @if ($log->user)
                        <div class="font-medium text-slate-900">{{ $log->user->name }}</div>
                        <div class="text-xs text-slate-500">{{ $log->user->email }}</div>
                    @else
                        <span class="text-xs font-medium text-slate-400">System / Guest</span>
                    @endif
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-xs">
                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700 uppercase">
                        {{ $log->module ?? 'System' }}
                    </span>
                    <span @class([
                        'ml-1 inline-flex items-center rounded-md px-2 py-0.5 text-xs font-bold uppercase',
                        'bg-emerald-100 text-emerald-800' => in_array($log->action, ['CREATE', 'POST', 'LOGIN', 'APPROVE']),
                        'bg-blue-100 text-blue-800' => in_array($log->action, ['UPDATE', 'PASSWORD_CHANGE']),
                        'bg-red-100 text-red-800' => in_array($log->action, ['DELETE', 'FAILED_LOGIN', 'REJECT', 'REVERSE']),
                        'bg-amber-100 text-amber-800' => in_array($log->action, ['LOGOUT', 'LOCK', 'UNLOCK']),
                        'bg-slate-100 text-slate-700' => !in_array($log->action, ['CREATE', 'POST', 'LOGIN', 'APPROVE', 'UPDATE', 'PASSWORD_CHANGE', 'DELETE', 'FAILED_LOGIN', 'REJECT', 'REVERSE', 'LOGOUT', 'LOCK', 'UNLOCK']),
                    ])>
                        {{ $log->action }}
                    </span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-xs">
                    @if ($log->entity_type || $log->subject_type)
                        <button type="button"
                                wire:click="viewEntityHistory('{{ addslashes($log->entity_type ?? $log->subject_type) }}', '{{ $log->entity_id ?? $log->subject_id }}')"
                                class="inline-flex items-center gap-1 font-semibold text-indigo-600 hover:text-indigo-900 hover:underline">
                            🔍 {{ $log->entityDisplay() }}
                        </button>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-xs text-slate-700 max-w-xs truncate" title="{{ $log->description }}">
                    {{ $log->description ?? '—' }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-xs">
                    @php $src = $log->source ?? $log->platform ?? 'web'; @endphp
                    <span @class([
                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-emerald-50 text-emerald-700 border border-emerald-200' => str_contains($src, 'flutter'),
                        'bg-sky-50 text-sky-700 border border-sky-200' => str_contains($src, 'web'),
                        'bg-slate-100 text-slate-700' => !str_contains($src, 'flutter') && !str_contains($src, 'web'),
                    ])>
                        {{ ucfirst(str_replace('_', ' ', $src)) }}
                    </span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-right text-xs">
                    <button type="button" wire:click="openDetailModal({{ $log->id }})"
                            class="rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                        {{ __('Details') }}
                    </button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-400">
                    {{ __('No audit log records found matching the criteria.') }}
                </td>
            </tr>
        @endforelse
    </x-ui.table>

    <div>{{ $logs->links() }}</div>

    {{-- Entity Chronological History Modal --}}
    @if ($showHistoryModal)
        <x-ui.modal :title="'Chronological History: ' . class_basename($historyEntityType) . ' #' . $historyEntityId" maxWidth="3xl" zIndex="z-40" closeAction="closeHistoryModal">
            <div class="space-y-4 px-6 py-5 text-sm max-h-[70vh] overflow-y-auto">
                <p class="text-xs text-slate-500">
                    Showing complete lifecycle events for <span class="font-bold text-slate-800">{{ class_basename($historyEntityType) }} #{{ $historyEntityId }}</span> in chronological order.
                </p>

                @if ($historyLogs->isEmpty())
                    <p class="text-sm text-slate-400 py-6 text-center">No audit history found for this entity.</p>
                @else
                    <div class="relative border-l-2 border-indigo-200 ml-4 space-y-6 py-2">
                        @foreach ($historyLogs as $hLog)
                            <div class="relative pl-6">
                                <span class="absolute -left-[9px] top-1.5 h-4 w-4 rounded-full border-2 border-white bg-indigo-600"></span>
                                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs">
                                    <div class="flex items-center justify-between gap-2 text-xs">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold uppercase text-slate-900">{{ $hLog->action }}</span>
                                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-600 font-mono text-[11px]">{{ $hLog->source ?? 'web' }}</span>
                                        </div>
                                        <span class="font-mono text-slate-500">{{ $hLog->formattedCreatedAt() }}</span>
                                    </div>

                                    <p class="mt-2 text-xs text-slate-800">{{ $hLog->description }}</p>

                                    <div class="mt-3 text-[11px] text-slate-500 flex items-center justify-between pt-2 border-t border-slate-100">
                                        <span>By: <strong class="text-slate-700">{{ $hLog->user?->name ?? 'System' }}</strong></span>
                                        <button type="button" wire:click="openDetailModal({{ $hLog->id }})"
                                                class="inline-flex items-center gap-1 rounded bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                                            {{ __('View Snapshot') }} →
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-6 py-3">
                <x-ui.button variant="secondary" wire:click="closeHistoryModal">{{ __('Close') }}</x-ui.button>
            </div>
        </x-ui.modal>
    @endif

    {{-- Audit Record Detail / Snapshot Modal --}}
    @if ($showDetailModal && $viewingLog)
        @php
            $renderSnapshotValue = function ($val) {
                if ($val === null) {
                    return '<span class="italic text-slate-400 text-xs font-sans">null</span>';
                }
                if (is_bool($val)) {
                    return $val
                        ? '<span class="inline-flex items-center rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-semibold text-emerald-800">true</span>'
                        : '<span class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-700">false</span>';
                }
                if (is_array($val)) {
                    return '<pre class="text-xs font-mono text-slate-800 bg-slate-50 p-2 rounded border border-slate-200 overflow-x-auto whitespace-pre-wrap break-all">' . e(json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '</pre>';
                }
                if ($val === '') {
                    return '<span class="italic text-slate-400 text-xs font-sans">(empty string)</span>';
                }
                return '<span class="break-all whitespace-pre-wrap font-mono text-xs">' . e((string) $val) . '</span>';
            };

            $hasDiff = !empty($viewingLog->old_values) && !empty($viewingLog->new_values);
            $hasNewOnly = !empty($viewingLog->new_values) && empty($viewingLog->old_values);
            $hasOldOnly = !empty($viewingLog->old_values) && empty($viewingLog->new_values);
            $comparisonFields = $hasDiff ? $viewingLog->getComparisonFields() : [];
        @endphp

        <x-ui.modal :title="__('Audit Record #').$viewingLog->id" maxWidth="4xl" zIndex="z-50" closeAction="closeDetailModal">
            <div class="space-y-6 px-6 py-5 text-sm max-h-[75vh] overflow-y-auto">
                @if ($showHistoryModal)
                    <div class="flex items-center justify-between bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-2 text-xs text-indigo-900">
                        <span class="font-medium">{{ __('Viewing snapshot from entity chronological history.') }}</span>
                        <button type="button" wire:click="closeDetailModal" class="font-semibold text-indigo-700 hover:underline">
                            &larr; {{ __('Back to History Timeline') }}
                        </button>
                    </div>
                @endif

                {{-- Metadata Grid --}}
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 rounded-lg bg-slate-50 p-4 border border-slate-200 text-xs">
                    <div>
                        <span class="block font-semibold text-slate-500 uppercase">{{ __('User') }}</span>
                        <span class="text-slate-900 font-medium">{{ $viewingLog->user?->name ?? 'System / Guest' }}</span>
                        @if ($viewingLog->user)
                            <span class="block text-slate-500">{{ $viewingLog->user->email }}</span>
                        @endif
                    </div>
                    <div>
                        <span class="block font-semibold text-slate-500 uppercase">{{ __('Action / Module') }}</span>
                        <span class="font-bold text-slate-900">{{ $viewingLog->action }}</span>
                        <span class="text-slate-500">({{ $viewingLog->module ?? 'System' }})</span>
                    </div>
                    <div>
                        <span class="block font-semibold text-slate-500 uppercase">{{ __('Entity') }}</span>
                        <span class="font-medium text-slate-900">{{ $viewingLog->entityDisplay() }}</span>
                    </div>
                    <div>
                        <span class="block font-semibold text-slate-500 uppercase">{{ __('Date & Time (Dhaka)') }}</span>
                        <span class="font-mono text-slate-900 font-medium">{{ $viewingLog->formattedCreatedAt() }} (BST)</span>
                    </div>
                    <div>
                        <span class="block font-semibold text-slate-500 uppercase">{{ __('Source / Platform') }}</span>
                        <span class="font-medium text-slate-900">{{ $viewingLog->source ?? 'web' }} ({{ $viewingLog->platform ?? 'browser' }})</span>
                        @if ($viewingLog->app_version)
                            <span class="block text-slate-500">v{{ $viewingLog->app_version }}</span>
                        @endif
                    </div>
                    <div>
                        <span class="block font-semibold text-slate-500 uppercase">{{ __('IP Address') }}</span>
                        <span class="font-mono text-slate-900">{{ $viewingLog->ip_address ?? '—' }}</span>
                    </div>
                    <div class="col-span-2 sm:col-span-3">
                        <span class="block font-semibold text-slate-500 uppercase">{{ __('Request / Correlation ID') }}</span>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="font-mono text-slate-800">{{ $viewingLog->request_id ?? '—' }}</span>
                            @if ($viewingLog->request_id)
                                <button type="button" wire:click="filterByRequestId('{{ $viewingLog->request_id }}')"
                                        class="text-indigo-600 hover:underline font-medium text-xs">
                                    [Filter by this request]
                                </button>
                            @endif
                        </div>
                    </div>
                    @if ($viewingLog->route)
                        <div class="col-span-2 sm:col-span-3">
                            <span class="block font-semibold text-slate-500 uppercase">{{ __('Route / Method') }}</span>
                            <span class="font-mono text-slate-800">{{ $viewingLog->http_method }} {{ $viewingLog->route }}</span>
                        </div>
                    @endif
                </div>

                {{-- Before / After Comparison Table --}}
                @if ($hasDiff)
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-bold text-slate-900">{{ __('State Snapshot & Field Changes (Before vs After)') }}</h4>
                            <span class="text-xs text-slate-500">{{ count($comparisonFields) }} {{ __('field(s) tracked') }}</span>
                        </div>
                        <div class="overflow-hidden rounded-lg border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-100 font-semibold text-slate-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left w-1/4">{{ __('Field') }}</th>
                                        <th class="px-3 py-2 text-left w-3/8 text-red-700 bg-red-50/50">{{ __('Before (Old Value)') }}</th>
                                        <th class="px-3 py-2 text-left w-3/8 text-emerald-700 bg-emerald-50/50">{{ __('After (New Value)') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white">
                                    @foreach ($comparisonFields as $field)
                                        @php
                                            $old = $viewingLog->old_values[$field] ?? null;
                                            $new = $viewingLog->new_values[$field] ?? null;
                                            $isChanged = $old !== $new;
                                        @endphp
                                        <tr @class(['bg-slate-50/30' => !$isChanged])>
                                            <td class="px-3 py-2 font-sans font-semibold text-slate-900 bg-slate-50/50 align-top">
                                                <span>{{ str_replace('_', ' ', ucfirst($field)) }}</span>
                                                @if ($isChanged)
                                                    <span class="ml-1 inline-flex items-center rounded bg-amber-100 px-1 py-0.2 text-[10px] font-semibold text-amber-800 uppercase">changed</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-red-700 bg-red-50/30 align-top">
                                                {!! $renderSnapshotValue($old) !!}
                                            </td>
                                            <td class="px-3 py-2 text-emerald-700 bg-emerald-50/30 font-semibold align-top">
                                                {!! $renderSnapshotValue($new) !!}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif ($hasNewOnly)
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-bold text-slate-900">{{ __('Created / Recorded State Snapshot') }}</h4>
                            <span class="text-xs text-slate-500">{{ count($viewingLog->new_values) }} {{ __('field(s)') }}</span>
                        </div>
                        <div class="overflow-hidden rounded-lg border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-100 font-semibold text-slate-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left w-1/3">{{ __('Field') }}</th>
                                        <th class="px-3 py-2 text-left w-2/3 text-emerald-700">{{ __('Value') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white">
                                    @foreach ($viewingLog->new_values as $key => $val)
                                        <tr>
                                            <td class="px-3 py-2 font-sans font-semibold text-slate-900 bg-slate-50/50 align-top">
                                                {{ str_replace('_', ' ', ucfirst($key)) }}
                                            </td>
                                            <td class="px-3 py-2 text-emerald-800 align-top">
                                                {!! $renderSnapshotValue($val) !!}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif ($hasOldOnly)
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-bold text-slate-900">{{ __('Deleted / Previous State Snapshot') }}</h4>
                            <span class="text-xs text-slate-500">{{ count($viewingLog->old_values) }} {{ __('field(s)') }}</span>
                        </div>
                        <div class="overflow-hidden rounded-lg border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-100 font-semibold text-slate-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left w-1/3">{{ __('Field') }}</th>
                                        <th class="px-3 py-2 text-left w-2/3 text-red-700">{{ __('Deleted Value') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white">
                                    @foreach ($viewingLog->old_values as $key => $val)
                                        <tr>
                                            <td class="px-3 py-2 font-sans font-semibold text-slate-900 bg-slate-50/50 align-top">
                                                {{ str_replace('_', ' ', ucfirst($key)) }}
                                            </td>
                                            <td class="px-3 py-2 text-red-800 align-top">
                                                {!! $renderSnapshotValue($val) !!}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif (empty($viewingLog->payload))
                    <div class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-xs text-slate-500">
                        <p class="font-medium text-slate-700">{{ __('No state snapshot recorded for this audit entry.') }}</p>
                        <p class="mt-1 text-slate-400">{{ __('This entry recorded an event action without model state diffs.') }}</p>
                    </div>
                @endif

                {{-- Payload Details if Present --}}
                @if (!empty($viewingLog->payload))
                    <div>
                        <h4 class="text-xs font-bold uppercase text-slate-600 mb-2">{{ __('Technical Payload / Extra Details') }}</h4>
                        <pre class="rounded-lg bg-slate-900 p-3 text-xs text-emerald-400 font-mono overflow-x-auto">{{ json_encode($viewingLog->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                @if ($showHistoryModal)
                    <x-ui.button variant="secondary" wire:click="closeDetailModal">
                        &larr; {{ __('Back to History') }}
                    </x-ui.button>
                @endif
                <x-ui.button variant="secondary" wire:click="closeDetailModal">{{ __('Close') }}</x-ui.button>
            </div>
        </x-ui.modal>
    @endif
</div>
