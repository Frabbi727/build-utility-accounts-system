<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <x-ui.page-header :title="__('backup.title')" :description="__('backup.subtitle')" />

        <div class="flex items-center gap-2">
            <button type="button"
                    wire:click="openUploadModal"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                <span>{{ __('backup.upload_backup') }}</span>
            </button>

            <button type="button"
                    wire:click="createManualBackup"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50 transition">
                <span wire:loading.remove wire:target="createManualBackup">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </span>
                <span wire:loading wire:target="createManualBackup">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
                <span wire:loading.remove wire:target="createManualBackup">{{ __('backup.create_backup') }}</span>
                <span wire:loading wire:target="createManualBackup">{{ __('backup.creating') }}</span>
            </button>
        </div>
    </div>

    {{-- Status Flash Alert --}}
    @if ($statusMessage !== '')
        <div @class([
            'rounded-xl p-4 text-sm font-medium border flex items-center justify-between shadow-xs',
            'bg-emerald-50 text-emerald-900 border-emerald-200' => $statusMessageType === 'success',
            'bg-rose-50 text-rose-900 border-rose-200' => $statusMessageType === 'error',
        ])>
            <div class="flex items-center gap-2">
                @if ($statusMessageType === 'success')
                    <span class="text-emerald-600 font-bold">✓</span>
                @else
                    <span class="text-rose-600 font-bold">✗</span>
                @endif
                <span>{{ $statusMessage }}</span>
            </div>
            <button type="button" wire:click="$set('statusMessage', '')" class="text-slate-400 hover:text-slate-700">✕</button>
        </div>
    @endif

    {{-- Summary Metrics --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Latest Backup --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('backup.latest_backup') }}</span>
                <span class="inline-flex rounded-full bg-emerald-50 p-1.5 text-emerald-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
            </div>
            <div class="mt-2">
                @if ($latestBackup)
                    <div class="text-base font-bold text-slate-900">{{ $latestBackup->formattedCreatedAt() }}</div>
                    <div class="mt-1 flex items-center gap-2 text-xs text-slate-500">
                        <span>{{ $latestBackup->formattedSize() }}</span>
                        <span>•</span>
                        <span class="font-mono">{{ substr($latestBackup->checksum ?? '', 0, 8) }}...</span>
                    </div>
                @else
                    <div class="text-sm font-medium text-slate-400">{{ __('backup.no_backups') }}</div>
                @endif
            </div>
        </div>

        {{-- Total Backups --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('backup.total_backups') }}</span>
                <span class="inline-flex rounded-full bg-indigo-50 p-1.5 text-indigo-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7C5 4 4 5 4 7z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2">
                <div class="text-2xl font-bold text-slate-900">{{ $totalCount }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ $completedCount }} completed successfully</div>
            </div>
        </div>

        {{-- Storage Used --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('backup.storage_used') }}</span>
                <span class="inline-flex rounded-full bg-sky-50 p-1.5 text-sky-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </span>
            </div>
            <div class="mt-2">
                @php
                    $bytes = $totalStorageBytes;
                    $units = ['B', 'KB', 'MB', 'GB'];
                    $i = $bytes > 0 ? min((int) floor(log($bytes, 1024)), 3) : 0;
                    $formattedStorage = $bytes > 0 ? round($bytes / pow(1024, $i), 2) . ' ' . $units[$i] : '0 MB';
                @endphp
                <div class="text-2xl font-bold text-slate-900">{{ $formattedStorage }}</div>
                <div class="mt-1 text-xs text-slate-500">Encrypted at rest: {{ config('backup.encryption_enabled') ? 'Yes' : 'No' }}</div>
            </div>
        </div>

        {{-- Scheduled Time --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('backup.scheduled_time') }}</span>
                <span class="inline-flex rounded-full bg-amber-50 p-1.5 text-amber-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2">
                <div class="text-base font-bold text-slate-900">{{ config('backup.scheduled_time', '23:30') }} BST</div>
                <div class="mt-1 text-xs text-slate-500">{{ __('backup.every_night_at') }}</div>
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('Search') }}</label>
                <input type="search" wire:model.live.debounce.300ms="search"
                       placeholder="{{ __('backup.search_placeholder') }}"
                       class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('backup.filter_type') }}</label>
                <select wire:model.live="typeFilter" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('backup.all_types') }}</option>
                    <option value="automatic">Automatic</option>
                    <option value="manual">Manual</option>
                    <option value="safety">Safety</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('backup.filter_status') }}</label>
                <select wire:model.live="statusFilter" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('backup.all_statuses') }}</option>
                    <option value="completed">Completed</option>
                    <option value="running">Running</option>
                    <option value="failed">Failed</option>
                    <option value="restored">Restored</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Backups Table --}}
    <x-ui.table>
        <x-slot:head>
            <th class="px-4 py-3">{{ __('backup.date_time') }}</th>
            <th class="px-4 py-3">{{ __('backup.name') }}</th>
            <th class="px-4 py-3">{{ __('backup.type') }}</th>
            <th class="px-4 py-3">{{ __('backup.size') }}</th>
            <th class="px-4 py-3">{{ __('backup.status') }}</th>
            <th class="px-4 py-3">{{ __('backup.checksum') }}</th>
            <th class="px-4 py-3 text-center">{{ __('backup.protected') }}</th>
            <th class="px-4 py-3 text-right">{{ __('backup.actions') }}</th>
        </x-slot:head>

        @forelse ($backups as $b)
            <tr wire:key="backup-{{ $b->id }}" class="hover:bg-slate-50/50">
                <td class="px-4 py-3 whitespace-nowrap text-xs text-slate-700 font-mono">
                    {{ $b->formattedCreatedAt() }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-xs font-medium text-slate-900">
                    <div class="flex items-center gap-1.5">
                        <span>{{ $b->backup_name }}</span>
                    </div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-xs">
                    <span @class([
                        'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold uppercase',
                        'bg-blue-50 text-blue-700 border border-blue-200' => $b->backup_type->value === 'automatic',
                        'bg-purple-50 text-purple-700 border border-purple-200' => $b->backup_type->value === 'manual',
                        'bg-amber-50 text-amber-800 border border-amber-200' => $b->backup_type->value === 'safety',
                    ])>
                        {{ $b->backup_type->label() }}
                    </span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-xs font-medium text-slate-700">
                    {{ $b->formattedSize() }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-xs">
                    <span @class([
                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold capitalize',
                        'bg-emerald-50 text-emerald-700 border border-emerald-200' => $b->status->value === 'completed',
                        'bg-amber-50 text-amber-700 border border-amber-200' => in_array($b->status->value, ['running', 'restoring']),
                        'bg-rose-50 text-rose-700 border border-rose-200' => in_array($b->status->value, ['failed', 'corrupted']),
                        'bg-blue-50 text-blue-700 border border-blue-200' => $b->status->value === 'restored',
                        'bg-slate-100 text-slate-700' => !in_array($b->status->value, ['completed', 'running', 'restoring', 'failed', 'corrupted', 'restored']),
                    ])>
                        {{ $b->status->label() }}
                    </span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-xs font-mono text-slate-500" title="{{ $b->checksum }}">
                    {{ $b->checksum ? substr($b->checksum, 0, 12).'...' : '—' }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-center text-xs">
                    <button type="button"
                            wire:click="toggleProtection({{ $b->id }})"
                            title="{{ $b->is_protected ? __('backup.unprotect') : __('backup.protect') }}"
                            class="p-1 rounded hover:bg-slate-100 transition">
                        @if ($b->is_protected)
                            <span class="text-amber-600 font-bold" title="Protected">🔒</span>
                        @else
                            <span class="text-slate-300 hover:text-slate-500" title="Unprotected">🔓</span>
                        @endif
                    </button>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-right text-xs">
                    <div class="inline-flex items-center gap-1.5">
                        <button type="button"
                                wire:click="openDetailModal({{ $b->id }})"
                                class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                            {{ __('backup.view_details') }}
                        </button>

                        @if ($b->isCompleted())
                            <a href="{{ route('admin.backups.download', $b) }}"
                               class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-indigo-600 shadow-xs hover:bg-slate-50">
                                {{ __('backup.download') }}
                            </a>

                            <button type="button"
                                    wire:click="openRestoreModal({{ $b->id }})"
                                    class="rounded-md bg-amber-50 border border-amber-200 px-2 py-1 text-xs font-semibold text-amber-800 shadow-xs hover:bg-amber-100">
                                {{ __('backup.restore') }}
                            </button>
                        @endif

                        @if (!$b->is_protected)
                            <button type="button"
                                    wire:click="openDeleteModal({{ $b->id }})"
                                    class="rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 shadow-xs hover:bg-rose-100">
                                {{ __('backup.delete') }}
                            </button>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-400">
                    {{ __('backup.no_backups') }}
                </td>
            </tr>
        @endforelse
    </x-ui.table>

    <div>{{ $backups->links() }}</div>

    {{-- Backup Details Modal --}}
    @if ($showDetailModal && $selectedBackup)
        <x-ui.modal :title="__('backup.details_title')" maxWidth="2xl" zIndex="z-50" closeAction="closeDetailModal">
            <div class="space-y-4 px-6 py-5 text-sm max-h-[75vh] overflow-y-auto">
                <div class="grid grid-cols-2 gap-4 rounded-lg bg-slate-50 p-4 border border-slate-200 text-xs">
                    <div>
                        <span class="block font-semibold text-slate-500 uppercase">{{ __('backup.name') }}</span>
                        <span class="text-slate-900 font-mono font-medium">{{ $selectedBackup->backup_name }}</span>
                    </div>
                    <div>
                        <span class="block font-semibold text-slate-500 uppercase">{{ __('backup.type') }}</span>
                        <span class="font-bold text-slate-900">{{ $selectedBackup->backup_type->label() }}</span>
                    </div>
                    <div>
                        <span class="block font-semibold text-slate-500 uppercase">{{ __('backup.environment') }}</span>
                        <span class="text-slate-900">{{ $selectedBackup->environment }}</span>
                    </div>
                    <div>
                        <span class="block font-semibold text-slate-500 uppercase">{{ __('backup.database_name') }}</span>
                        <span class="text-slate-900 font-mono">{{ $selectedBackup->database_name ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="block font-semibold text-slate-500 uppercase">{{ __('backup.size') }}</span>
                        <span class="text-slate-900 font-medium">{{ $selectedBackup->formattedSize() }} ({{ number_format($selectedBackup->file_size) }} bytes)</span>
                    </div>
                    <div>
                        <span class="block font-semibold text-slate-500 uppercase">{{ __('backup.created_by') }}</span>
                        <span class="text-slate-900">{{ $selectedBackup->user?->name ?? 'Automated System Scheduler' }}</span>
                    </div>
                    <div>
                        <span class="block font-semibold text-slate-500 uppercase">{{ __('backup.date_time') }}</span>
                        <span class="font-mono text-slate-900">{{ $selectedBackup->formattedCreatedAt('d M Y, h:i:s A') }}</span>
                    </div>
                    <div>
                        <span class="block font-semibold text-slate-500 uppercase">{{ __('backup.completed_at') }}</span>
                        <span class="font-mono text-slate-900">{{ $selectedBackup->completed_at ? $selectedBackup->completed_at->copy()->timezone('Asia/Dhaka')->format('d M Y, h:i:s A') : '—' }}</span>
                    </div>
                    <div class="col-span-2">
                        <span class="block font-semibold text-slate-500 uppercase">{{ __('backup.checksum') }}</span>
                        <span class="font-mono text-xs text-slate-800 break-all select-all bg-white p-1 rounded border border-slate-200 block mt-0.5">{{ $selectedBackup->checksum ?? '—' }}</span>
                    </div>
                </div>

                @if (!empty($selectedBackup->metadata))
                    <div>
                        <h4 class="text-xs font-bold uppercase text-slate-600 mb-2">Manifest Metadata</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
                            <div class="rounded bg-slate-100 p-2">
                                <span class="text-slate-500 block">Tables Count</span>
                                <span class="font-bold text-slate-800">{{ $selectedBackup->metadata['tables_count'] ?? '—' }}</span>
                            </div>
                            <div class="rounded bg-slate-100 p-2">
                                <span class="text-slate-500 block">Files Count</span>
                                <span class="font-bold text-slate-800">{{ $selectedBackup->metadata['files_count'] ?? '—' }}</span>
                            </div>
                            <div class="rounded bg-slate-100 p-2">
                                <span class="text-slate-500 block">Duration</span>
                                <span class="font-bold text-slate-800">{{ isset($selectedBackup->metadata['duration_ms']) ? $selectedBackup->metadata['duration_ms'] . ' ms' : '—' }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($selectedBackup->error_message)
                    <div class="rounded-lg bg-rose-50 border border-rose-200 p-3 text-xs text-rose-900">
                        <span class="font-bold block mb-1">Error Message:</span>
                        <pre class="whitespace-pre-wrap font-mono">{{ $selectedBackup->error_message }}</pre>
                    </div>
                @endif
            </div>

            <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-6 py-3">
                <x-ui.button variant="secondary" wire:click="closeDetailModal">{{ __('backup.close') }}</x-ui.button>
            </div>
        </x-ui.modal>
    @endif

    {{-- Restore Confirmation Modal --}}
    @if ($showRestoreModal && $selectedBackup)
        <x-ui.modal :title="__('backup.restore_title')" maxWidth="xl" zIndex="z-50" closeAction="closeRestoreModal">
            <div class="space-y-4 px-6 py-5 text-sm">
                <div class="rounded-lg bg-amber-50 border border-amber-300 p-4 text-amber-900 text-xs leading-relaxed">
                    <div class="flex items-center gap-2 font-bold text-sm text-amber-800 mb-1">
                        <span>⚠️</span>
                        <span>MANDATORY SYSTEM RESTORE WARNING</span>
                    </div>
                    <p>{{ __('backup.restore_warning') }}</p>
                </div>

                {{-- 4-Stage Process Overview --}}
                <div class="rounded-lg bg-indigo-50/70 border border-indigo-200 p-3 text-xs text-indigo-950 space-y-1.5">
                    <div class="font-bold text-indigo-900 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <span>{{ __('backup.restore_process_overview') }}</span>
                    </div>
                    <ul class="space-y-1 text-[11px] text-slate-700 pl-5 list-disc">
                        <li>{{ __('backup.restore_step_1') }}</li>
                        <li>{{ __('backup.restore_step_2') }}</li>
                        <li>{{ __('backup.restore_step_3') }}</li>
                        <li>{{ __('backup.restore_step_4') }}</li>
                    </ul>
                </div>

                <div class="rounded-lg bg-slate-50 p-3 border border-slate-200 text-xs space-y-1">
                    <div><span class="font-semibold text-slate-500">Target Backup:</span> <span class="font-mono text-slate-900 font-bold">{{ $selectedBackup->backup_name }}</span></div>
                    <div><span class="font-semibold text-slate-500">Created:</span> <span class="text-slate-700">{{ $selectedBackup->formattedCreatedAt() }}</span></div>
                    <div><span class="font-semibold text-slate-500">Archive Size:</span> <span class="text-slate-700">{{ $selectedBackup->formattedSize() }}</span></div>
                    <div><span class="font-semibold text-slate-500">Checksum (SHA-256):</span> <span class="font-mono text-[11px] text-slate-600 truncate block">{{ $selectedBackup->checksum }}</span></div>
                </div>

                {{-- Type Confirmation Phrase Input --}}
                <div class="space-y-1.5 pt-1">
                    <label class="block text-xs font-bold text-slate-700">
                        {{ __('backup.restore_type_phrase') }}
                    </label>
                    <input type="text"
                           wire:model.live="restoreConfirmationPhrase"
                           placeholder="RESTORE"
                           class="w-full rounded-lg border-slate-300 font-mono text-sm font-bold tracking-wider placeholder-slate-400 focus:border-amber-500 focus:ring-amber-500 uppercase">
                </div>

                <div class="pt-1">
                    <label class="flex items-start gap-2 cursor-pointer select-none">
                        <input type="checkbox" wire:model.live="restoreConfirmed" class="mt-0.5 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                        <span class="text-xs font-semibold text-slate-800 leading-snug">
                            {{ __('backup.restore_confirm_checkbox') }}
                        </span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                <x-ui.button variant="secondary" wire:click="closeRestoreModal" :disabled="$isRestoring">{{ __('backup.cancel') }}</x-ui.button>
                <button type="button"
                        wire:click="confirmRestore"
                        wire:loading.attr="disabled"
                        @disabled(!$restoreConfirmed || trim(strtoupper($restoreConfirmationPhrase)) !== 'RESTORE' || $isRestoring)
                        class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-amber-500 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    <span wire:loading wire:target="confirmRestore">
                        <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                    <span wire:loading.remove wire:target="confirmRestore">{{ __('backup.confirm_restore') }}</span>
                    <span wire:loading wire:target="confirmRestore">{{ __('backup.restoring') }}</span>
                </button>
            </div>
        </x-ui.modal>
    @endif

    {{-- Delete Confirmation Modal --}}
    @if ($showDeleteModal && $backupToDelete)
        <x-ui.confirm-dialog
            :title="__('backup.delete_title')"
            :message="__('backup.delete_warning')"
            :confirm-label="__('backup.confirm_delete')"
            confirm-action="confirmDelete"
            cancel-action="closeDeleteModal"
            variant="danger"
        >
            <div class="space-y-1 text-xs">
                <div><span class="font-semibold text-slate-500">{{ __('backup.name') }}:</span> <span class="font-mono font-bold text-slate-800">{{ $backupToDelete->backup_name }}</span></div>
                <div><span class="font-semibold text-slate-500">{{ __('backup.date_time') }}:</span> <span class="text-slate-700">{{ $backupToDelete->formattedCreatedAt() }}</span></div>
                <div><span class="font-semibold text-slate-500">{{ __('backup.size') }}:</span> <span class="text-slate-700">{{ $backupToDelete->formattedSize() }}</span></div>
            </div>
        </x-ui.confirm-dialog>
    @endif

    {{-- Upload Backup Modal --}}
    @if ($showUploadModal)
        <x-ui.modal :title="__('backup.upload_modal_title')" maxWidth="lg" zIndex="z-50" closeAction="closeUploadModal">
            <div class="space-y-4 px-6 py-5 text-sm">
                <p class="text-xs text-slate-600 leading-relaxed">{{ __('backup.upload_help') }}</p>

                <div class="rounded-xl border-2 border-dashed border-slate-300 p-6 text-center hover:border-indigo-400 transition bg-slate-50/50">
                    <input type="file" wire:model="uploadedFile" id="uploadedFile" accept=".zip,.enc" class="hidden">
                    <label for="uploadedFile" class="cursor-pointer block">
                        <svg class="mx-auto h-10 w-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <span class="mt-2 block text-xs font-semibold text-indigo-600 hover:text-indigo-500">
                            {{ __('backup.upload_select_file') }}
                        </span>
                        <span class="mt-1 block text-[11px] text-slate-500">
                            {{ __('backup.upload_file_types') }}
                        </span>
                    </label>

                    @if ($uploadedFile)
                        <div class="mt-4 flex items-center justify-between rounded-lg bg-indigo-50 px-3 py-2 text-xs text-indigo-900 border border-indigo-200">
                            <span class="font-medium truncate font-mono">{{ $uploadedFile->getClientOriginalName() }}</span>
                            <span class="text-slate-500 text-[11px]">{{ round($uploadedFile->getSize() / 1024, 1) }} KB</span>
                        </div>
                    @endif

                    <div wire:loading wire:target="uploadedFile" class="mt-2 text-xs text-indigo-600 font-medium animate-pulse">
                        Uploading to server...
                    </div>
                </div>

                @error('uploadedFile')
                    <div class="rounded-lg bg-rose-50 border border-rose-200 p-2 text-xs text-rose-700">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                <x-ui.button variant="secondary" wire:click="closeUploadModal" :disabled="$isUploading">{{ __('backup.cancel') }}</x-ui.button>
                <button type="button"
                        wire:click="processUpload"
                        wire:loading.attr="disabled"
                        @disabled(!$uploadedFile || $isUploading)
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500 disabled:opacity-50 transition">
                    <span wire:loading wire:target="processUpload">
                        <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                    <span wire:loading.remove wire:target="processUpload">{{ __('backup.upload_button') }}</span>
                    <span wire:loading wire:target="processUpload">{{ __('backup.uploading') }}</span>
                </button>
            </div>
        </x-ui.modal>
    @endif
</div>
