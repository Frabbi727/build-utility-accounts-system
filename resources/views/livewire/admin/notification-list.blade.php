<div class="space-y-6">
    <x-ui.page-header :title="__('Notifications')" :description="__('View and manage all system notifications. Send manual notifications to users.')">
        <x-slot:actions>
            <button wire:click="openSendModal" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                {{ __('Send Notification') }}
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.notice :message="$notice" :type="$noticeType" />

    {{-- Filter Bar --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('Search') }}</label>
                <input type="search" wire:model.live.debounce.300ms="search"
                       placeholder="{{ __('Search title, body, user...') }}"
                       class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('Type') }}</label>
                <select wire:model.live="type" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('All Types') }}</option>
                    @foreach ($types as $t)
                        <option value="{{ $t['value'] }}">{{ $t['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('Status') }}</label>
                <select wire:model.live="status" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="pending">Pending</option>
                    <option value="sent">Sent</option>
                    <option value="failed">Failed</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('User') }}</label>
                <select wire:model.live="userId" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('All Users') }}</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('Read Status') }}</label>
                <select wire:model.live="isRead" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('All') }}</option>
                    <option value="0">Unread</option>
                    <option value="1">Read</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Notification Table --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-xs overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('User') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Type') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Title') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Read') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Created') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($notifications as $notification)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $notification->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700">
                                {{ str_replace('_', ' ', $notification->type) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ Str::limit($notification->title, 50) }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span @class([
                                'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                                'bg-yellow-50 text-yellow-700' => $notification->status === 'pending',
                                'bg-green-50 text-green-700' => $notification->status === 'sent',
                                'bg-red-50 text-red-700' => $notification->status === 'failed',
                            ])>{{ ucfirst($notification->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if ($notification->is_read)
                                <span class="text-green-600">✓ Read</span>
                            @else
                                <span class="text-slate-400">Unread</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-500">{{ $notification->created_at?->timezone('Asia/Dhaka')->format('d M Y, h:i A') }}</td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="openDetail({{ $notification->id }})" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                {{ __('Details') }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-sm text-slate-400">{{ __('No notifications found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($notifications->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>

    {{-- Detail Modal --}}
    @if ($showDetailModal && $viewingNotification)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="closeDetail">
            <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl max-h-[80vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-800">{{ __('Notification Details') }}</h3>
                    <button wire:click="closeDetail" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>

                <dl class="divide-y divide-slate-100">
                    <div class="py-2 flex gap-4"><dt class="w-28 text-sm font-medium text-slate-500">User</dt><dd class="text-sm text-slate-700">{{ $viewingNotification->user?->name ?? '—' }}</dd></div>
                    <div class="py-2 flex gap-4"><dt class="w-28 text-sm font-medium text-slate-500">Type</dt><dd class="text-sm text-slate-700">{{ $viewingNotification->type }}</dd></div>
                    <div class="py-2 flex gap-4"><dt class="w-28 text-sm font-medium text-slate-500">Title</dt><dd class="text-sm text-slate-700">{{ $viewingNotification->title }}</dd></div>
                    <div class="py-2 flex gap-4"><dt class="w-28 text-sm font-medium text-slate-500">Body</dt><dd class="text-sm text-slate-700">{{ $viewingNotification->body }}</dd></div>
                    <div class="py-2 flex gap-4"><dt class="w-28 text-sm font-medium text-slate-500">Status</dt><dd class="text-sm text-slate-700">{{ ucfirst($viewingNotification->status) }}</dd></div>
                    <div class="py-2 flex gap-4"><dt class="w-28 text-sm font-medium text-slate-500">Read</dt><dd class="text-sm text-slate-700">{{ $viewingNotification->is_read ? 'Yes (' . $viewingNotification->read_at?->format('d M Y, h:i A') . ')' : 'No' }}</dd></div>
                    <div class="py-2 flex gap-4"><dt class="w-28 text-sm font-medium text-slate-500">Created</dt><dd class="text-sm text-slate-700">{{ $viewingNotification->created_at?->timezone('Asia/Dhaka')->format('d M Y, h:i:s A') }}</dd></div>
                    @if ($viewingNotification->data)
                        <div class="py-2 flex gap-4"><dt class="w-28 text-sm font-medium text-slate-500">Data</dt><dd class="text-sm text-slate-700"><pre class="text-xs bg-slate-50 p-2 rounded">{{ json_encode($viewingNotification->data, JSON_PRETTY_PRINT) }}</pre></dd></div>
                    @endif
                </dl>

                {{-- Delivery Logs --}}
                @if ($viewingNotification->logs->isNotEmpty())
                    <h4 class="mt-4 text-sm font-semibold text-slate-700 mb-2">{{ __('Delivery Logs') }}</h4>
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">Device</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">Status</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">Error</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($viewingNotification->logs as $log)
                                <tr>
                                    <td class="px-3 py-2 text-slate-600">{{ $log->userDevice?->device_model ?? $log->userDevice?->device_id ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                            'bg-green-50 text-green-700' => $log->status === 'success',
                                            'bg-red-50 text-red-700' => $log->status === 'failed',
                                            'bg-yellow-50 text-yellow-700' => $log->status === 'skipped',
                                        ])>{{ ucfirst($log->status) }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-slate-500">{{ Str::limit($log->error_message, 60) ?? '—' }}</td>
                                    <td class="px-3 py-2 text-slate-500">{{ $log->created_at?->timezone('Asia/Dhaka')->format('h:i:s A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @endif

    {{-- Send Notification Modal --}}
    @if ($showSendModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="closeSendModal">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-800">{{ __('Send Notification') }}</h3>
                    <button wire:click="closeSendModal" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>

                <form wire:submit="sendNotification" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Target') }}</label>
                        <select wire:model.live="sendTarget" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="all_residents">All Residents (Owners & Tenants)</option>
                            <option value="owners">Owners Only</option>
                            <option value="tenants">Tenants Only</option>
                            <option value="staff">Staff & Admins</option>
                            <option value="single">Single User</option>
                        </select>
                        @error('sendTarget') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    @if ($sendTarget === 'single')
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Select User') }}</label>
                            <select wire:model="sendUserId" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Choose user...') }}</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                            @error('sendUserId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Title') }}</label>
                        <input type="text" wire:model="sendTitle" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500" placeholder="Notification title...">
                        @error('sendTitle') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Body') }}</label>
                        <textarea wire:model="sendBody" rows="4" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500" placeholder="Notification message..."></textarea>
                        @error('sendBody') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeSendModal" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-700">
                            {{ __('Send') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
