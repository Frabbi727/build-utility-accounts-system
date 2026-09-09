<div>
    @if ($isStaff)
        <h1 class="mb-6 text-lg font-semibold text-slate-900">{{ __('nav.dashboard') }}</h1>

        @if ($needsFlats)
            <div class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-medium">{{ __('billing.no_flats') }}</p>
                <p class="mt-1">{{ __('masters.bulk_generate_help') }}</p>
                <a href="{{ route('flats.index') }}" class="mt-2 inline-block font-medium underline">
                    {{ __('masters.bulk_generate') }}
                </a>
            </div>
        @endif

        @if ($needsChargeHeads)
            <div class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-medium">{{ __('masters.no_charge_heads') }}</p>
                <p class="mt-1">{{ __('masters.no_charge_heads_help') }}</p>
                <a href="{{ route('charge-heads.index') }}" class="mt-2 inline-block font-medium underline">
                    {{ __('masters.charge_heads') }}
                </a>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('reports.total_receivable') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format((float) $totalReceivable, 2) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('reports.cash_in_hand') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format((float) $cash, 2) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('reports.bank') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format((float) $bank, 2) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('reports.active_flats') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $flatCount }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('reports.unpaid_bills') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $unpaidBills }}</p>
            </div>
        </div>
    @else
        {{-- Resident Portal --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-900">
                    {{ __('dashboard.welcome_back', ['name' => auth()->user()->name]) }}
                </h1>
                @if ($selectedFlat)
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $selectedFlat->building->displayName() }} &bull; {{ __('maintenance.flat') }}: <span class="font-semibold text-slate-800">{{ $selectedFlat->number }}</span>
                    </p>
                @endif
            </div>

            @if ($flats->count() > 1)
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-slate-500">{{ __('dashboard.my_flats') }}:</span>
                    <div class="inline-flex rounded-md shadow-sm">
                        @foreach ($flats as $f)
                            <button type="button"
                                    wire:click="selectFlat({{ $f->id }})"
                                    class="px-3 py-1.5 text-xs font-medium first:rounded-l-md last:rounded-r-md border {{ $selectedFlatId === $f->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">
                                {{ $f->number }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        @if ($notice)
            <div class="mb-6">
                <x-ui.notice :message="$notice" :type="$noticeType" />
            </div>
        @endif

        @if (! $selectedFlat)
            <div class="rounded-lg border border-slate-200 bg-white p-8 text-center">
                <p class="text-slate-500">{{ __('reports.no_flat_linked') }}</p>
            </div>
        @else
            {{-- Emergency Notice Banner if any --}}
            @php($emergencyNotice = $activeNotices->firstWhere('type', App\Enums\NoticeType::Emergency))
            @if ($emergencyNotice)
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900 shadow-sm flex items-start justify-between">
                    <div class="flex items-start gap-3">
                        <span class="text-2xl">🚨</span>
                        <div>
                            <h3 class="font-bold text-red-800">{{ $emergencyNotice->title }}</h3>
                            <p class="mt-1 text-sm text-red-700 line-clamp-2">{{ $emergencyNotice->content }}</p>
                        </div>
                    </div>
                    <button type="button" wire:click="openNoticeModal({{ $emergencyNotice->id }})"
                            class="shrink-0 text-xs font-semibold uppercase tracking-wider text-red-700 hover:text-red-900 underline ml-4">
                        {{ __('dashboard.view_notice') }}
                    </button>
                </div>
            @endif

            {{-- 4 Financial Metric Cards --}}
            <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('dashboard.total_due') }}</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums {{ bccomp($totalDue, '0.00', 2) > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                        {{ number_format((float) $totalDue, 2) }}
                    </p>
                    <div class="mt-3">
                        <a href="{{ route('flats.statement', $selectedFlat) }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                            {{ __('dashboard.view_statement') }} &rarr;
                        </a>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('dashboard.current_bill') }}</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-slate-900">
                        {{ number_format((float) $currentMonthCharges, 2) }}
                    </p>
                    <p class="mt-2 text-xs text-slate-500">
                        @if ($latestBill)
                            {{ \Carbon\Carbon::parse($latestBill->billing_month)->format('F Y') }}
                        @else
                            &mdash;
                        @endif
                    </p>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('dashboard.arrears') }}</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-slate-900">
                        {{ number_format((float) $arrears, 2) }}
                    </p>
                    <p class="mt-2 text-xs text-slate-500">{{ __('dashboard.arrears') }}</p>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('dashboard.advance_available') }}</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-emerald-600">
                        {{ number_format((float) $advanceHeld, 2) }}
                    </p>
                    <p class="mt-2 text-xs text-slate-500">{{ __('dashboard.advance_available') }}</p>
                </div>
            </div>

            <div class="grid gap-8 lg:grid-cols-3">
                {{-- Left 2 Columns: Latest Bill and Payment History --}}
                <div class="space-y-8 lg:col-span-2">
                    {{-- Latest Bill Preview --}}
                    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                            <div>
                                <h2 class="text-base font-semibold text-slate-900">{{ __('dashboard.latest_bill') }}</h2>
                                @if ($latestBill)
                                    <p class="text-xs text-slate-500">
                                        {{ __('dashboard.bill_no') }}: <span class="font-mono">{{ $latestBill->bill_no }}</span> &bull; {{ \Carbon\Carbon::parse($latestBill->billing_month)->format('F Y') }}
                                    </p>
                                @endif
                            </div>
                            @if ($latestBill)
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $latestBill->status->badgeClasses() }}">
                                        {{ $latestBill->status->label() }}
                                    </span>
                                    <a href="{{ route('bills.print', $latestBill) }}" target="_blank"
                                       class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                                        🖨️ {{ __('dashboard.print_bill') }}
                                    </a>
                                </div>
                            @endif
                        </div>

                        @if (! $latestBill)
                            <p class="py-6 text-center text-sm text-slate-500">{{ __('dashboard.no_bills_yet') }}</p>
                        @else
                            <div class="mt-4 divide-y divide-slate-100 text-sm">
                                @foreach ($latestBill->items as $item)
                                    <div class="flex justify-between py-2">
                                        <span class="text-slate-600">{{ $item->charge_name }}</span>
                                        <span class="font-medium tabular-nums text-slate-900">{{ number_format((float) $item->amount, 2) }}</span>
                                    </div>
                                @endforeach
                                <div class="flex justify-between pt-3 font-bold text-slate-900">
                                    <span>{{ __('billing.total_charges') }}</span>
                                    <span class="tabular-nums">{{ number_format((float) $latestBill->total_amount, 2) }}</span>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Recent Payment History --}}
                    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="mb-4 text-base font-semibold text-slate-900">{{ __('dashboard.recent_payments') }}</h2>

                        @if ($recentPayments->isEmpty())
                            <p class="py-4 text-center text-sm text-slate-500">{{ __('dashboard.no_payments_yet') }}</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-200 text-xs text-slate-500">
                                            <th class="pb-2">{{ __('dashboard.receipt_no') }}</th>
                                            <th class="pb-2">{{ __('dashboard.payment_date') }}</th>
                                            <th class="pb-2">{{ __('dashboard.method') }}</th>
                                            <th class="pb-2 text-right">{{ __('dashboard.amount') }}</th>
                                            <th class="pb-2 text-right">{{ __('masters.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach ($recentPayments as $pay)
                                            <tr>
                                                <td class="py-2.5 font-mono text-xs font-semibold text-slate-800">{{ $pay->receipt_no }}</td>
                                                <td class="py-2.5 text-slate-600">{{ $pay->received_on->format('M d, Y') }}</td>
                                                <td class="py-2.5 text-xs capitalize text-slate-600">{{ $pay->method->value }}</td>
                                                <td class="py-2.5 text-right font-semibold tabular-nums text-emerald-600">{{ number_format((float) $pay->amount, 2) }}</td>
                                                <td class="py-2.5 text-right">
                                                    <a href="{{ route('payments.receipt', $pay) }}" target="_blank"
                                                       class="text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                                                        {{ __('dashboard.print_receipt') }}
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Right Column: Notice Board & Maintenance Requests --}}
                <div class="space-y-8">
                    {{-- Notice Board --}}
                    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-base font-semibold text-slate-900">{{ __('dashboard.notices_announcements') }}</h2>
                            <span class="text-xs text-slate-400">📋</span>
                        </div>

                        @if ($activeNotices->isEmpty())
                            <p class="py-4 text-center text-sm text-slate-500">{{ __('dashboard.no_notices') }}</p>
                        @else
                            <div class="divide-y divide-slate-100">
                                @foreach ($activeNotices as $noticeItem)
                                    <div class="py-3">
                                        <div class="flex items-center justify-between">
                                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium {{ $noticeItem->type->badgeClasses() }}">
                                                {{ $noticeItem->type->label() }}
                                            </span>
                                            <span class="text-[11px] text-slate-400">{{ $noticeItem->published_at->diffForHumans() }}</span>
                                        </div>
                                        <h4 class="mt-1 text-sm font-medium text-slate-900">
                                            @if ($noticeItem->is_pinned) 📌 @endif {{ $noticeItem->title }}
                                        </h4>
                                        <p class="mt-1 text-xs text-slate-500 line-clamp-2">{{ $noticeItem->content }}</p>
                                        <button type="button" wire:click="openNoticeModal({{ $noticeItem->id }})"
                                                class="mt-2 text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                            {{ __('dashboard.view_notice') }} &rarr;
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Maintenance Requests --}}
                    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-base font-semibold text-slate-900">{{ __('dashboard.maintenance_requests') }}</h2>
                            <button type="button" wire:click="openTicketModal"
                                    class="inline-flex items-center rounded-md bg-indigo-50 px-2.5 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                                + {{ __('maintenance.report_issue') }}
                            </button>
                        </div>

                        @if ($myTickets->isEmpty())
                            <p class="py-4 text-center text-sm text-slate-500">{{ __('dashboard.no_issues_reported') }}</p>
                        @else
                            <div class="divide-y divide-slate-100">
                                @foreach ($myTickets as $ticket)
                                    <div class="py-3">
                                        <div class="flex items-center justify-between">
                                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium {{ $ticket->status->badgeClasses() }}">
                                                {{ $ticket->status->label() }}
                                            </span>
                                            <span class="text-[11px] text-slate-400">{{ $ticket->created_at->format('M d') }}</span>
                                        </div>
                                        <h4 class="mt-1 text-sm font-medium text-slate-900">{{ $ticket->title }}</h4>
                                        <div class="mt-1 flex items-center gap-2 text-xs text-slate-500">
                                            <span>{{ $ticket->category->label() }}</span> &bull;
                                            <span class="font-medium {{ $ticket->priority->badgeClasses() }} rounded px-1">{{ $ticket->priority->label() }}</span>
                                        </div>
                                        @if ($ticket->resolution_notes)
                                            <div class="mt-2 rounded bg-slate-50 p-2 text-xs text-slate-700">
                                                <span class="font-semibold text-slate-900">{{ __('maintenance.resolution_notes') }}:</span>
                                                {{ $ticket->resolution_notes }}
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Report Issue Modal --}}
        @if ($showTicketModal)
            <x-ui.modal :title="__('maintenance.report_issue')">
                <form wire:submit="submitTicket">
                    <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                        <x-form.field :label="__('maintenance.title')" name="ticketTitle" required class="sm:col-span-2">
                            <x-form.input wire:model="ticketTitle" placeholder="e.g. Bathroom pipe leaking" required />
                        </x-form.field>

                        <x-form.field :label="__('maintenance.category')" name="ticketCategory" required>
                            <select wire:model="ticketCategory" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                                @endforeach
                            </select>
                        </x-form.field>

                        <x-form.field :label="__('maintenance.priority')" name="ticketPriority" required>
                            <select wire:model="ticketPriority" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @foreach ($priorities as $pr)
                                    <option value="{{ $pr->value }}">{{ $pr->label() }}</option>
                                @endforeach
                            </select>
                        </x-form.field>

                        <x-form.field :label="__('maintenance.description')" name="ticketDescription" required class="sm:col-span-2">
                            <textarea wire:model="ticketDescription" rows="4" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Please describe the issue in detail..." required></textarea>
                        </x-form.field>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                        <x-ui.button variant="secondary" wire:click="closeTicketModal">{{ __('masters.cancel') }}</x-ui.button>
                        <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('masters.save') }}</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>
        @endif

        {{-- View Notice Modal --}}
        @if ($showNoticeModal && $viewingNotice)
            <x-ui.modal :title="$viewingNotice->title">
                <div class="px-6 py-5">
                    <div class="mb-4 flex items-center gap-2">
                        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium {{ $viewingNotice->type->badgeClasses() }}">
                            {{ $viewingNotice->type->label() }}
                        </span>
                        @if ($viewingNotice->is_pinned)
                            <span class="text-xs font-medium text-amber-700">📌 {{ __('masters.pinned') }}</span>
                        @endif
                        <span class="text-xs text-slate-400">&bull; {{ $viewingNotice->published_at->format('M d, Y H:i') }}</span>
                    </div>

                    <div class="prose prose-sm text-slate-700 whitespace-pre-line">
                        {{ $viewingNotice->content }}
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-6 py-3">
                    <x-ui.button variant="secondary" wire:click="closeNoticeModal">{{ __('masters.done') }}</x-ui.button>
                </div>
            </x-ui.modal>
        @endif
    @endif
</div>
