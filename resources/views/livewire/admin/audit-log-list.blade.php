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
            <th class="px-4 py-3">{{ __('Timestamp') }}</th>
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
                    {{ $log->created_at?->format('d M Y, H:i:s') }}
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

    {{-- Audit Record Detail Modal --}}
    @if ($showDetailModal && $viewingLog)
        <x-ui.modal :title="__('Audit Record #').$viewingLog->id">
            <div class="space-y-6 px-6 py-5 text-sm">
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
                        <span class="block font-semibold text-slate-500 uppercase">{{ __('Date & Time') }}</span>
                        <span class="font-mono text-slate-900">{{ $viewingLog->created_at?->format('d M Y, H:i:s') }}</span>
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
                                        class="text-indigo-600 hover:underline font-medium">
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
                @if ($viewingLog->action === 'UPDATE' && !empty($viewingLog->changed_fields))
                    <div>
                        <h4 class="text-sm font-bold text-slate-900 mb-3">{{ __('Field Changes (Before vs After)') }}</h4>
                        <div class="overflow-hidden rounded-lg border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-100 font-semibold text-slate-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left w-1/4">{{ __('Field') }}</th>
                                        <th class="px-3 py-2 text-left w-3/8 text-red-700 bg-red-50/50">{{ __('Before (Old Value)') }}</th>
                                        <th class="px-3 py-2 text-left w-3/8 text-emerald-700 bg-emerald-50/50">{{ __('After (New Value)') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white font-mono">
                                    @foreach ($viewingLog->changed_fields as $field)
                                        @php
                                            $old = $viewingLog->old_values[$field] ?? null;
                                            $new = $viewingLog->new_values[$field] ?? null;
                                            $oldStr = is_array($old) ? json_encode($old) : (is_bool($old) ? ($old ? 'true' : 'false') : (string)($old ?? 'null'));
                                            $newStr = is_array($new) ? json_encode($new) : (is_bool($new) ? ($new ? 'true' : 'false') : (string)($new ?? 'null'));
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-2 font-sans font-semibold text-slate-900 bg-slate-50/50">
                                                {{ str_replace('_', ' ', ucfirst($field)) }}
                                            </td>
                                            <td class="px-3 py-2 text-red-700 bg-red-50/30 whitespace-pre-wrap break-all">
                                                {{ $oldStr }}
                                            </td>
                                            <td class="px-3 py-2 text-emerald-700 bg-emerald-50/30 font-semibold whitespace-pre-wrap break-all">
                                                {{ $newStr }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif ($viewingLog->action === 'CREATE' && !empty($viewingLog->new_values))
                    <div>
                        <h4 class="text-sm font-bold text-slate-900 mb-3">{{ __('Created Values') }}</h4>
                        <div class="overflow-hidden rounded-lg border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-100 font-semibold text-slate-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left w-1/3">{{ __('Field') }}</th>
                                        <th class="px-3 py-2 text-left w-2/3 text-emerald-700">{{ __('Value') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white font-mono">
                                    @foreach ($viewingLog->new_values as $key => $val)
                                        <tr>
                                            <td class="px-3 py-2 font-sans font-semibold text-slate-900 bg-slate-50/50">
                                                {{ str_replace('_', ' ', ucfirst($key)) }}
                                            </td>
                                            <td class="px-3 py-2 text-emerald-800 whitespace-pre-wrap break-all">
                                                {{ is_array($val) ? json_encode($val) : (is_bool($val) ? ($val ? 'true' : 'false') : (string)($val ?? 'null')) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif ($viewingLog->action === 'DELETE' && !empty($viewingLog->old_values))
                    <div>
                        <h4 class="text-sm font-bold text-slate-900 mb-3">{{ __('Deleted Record Snapshot') }}</h4>
                        <div class="overflow-hidden rounded-lg border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-100 font-semibold text-slate-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left w-1/3">{{ __('Field') }}</th>
                                        <th class="px-3 py-2 text-left w-2/3 text-red-700">{{ __('Deleted Value') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white font-mono">
                                    @foreach ($viewingLog->old_values as $key => $val)
                                        <tr>
                                            <td class="px-3 py-2 font-sans font-semibold text-slate-900 bg-slate-50/50">
                                                {{ str_replace('_', ' ', ucfirst($key)) }}
                                            </td>
                                            <td class="px-3 py-2 text-red-800 whitespace-pre-wrap break-all">
                                                {{ is_array($val) ? json_encode($val) : (is_bool($val) ? ($val ? 'true' : 'false') : (string)($val ?? 'null')) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Payload Details if Present --}}
                @if (!empty($viewingLog->payload))
                    <div>
                        <h4 class="text-xs font-bold uppercase text-slate-600 mb-2">{{ __('Technical Payload / Extra Details') }}</h4>
                        <pre class="rounded-lg bg-slate-900 p-3 text-xs text-emerald-400 font-mono overflow-x-auto">{{ json_encode($viewingLog->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                <x-ui.button variant="secondary" wire:click="closeDetailModal">{{ __('Close') }}</x-ui.button>
            </div>
        </x-ui.modal>
    @endif

    {{-- Entity Chronological History Modal --}}
    @if ($showHistoryModal)
        <x-ui.modal :title="'Chronological History: ' . class_basename($historyEntityType) . ' #' . $historyEntityId">
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
                                        <span class="font-mono text-slate-500">{{ $hLog->created_at?->format('d M Y, H:i:s') }}</span>
                                    </div>

                                    <p class="mt-2 text-xs text-slate-800">{{ $hLog->description }}</p>

                                    <div class="mt-2 text-[11px] text-slate-500 flex items-center justify-between">
                                        <span>By: <strong>{{ $hLog->user?->name ?? 'System' }}</strong></span>
                                        <button type="button" wire:click="openDetailModal({{ $hLog->id }})" class="text-indigo-600 hover:underline font-semibold">
                                            View Snapshot →
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
</div>
