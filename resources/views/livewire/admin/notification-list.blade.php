<div class="space-y-6">
    <x-ui.page-header :title="__('Notification & Automation Hub')" :description="__('Manage automated reminder rules, preview live templates, inspect delivery logs, and send broadcast alerts.')">
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <button wire:click="previewReminders" class="inline-flex items-center gap-2 rounded-lg bg-white border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 shadow-xs hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    {{ __('Run Reminders Now') }}
                </button>
                <button wire:click="setTab('broadcast')" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    {{ __('Send Broadcast') }}
                </button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.notice :message="$notice" :type="$noticeType" />

    {{-- Tab Navigation Bar --}}
    <div class="border-b border-slate-200 bg-white rounded-t-xl px-4 pt-3 shadow-xs">
        <nav class="-mb-px flex space-x-6" aria-label="Tabs">
            <button wire:click="setTab('rules')"
                    @class([
                        'flex items-center gap-2 py-3 px-1 border-b-2 text-sm font-medium transition-colors',
                        'border-indigo-600 text-indigo-600' => $activeTab === 'rules',
                        'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' => $activeTab !== 'rules',
                    ])>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                {{ __('⚙️ Automation Rules & Templates') }}
            </button>

            <button wire:click="setTab('history')"
                    @class([
                        'flex items-center gap-2 py-3 px-1 border-b-2 text-sm font-medium transition-colors',
                        'border-indigo-600 text-indigo-600' => $activeTab === 'history',
                        'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' => $activeTab !== 'history',
                    ])>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                {{ __('📋 Notification Logs & History') }}
            </button>

            <button wire:click="setTab('broadcast')"
                    @class([
                        'flex items-center gap-2 py-3 px-1 border-b-2 text-sm font-medium transition-colors',
                        'border-indigo-600 text-indigo-600' => $activeTab === 'broadcast',
                        'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' => $activeTab !== 'broadcast',
                    ])>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                {{ __('📢 Broadcast & Reminders') }}
            </button>
        </nav>
    </div>

    {{-- TAB 1: AUTOMATION RULES & TEMPLATES --}}
    @if ($activeTab === 'rules')
        <div class="space-y-6">
            {{-- Rules Overview / Quick Controls --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">{{ __('Automated Notification Rules') }}</h3>
                    <p class="text-sm text-slate-500 mt-0.5">{{ __('Configure event triggers, timing offsets, and customize message templates with dynamic placeholders.') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <button wire:click="previewReminders" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-50 border border-indigo-200 px-3.5 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 transition-colors">
                        <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ __('Run Reminders Now') }}
                    </button>
                </div>
            </div>

            {{-- Rules Table --}}
            <div class="rounded-xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Trigger Event') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Timing Offset') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Title Template') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Scope') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Active') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rules as $rule)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-sm text-slate-800">
                                        {{ $rule->trigger_event->label() }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ $rule->trigger_event->description() }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($rule->trigger_event->isBillEvent())
                                        @if ($rule->days_offset < 0)
                                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                                                {{ abs($rule->days_offset) }} {{ __('days before due') }}
                                            </span>
                                        @elseif ($rule->days_offset === 0)
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
                                                {{ __('On due date (0)') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700">
                                                {{ $rule->days_offset }} {{ __('days overdue') }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                            {{ __('Immediate (0)') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700 max-w-xs truncate" title="{{ $rule->title_template }}">
                                    {{ $rule->title_template }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($rule->building_id)
                                        <span class="inline-flex items-center rounded-full bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-700">
                                            {{ __('Building Custom') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                            {{ __('Global Default') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button wire:click="toggleRuleActive({{ $rule->id }})"
                                            type="button"
                                            @class([
                                                'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                                                'bg-indigo-600' => $rule->is_active,
                                                'bg-slate-200' => ! $rule->is_active,
                                            ])>
                                        <span class="sr-only">{{ __('Toggle rule active') }}</span>
                                        <span @class([
                                            'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out',
                                            'translate-x-5' => $rule->is_active,
                                            'translate-x-0' => ! $rule->is_active,
                                        ])></span>
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <button wire:click="openEditRuleModal({{ $rule->id }})" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-900 font-medium">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        {{ __('Edit') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">
                                    {{ __('No notification rules configured.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- TAB 2: NOTIFICATION LOGS & HISTORY --}}
    @if ($activeTab === 'history')
        <div class="space-y-6">
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
        </div>
    @endif

    {{-- TAB 3: BROADCAST & REMINDERS --}}
    @if ($activeTab === 'broadcast')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                    <div class="border-b border-slate-200 pb-4 mb-6">
                        <h3 class="text-base font-semibold text-slate-900">{{ __('Send Broadcast Notification') }}</h3>
                        <p class="text-sm text-slate-500 mt-1">{{ __('Send instant push and in-app announcements to residents or staff groups.') }}</p>
                    </div>

                    <form wire:submit="sendNotification" class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Target Audience') }}</label>
                            <select wire:model.live="sendTarget" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="all_residents">{{ __('All Residents (Owners & Tenants)') }}</option>
                                <option value="owners">{{ __('Owners Only') }}</option>
                                <option value="tenants">{{ __('Tenants Only') }}</option>
                                <option value="staff">{{ __('Staff & Admins') }}</option>
                                <option value="single">{{ __('Single User') }}</option>
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
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Notification Title') }}</label>
                            <input type="text" wire:model="sendTitle" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500" placeholder="{{ __('e.g. Building Maintenance Notice') }}">
                            @error('sendTitle') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Notification Body') }}</label>
                            <textarea wire:model="sendBody" rows="5" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500" placeholder="{{ __('Write your announcement message here...') }}"></textarea>
                            @error('sendBody') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex justify-end pt-3">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                {{ __('Dispatch Broadcast') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Quick Reminders Card --}}
            <div class="lg:col-span-1 space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                    <h3 class="text-base font-semibold text-slate-900 mb-2">{{ __('On-Demand Bill Reminders') }}</h3>
                    <p class="text-sm text-slate-500 mb-4">{{ __('Instantly run automated reminder evaluations for all due and overdue bills for today.') }}</p>
                    <button wire:click="previewReminders" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-50 border border-indigo-200 px-4 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 transition-colors">
                        <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        {{ __('Preview & Trigger Reminders') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Edit Rule Modal --}}
    @if ($showEditRuleModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:click.self="closeEditRuleModal">
            <div class="w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto space-y-6">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-800">{{ __('Edit Notification Rule & Template') }}</h3>
                        <p class="text-xs text-slate-500">{{ $editingTriggerEvent }}</p>
                    </div>
                    <button wire:click="closeEditRuleModal" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                </div>

                <form wire:submit="saveRule" class="space-y-4">
                    {{-- Timing Offset (for bill events) --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Days Offset (Relative to Due Date)') }}</label>
                        <input type="number" wire:model="editingDaysOffset" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="text-xs text-slate-500 mt-1">{{ __('Use negative numbers for days before due date (e.g., -3), 0 for on due date, and positive numbers for overdue (e.g., 2).') }}</p>
                        @error('editingDaysOffset') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Title Template --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Title Template') }}</label>
                        <input type="text" wire:model.live="editingTitleTemplate" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                        @error('editingTitleTemplate') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Body Template --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Body Template') }}</label>
                        <textarea wire:model.live="editingBodyTemplate" rows="4" class="w-full rounded-md border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        @error('editingBodyTemplate') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Placeholder Tokens Badge Bar --}}
                    @if (! empty($supportedTokens))
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">{{ __('Available Dynamic Placeholders') }}</label>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($supportedTokens as $token)
                                    <span class="inline-flex items-center rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-mono font-medium text-indigo-700 border border-indigo-100">
                                        {{ $token }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Active Toggle --}}
                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" id="editingIsActive" wire:model="editingIsActive" class="rounded border-slate-300 text-indigo-600 shadow-xs focus:ring-indigo-500">
                        <label for="editingIsActive" class="text-sm font-medium text-slate-700">{{ __('Rule is enabled and active') }}</label>
                    </div>

                    {{-- Live Preview Card --}}
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 space-y-2">
                        <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <span>{{ __('📱 Live Real-Time Preview') }}</span>
                            <span class="text-indigo-600 normal-case font-normal">{{ __('Simulated with sample resident data') }}</span>
                        </div>
                        <div class="rounded-md border border-slate-300 bg-white p-3 shadow-xs">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="h-2 w-2 rounded-full bg-indigo-600"></span>
                                <span class="font-semibold text-sm text-slate-800">{{ $this->previewTitle ?: __('(No title rendered)') }}</span>
                            </div>
                            <p class="text-xs text-slate-600 leading-relaxed">{{ $this->previewBody ?: __('(No body rendered)') }}</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-200">
                        <button type="button" wire:click="closeEditRuleModal" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-700">
                            {{ __('Save Rule') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Reminders Dry-Run & Confirmation Modal --}}
    @if ($showRemindersConfirmModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:click.self="closeRemindersConfirmModal">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <h3 class="text-lg font-semibold text-slate-800">{{ __('Trigger Automated Bill Reminders') }}</h3>
                    <button wire:click="closeRemindersConfirmModal" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                </div>

                <div class="rounded-lg bg-indigo-50 p-4 border border-indigo-100">
                    <div class="text-sm font-medium text-indigo-900 mb-2">{{ __('Dry-Run Reminder Evaluation') }}</div>
                    <div class="grid grid-cols-2 gap-4 text-center">
                        <div class="bg-white rounded-lg p-3 border border-indigo-100 shadow-xs">
                            <div class="text-2xl font-bold text-indigo-700">{{ $dryRunSummary['bills_count'] ?? 0 }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">{{ __('Eligible Bills') }}</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-indigo-100 shadow-xs">
                            <div class="text-2xl font-bold text-indigo-700">{{ $dryRunSummary['notifications_count'] ?? 0 }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">{{ __('Recipients') }}</div>
                        </div>
                    </div>
                </div>

                <p class="text-xs text-slate-500">
                    {{ __('Dispatching will evaluate active reminder rules (upcoming, due today, and overdue) and send real-time push & in-app alerts.') }}
                </p>

                <div class="flex justify-end gap-3 pt-2 border-t border-slate-200">
                    <button type="button" wire:click="closeRemindersConfirmModal" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button" wire:click="sendRemindersNow" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-700">
                        {{ __('Send Reminders Now') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Detail Modal --}}
    @if ($showDetailModal && $viewingNotification)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:click.self="closeDetail">
            <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl max-h-[85vh] overflow-y-auto space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <h3 class="text-lg font-semibold text-slate-800">{{ __('Notification Details') }}</h3>
                    <button wire:click="closeDetail" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                </div>

                <dl class="divide-y divide-slate-100">
                    <div class="py-2 flex gap-4"><dt class="w-28 text-sm font-medium text-slate-500">{{ __('User') }}</dt><dd class="text-sm text-slate-700">{{ $viewingNotification->user?->name ?? '—' }}</dd></div>
                    <div class="py-2 flex gap-4"><dt class="w-28 text-sm font-medium text-slate-500">{{ __('Type') }}</dt><dd class="text-sm text-slate-700">{{ $viewingNotification->type }}</dd></div>
                    <div class="py-2 flex gap-4"><dt class="w-28 text-sm font-medium text-slate-500">{{ __('Title') }}</dt><dd class="text-sm text-slate-700">{{ $viewingNotification->title }}</dd></div>
                    <div class="py-2 flex gap-4"><dt class="w-28 text-sm font-medium text-slate-500">{{ __('Body') }}</dt><dd class="text-sm text-slate-700">{{ $viewingNotification->body }}</dd></div>
                    <div class="py-2 flex gap-4"><dt class="w-28 text-sm font-medium text-slate-500">{{ __('Status') }}</dt><dd class="text-sm text-slate-700">{{ ucfirst($viewingNotification->status) }}</dd></div>
                    <div class="py-2 flex gap-4"><dt class="w-28 text-sm font-medium text-slate-500">{{ __('Read') }}</dt><dd class="text-sm text-slate-700">{{ $viewingNotification->is_read ? 'Yes (' . $viewingNotification->read_at?->format('d M Y, h:i A') . ')' : 'No' }}</dd></div>
                    <div class="py-2 flex gap-4"><dt class="w-28 text-sm font-medium text-slate-500">{{ __('Created') }}</dt><dd class="text-sm text-slate-700">{{ $viewingNotification->created_at?->timezone('Asia/Dhaka')->format('d M Y, h:i:s A') }}</dd></div>
                    @if ($viewingNotification->data)
                        <div class="py-2 flex gap-4"><dt class="w-28 text-sm font-medium text-slate-500">{{ __('Data') }}</dt><dd class="text-sm text-slate-700"><pre class="text-xs bg-slate-50 p-2 rounded max-h-40 overflow-y-auto">{{ json_encode($viewingNotification->data, JSON_PRETTY_PRINT) }}</pre></dd></div>
                    @endif
                </dl>

                {{-- Delivery Logs --}}
                @if ($viewingNotification->logs->isNotEmpty())
                    <h4 class="mt-4 text-sm font-semibold text-slate-700">{{ __('Delivery Logs') }}</h4>
                    <div class="rounded-lg border border-slate-200 overflow-hidden">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">{{ __('Device') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">{{ __('Status') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">{{ __('Error') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">{{ __('Time') }}</th>
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
                                        <td class="px-3 py-2 text-slate-500">{{ $log->error_message ? Str::limit($log->error_message, 60) : '—' }}</td>
                                        <td class="px-3 py-2 text-slate-500">{{ $log->created_at?->timezone('Asia/Dhaka')->format('h:i:s A') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
