<div>
    @if ($isStaff)
        {{-- ========================================================================= --}}
        {{-- STAFF / MANAGEMENT EXECUTIVE COMMAND CENTER                              --}}
        {{-- ========================================================================= --}}

        {{-- 1. Executive Context & Quick Actions Header --}}
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-slate-200/80 pb-6">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('dashboard.financial_overview') }}</h1>
                    @if ($building)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 border border-indigo-200/60 px-3 py-1 text-xs font-semibold text-indigo-700">
                            <svg class="h-3.5 w-3.5 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect width="16" height="20" x="4" y="2" rx="2" />
                                <path d="M9 22v-4h6v4M8 6h.01M16 6h.01M8 10h.01M16 10h.01M8 14h.01M16 14h.01" />
                            </svg>
                            {{ $building->displayName() }}
                        </span>
                    @endif
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-4 text-xs text-slate-500">
                    <span class="flex items-center gap-1.5 font-medium text-slate-700">
                        <svg class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect width="18" height="18" x="3" y="4" rx="2" ry="2" />
                            <line x1="16" x2="16" y1="2" y2="6" />
                            <line x1="8" x2="8" y1="2" y2="6" />
                            <line x1="3" x2="21" y1="10" y2="10" />
                        </svg>
                        {{ now()->format('F Y') }}
                    </span>
                    <span class="text-slate-300">&bull;</span>
                    <span>{{ __('reports.active_flats') }}: <strong class="font-semibold text-slate-800">{{ $flatCount }}</strong></span>
                    <span class="text-slate-300">&bull;</span>
                    <span>{{ __('dashboard.collection_rate') }}: <strong class="font-semibold text-indigo-600">{{ $collectionPercentage }}%</strong></span>
                </div>
            </div>

            {{-- Action Toolbar --}}
            <div class="flex flex-wrap items-center gap-2.5">
                @if (auth()->user()->canManageMoney())
                    <a href="{{ route('billing.generate') }}"
                       class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500 hover:shadow-sm transition cursor-pointer">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="12" x2="12" y1="18" y2="12" />
                            <line x1="9" x2="15" y1="15" y2="15" />
                        </svg>
                        {{ __('dashboard.generate_bills_now') }}
                    </a>
                    <a href="{{ route('payments.create') }}"
                       class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-500 hover:shadow-sm transition cursor-pointer">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect width="20" height="14" x="2" y="5" rx="2" />
                            <line x1="2" x2="22" y1="10" y2="10" />
                        </svg>
                        {{ __('dashboard.record_payment') }}
                    </a>
                @endif
                <a href="{{ route('expenses.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-slate-900 transition cursor-pointer">
                    <svg class="h-3.5 w-3.5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" x2="12" y1="2" y2="22" />
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                    </svg>
                    {{ __('dashboard.add_expense') }}
                </a>
                <a href="{{ route('readings.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-slate-900 transition cursor-pointer">
                    <svg class="h-3.5 w-3.5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                    </svg>
                    {{ __('dashboard.log_meter_reading') }}
                </a>
                <a href="{{ route('notices.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-slate-900 transition cursor-pointer">
                    <svg class="h-3.5 w-3.5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                        <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                    </svg>
                    {{ __('dashboard.post_announcement') }}
                </a>
            </div>
        </div>

        {{-- Configuration Warnings --}}
        @if ($needsFlats)
            <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-900 shadow-xs flex items-start gap-3">
                <span class="text-xl">⚠️</span>
                <div>
                    <p class="font-semibold">{{ __('billing.no_flats') }}</p>
                    <p class="mt-0.5 text-xs text-amber-800">{{ __('masters.bulk_generate_help') }}</p>
                    <a href="{{ route('flats.index') }}" class="mt-2 inline-flex items-center text-xs font-bold text-amber-900 underline hover:text-amber-700">
                        {{ __('masters.bulk_generate') }} &rarr;
                    </a>
                </div>
            </div>
        @endif

        @if ($needsChargeHeads)
            <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-900 shadow-xs flex items-start gap-3">
                <span class="text-xl">⚠️</span>
                <div>
                    <p class="font-semibold">{{ __('masters.no_charge_heads') }}</p>
                    <p class="mt-0.5 text-xs text-amber-800">{{ __('masters.no_charge_heads_help') }}</p>
                    <a href="{{ route('charge-heads.index') }}" class="mt-2 inline-flex items-center text-xs font-bold text-amber-900 underline hover:text-amber-700">
                        {{ __('masters.charge_heads') }} &rarr;
                    </a>
                </div>
            </div>
        @endif

        {{-- 2. Core Executive Financial Metric Cards (4 Cards) --}}
        <div class="mb-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Monthly Collections with Progress Bar --}}
            <div class="relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs transition hover:shadow-md hover:border-indigo-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="22 7 13.5 15.5 8.5 10.5 2 17" />
                                <polyline points="16 7 22 7 22 13" />
                            </svg>
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('dashboard.collection_rate') }}</span>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-bold text-indigo-700 ring-1 ring-inset ring-indigo-700/10">
                        {{ $collectionPercentage }}%
                    </span>
                </div>
                <div class="mt-4">
                    <p class="text-2xl font-bold tracking-tight tabular-nums text-slate-900">
                        ৳{{ number_format((float) $collectedThisMonth, 2) }}
                    </p>
                    <div class="mt-2.5 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="h-2 rounded-full bg-gradient-to-r from-indigo-500 to-indigo-600 transition-all duration-500" style="width: {{ $collectionPercentage }}%"></div>
                    </div>
                    <p class="mt-2.5 text-xs text-slate-500 truncate">
                        {{ __('dashboard.collected_of_target', [
                            'collected' => number_format((float) $collectedThisMonth, 2),
                            'target' => number_format((float) $billedThisMonth, 2)
                        ]) }}
                    </p>
                </div>
            </div>

            {{-- Total Outstanding Receivables --}}
            <div class="relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs transition hover:shadow-md hover:border-red-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg {{ bccomp($totalReceivable, '0.00', 2) > 0 ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600' }}">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" x2="12" y1="8" y2="12" />
                                <line x1="12" x2="12.01" y1="16" y2="16" />
                            </svg>
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('reports.total_receivable') }}</span>
                    </div>
                    <a href="{{ route('reports.owner-dues') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                        {{ __('reports.owner_dues') }} &rarr;
                    </a>
                </div>
                <div class="mt-4">
                    <p class="text-2xl font-bold tracking-tight tabular-nums {{ bccomp($totalReceivable, '0.00', 2) > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                        ৳{{ number_format((float) $totalReceivable, 2) }}
                    </p>
                    <p class="mt-4 text-xs text-slate-500 flex items-center gap-1.5">
                        <span class="inline-block h-2 w-2 rounded-full {{ $unpaidBills > 0 ? 'bg-red-500' : 'bg-emerald-500' }}"></span>
                        <strong class="font-semibold text-slate-800">{{ $unpaidBills }}</strong> {{ __('reports.unpaid_bills') }}
                    </p>
                </div>
            </div>

            {{-- Liquid Funds (Cash in hand + Bank) --}}
            <div class="relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs transition hover:shadow-md hover:border-emerald-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect width="20" height="12" x="2" y="6" rx="2" />
                                <circle cx="12" cy="12" r="2" />
                                <path d="M6 12h.01M18 12h.01" />
                            </svg>
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('dashboard.liquid_funds') }}</span>
                    </div>
                    <a href="{{ route('reports.cash-book') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                        {{ __('reports.cash_book') }} &rarr;
                    </a>
                </div>
                <div class="mt-4">
                    <p class="text-2xl font-bold tracking-tight tabular-nums text-emerald-600">
                        ৳{{ number_format((float) $totalLiquid, 2) }}
                    </p>
                    <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2 text-[11px] text-slate-500">
                        <span>{{ __('reports.cash_in_hand') }}: <strong class="font-mono text-slate-700">৳{{ number_format((float) $cash, 2) }}</strong></span>
                        <span>{{ __('reports.bank') }}: <strong class="font-mono text-slate-700">৳{{ number_format((float) $bank, 2) }}</strong></span>
                    </div>
                </div>
            </div>

            {{-- Net Monthly Cashflow --}}
            <div class="relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs transition hover:shadow-md hover:border-slate-300">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg {{ $isSurplus ? 'bg-teal-50 text-teal-600' : 'bg-rose-50 text-rose-600' }}">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                            </svg>
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('dashboard.net_cashflow') }}</span>
                    </div>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold {{ $isSurplus ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                        {{ $isSurplus ? __('dashboard.surplus') : __('dashboard.deficit') }}
                    </span>
                </div>
                <div class="mt-4">
                    <p class="text-2xl font-bold tracking-tight tabular-nums {{ $isSurplus ? 'text-slate-900' : 'text-rose-600' }}">
                        {{ $isSurplus ? '+' : '' }}৳{{ number_format((float) $netCashflow, 2) }}
                    </p>
                    <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2 text-[11px] text-slate-500">
                        <span>In: <strong class="font-mono text-emerald-600">৳{{ number_format((float) $collectedThisMonth, 2) }}</strong></span>
                        <span>Out: <strong class="font-mono text-rose-600">৳{{ number_format((float) $expensesThisMonth, 2) }}</strong></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Operational Pulse & Action Pipeline (4 Interactive Pulse Cards) --}}
        <div class="mb-8">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('dashboard.operational_pulse') }}</h2>
                <span class="text-[11px] text-slate-400">Click any card to take immediate action</span>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Pending Payment Submissions --}}
                <a href="{{ route('billing.submissions') }}"
                   class="group relative overflow-hidden rounded-2xl border {{ $pendingSubmissionsCount > 0 ? 'border-amber-300 bg-amber-50/40 hover:bg-amber-50/80 ring-1 ring-amber-400/20' : 'border-slate-200/90 bg-white hover:bg-slate-50' }} p-4 shadow-xs transition hover:shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-800 flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-amber-100 text-amber-700 text-xs">🔔</span>
                            {{ __('dashboard.pending_approvals') }}
                        </span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $pendingSubmissionsCount > 0 ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-600' }}">
                            {{ $pendingSubmissionsCount }}
                        </span>
                    </div>
                    <p class="mt-2.5 text-xs text-slate-600 font-medium">
                        @if ($pendingSubmissionsCount > 0)
                            <span class="text-amber-900 font-semibold">{{ __('billing.pending_submissions', ['count' => $pendingSubmissionsCount]) }}</span>
                        @else
                            {{ __('dashboard.status_all_clear') }}
                        @endif
                    </p>
                    <span class="mt-2 inline-flex items-center gap-1 text-[11px] font-bold text-indigo-600 group-hover:text-indigo-800">
                        {{ __('dashboard.review_now') }} &rarr;
                    </span>
                </a>

                {{-- Meter Readings Progress --}}
                <a href="{{ route('readings.index') }}"
                   class="group relative overflow-hidden rounded-2xl border {{ $isMeterReadingComplete ? 'border-slate-200/90 bg-white hover:bg-slate-50' : 'border-amber-300 bg-amber-50/40 hover:bg-amber-50/80 ring-1 ring-amber-400/20' }} p-4 shadow-xs transition hover:shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-800 flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-indigo-100 text-indigo-700 text-xs">⚡</span>
                            {{ __('dashboard.meter_readings') }}
                        </span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $isMeterReadingComplete ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-500 text-white' }}">
                            {{ $recordedMetersCount }}/{{ $totalMetersCount }}
                        </span>
                    </div>
                    <p class="mt-2.5 text-xs text-slate-600 font-medium">
                        {{ $isMeterReadingComplete ? __('dashboard.all_meters_logged') : __('dashboard.meters_pending') }}
                    </p>
                    <span class="mt-2 inline-flex items-center gap-1 text-[11px] font-bold text-indigo-600 group-hover:text-indigo-800">
                        {{ __('dashboard.log_meter_reading') }} &rarr;
                    </span>
                </a>

                {{-- Monthly Bill Generation --}}
                <a href="{{ route('billing.generate') }}"
                   class="group relative overflow-hidden rounded-2xl border {{ $isBillingCompleteForMonth ? 'border-slate-200/90 bg-white hover:bg-slate-50' : 'border-indigo-300 bg-indigo-50/40 hover:bg-indigo-50/80 ring-1 ring-indigo-400/20' }} p-4 shadow-xs transition hover:shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-800 flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-blue-100 text-blue-700 text-xs">📑</span>
                            {{ __('dashboard.bill_generation') }}
                        </span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $isBillingCompleteForMonth ? 'bg-emerald-100 text-emerald-800' : 'bg-indigo-600 text-white' }}">
                            {{ $flatsBilledCount }}/{{ $flatCount }}
                        </span>
                    </div>
                    <p class="mt-2.5 text-xs text-slate-600 font-medium">
                        {{ $isBillingCompleteForMonth ? __('dashboard.bills_complete') : __('dashboard.bills_incomplete') }}
                    </p>
                    <span class="mt-2 inline-flex items-center gap-1 text-[11px] font-bold text-indigo-600 group-hover:text-indigo-800">
                        {{ __('dashboard.generate_bills_now') }} &rarr;
                    </span>
                </a>

                {{-- Maintenance Issues --}}
                <a href="{{ route('maintenance-requests.index') }}"
                   class="group relative overflow-hidden rounded-2xl border {{ $urgentTicketsCount > 0 ? 'border-red-300 bg-red-50/40 hover:bg-red-50/80 ring-1 ring-red-400/20' : 'border-slate-200/90 bg-white hover:bg-slate-50' }} p-4 shadow-xs transition hover:shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-800 flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-rose-100 text-rose-700 text-xs">🛠️</span>
                            {{ __('dashboard.urgent_tickets') }}
                        </span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $urgentTicketsCount > 0 ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-600' }}">
                            {{ $openTicketsCount }}
                        </span>
                    </div>
                    <p class="mt-2.5 text-xs text-slate-600 font-medium">
                        {{ __('dashboard.open_tickets', ['open' => $openTicketsCount, 'urgent' => $urgentTicketsCount]) }}
                    </p>
                    <span class="mt-2 inline-flex items-center gap-1 text-[11px] font-bold text-indigo-600 group-hover:text-indigo-800">
                        {{ __('maintenance.view_requests') }} &rarr;
                    </span>
                </a>
            </div>
        </div>

        {{-- 4. Analytics & Defaulter Watchlist (2 Columns) --}}
        <div class="mb-8 grid gap-6 lg:grid-cols-12">
            {{-- Left Column: Billing Compliance (5 cols) --}}
            <div class="space-y-6 lg:col-span-5">
                <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">{{ __('dashboard.billing_compliance') }}</h3>
                            <p class="text-xs text-slate-500">{{ now()->format('F Y') }} payment status</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                            {{ $flatCount }} Flats
                        </span>
                    </div>

                    <div class="mt-6 space-y-4">
                        @php
                            $totalTracked = max(1, $flatCount);
                            $paidPct = round(($paidBillsCount / $totalTracked) * 100);
                            $partPct = round(($partialBillsCount / $totalTracked) * 100);
                            $unpaidPct = round(($unpaidBillsCount / $totalTracked) * 100);
                        @endphp
                        <div class="flex h-3 w-full overflow-hidden rounded-full bg-slate-100">
                            <div style="width: {{ $paidPct }}%" class="bg-emerald-500 transition-all duration-500" title="{{ __('dashboard.paid_flats') }}: {{ $paidBillsCount }}"></div>
                            <div style="width: {{ $partPct }}%" class="bg-amber-400 transition-all duration-500" title="{{ __('dashboard.partially_paid_flats') }}: {{ $partialBillsCount }}"></div>
                            <div style="width: {{ $unpaidPct }}%" class="bg-rose-500 transition-all duration-500" title="{{ __('dashboard.unpaid_flats') }}: {{ $unpaidBillsCount }}"></div>
                        </div>

                        <div class="grid grid-cols-3 gap-2.5 pt-2 text-center text-xs">
                            <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3">
                                <span class="font-bold text-emerald-800 text-lg">{{ $paidBillsCount }}</span>
                                <p class="text-[11px] text-emerald-700 font-semibold mt-0.5">{{ __('dashboard.paid_flats') }}</p>
                                <span class="text-[10px] text-emerald-600 font-medium">{{ $paidPct }}%</span>
                            </div>
                            <div class="rounded-xl border border-amber-100 bg-amber-50/60 p-3">
                                <span class="font-bold text-amber-800 text-lg">{{ $partialBillsCount }}</span>
                                <p class="text-[11px] text-amber-700 font-semibold mt-0.5">{{ __('dashboard.partially_paid_flats') }}</p>
                                <span class="text-[10px] text-amber-600 font-medium">{{ $partPct }}%</span>
                            </div>
                            <div class="rounded-xl border border-rose-100 bg-rose-50/60 p-3">
                                <span class="font-bold text-rose-800 text-lg">{{ $unpaidBillsCount }}</span>
                                <p class="text-[11px] text-rose-700 font-semibold mt-0.5">{{ __('dashboard.unpaid_flats') }}</p>
                                <span class="text-[10px] text-rose-600 font-medium">{{ $unpaidPct }}%</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-between border-t border-slate-100 pt-4 text-xs">
                        <span class="text-slate-500">View detailed breakdown</span>
                        <a href="{{ route('reports.collections') }}" class="font-semibold text-indigo-600 hover:underline">
                            {{ __('reports.collections') }} &rarr;
                        </a>
                    </div>
                </div>
            </div>

            {{-- Right Column: Top Overdue / Defaulters Watchlist (7 cols) --}}
            <div class="lg:col-span-7">
                <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">{{ __('dashboard.defaulter_watchlist') }}</h3>
                            <p class="text-xs text-slate-500">Top overdue balances requiring attention</p>
                        </div>
                        <a href="{{ route('reports.owner-dues') }}" class="text-xs font-semibold text-indigo-600 hover:underline">
                            {{ __('reports.owner_dues') }} &rarr;
                        </a>
                    </div>

                    @if ($topOverdueFlats->isEmpty())
                        <div class="py-10 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-2xl">
                                ✨
                            </div>
                            <p class="mt-3 text-sm font-semibold text-slate-800">{{ __('dashboard.no_defaulters') }}</p>
                            <p class="text-xs text-slate-400">All flats are currently up to date on payments.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-slate-200 text-slate-400 uppercase tracking-wider">
                                        <th class="pb-3 font-semibold">{{ __('dashboard.flat_owner') }}</th>
                                        <th class="pb-3 font-semibold text-right">{{ __('dashboard.overdue_amount') }}</th>
                                        <th class="pb-3 font-semibold text-right">{{ __('masters.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($topOverdueFlats as $item)
                                        <tr class="hover:bg-slate-50/80 transition">
                                            <td class="py-3">
                                                <div class="font-bold text-slate-900">{{ $item['flat']->number }}</div>
                                                <div class="text-[11px] text-slate-500">{{ $item['flat']->owner?->user?->name ?? '—' }}</div>
                                            </td>
                                            <td class="py-3 text-right font-extrabold tabular-nums text-red-600">
                                                ৳{{ number_format((float) $item['due'], 2) }}
                                            </td>
                                            <td class="py-3 text-right">
                                                <a href="{{ route('flats.statement', $item['flat']) }}"
                                                   class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-200 transition">
                                                    {{ __('dashboard.view_ledger') }} &rarr;
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
        </div>

        {{-- 5. Recent Activity Stream (2 Columns) --}}
        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Recent Confirmed Payments --}}
            <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">{{ __('dashboard.recent_payments') }}</h3>
                    <a href="{{ route('payments.index') }}" class="text-xs font-semibold text-indigo-600 hover:underline">
                        {{ __('nav.payments') }} &rarr;
                    </a>
                </div>

                @if ($recentStaffPayments->isEmpty())
                    <p class="py-8 text-center text-xs text-slate-400">{{ __('dashboard.no_recent_activity') }}</p>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($recentStaffPayments as $pay)
                            <div class="flex items-center justify-between py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 font-bold text-emerald-700 text-sm">
                                        ৳
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-slate-900">
                                            {{ $pay->flat?->number ?? 'Flat' }} &bull; <span class="font-mono text-slate-600">{{ $pay->receipt_no }}</span>
                                        </p>
                                        <p class="text-[11px] text-slate-400">
                                            {{ $pay->received_on->format('M d, Y') }} &bull; <span class="capitalize">{{ $pay->method->value }}</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold text-xs tabular-nums text-emerald-600">
                                        +৳{{ number_format((float) $pay->amount, 2) }}
                                    </span>
                                    <a href="{{ route('payments.receipt', $pay) }}" target="_blank"
                                       class="block text-[11px] font-semibold text-indigo-600 hover:underline">
                                        {{ __('dashboard.print_receipt') }}
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Recent Maintenance Requests --}}
            <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">{{ __('dashboard.recent_tickets') }}</h3>
                    <a href="{{ route('maintenance-requests.index') }}" class="text-xs font-semibold text-indigo-600 hover:underline">
                        {{ __('maintenance.view_requests') }} &rarr;
                    </a>
                </div>

                @if ($recentStaffTickets->isEmpty())
                    <p class="py-8 text-center text-xs text-slate-400">{{ __('dashboard.no_issues_reported') }}</p>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($recentStaffTickets as $t)
                            <div class="py-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $t->status->badgeClasses() }}">
                                            {{ $t->status->label() }}
                                        </span>
                                        <span class="text-xs font-bold text-slate-900">{{ $t->flat?->number }}</span>
                                    </div>
                                    <span class="text-[11px] text-slate-400">{{ $t->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="mt-1 text-xs font-medium text-slate-700 truncate">{{ $t->title }}</p>
                                <div class="mt-1 flex items-center gap-2 text-[11px] text-slate-500">
                                    <span>{{ $t->category->label() }}</span> &bull;
                                    <span class="font-semibold {{ $t->priority->badgeClasses() }} rounded px-1.5 py-0.5 text-[10px]">{{ $t->priority->label() }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

    @else
        {{-- ========================================================================= --}}
        {{-- RESIDENT / TENANT / OWNER SELF-SERVICE PORTAL                             --}}
        {{-- ========================================================================= --}}

        {{-- Resident Greeting & Flat Selector Header --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200/80 pb-5">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                    {{ __('dashboard.welcome_back', ['name' => auth()->user()->name]) }}
                </h1>
                @if ($selectedFlat)
                    <p class="mt-1 text-xs text-slate-500">
                        🏢 {{ $selectedFlat->building->displayName() }} &bull; {{ __('maintenance.flat') }}: <span class="font-bold text-slate-800">{{ $selectedFlat->number }}</span>
                    </p>
                @endif
            </div>

            @if ($flats->count() > 1)
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-500">{{ __('dashboard.my_flats') }}:</span>
                    <div class="inline-flex rounded-xl shadow-xs border border-slate-200 overflow-hidden bg-white p-0.5">
                        @foreach ($flats as $f)
                            <button type="button"
                                    wire:click="selectFlat({{ $f->id }})"
                                    class="px-3.5 py-1.5 text-xs font-bold rounded-lg transition {{ $selectedFlatId === $f->id ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-700 hover:bg-slate-100' }}">
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
            <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-xs">
                <span class="text-4xl">🏢</span>
                <p class="mt-3 text-sm font-semibold text-slate-700">{{ __('reports.no_flat_linked') }}</p>
            </div>
        @else
            {{-- Emergency Notice Banner if any --}}
            @php($emergencyNotice = $activeNotices->firstWhere('type', App\Enums\NoticeType::Emergency))
            @if ($emergencyNotice)
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50/90 p-4 text-red-900 shadow-xs flex items-start justify-between">
                    <div class="flex items-start gap-3">
                        <span class="text-2xl">🚨</span>
                        <div>
                            <h3 class="font-bold text-red-800">{{ $emergencyNotice->title }}</h3>
                            <p class="mt-1 text-xs text-red-700 line-clamp-2">{{ $emergencyNotice->content }}</p>
                        </div>
                    </div>
                    <button type="button" wire:click="openNoticeModal({{ $emergencyNotice->id }})"
                            class="shrink-0 text-xs font-bold uppercase tracking-wider text-red-700 hover:text-red-900 underline ml-4 cursor-pointer">
                        {{ __('dashboard.view_notice') }}
                    </button>
                </div>
            @endif

            {{-- 1. Glanceable Financial Standing Hero Card --}}
            <div class="mb-8 rounded-3xl border {{ bccomp($totalDue, '0.00', 2) > 0 ? 'border-amber-200 bg-gradient-to-br from-amber-50/80 via-orange-50/50 to-amber-100/40' : 'border-emerald-200 bg-gradient-to-br from-emerald-50/80 via-teal-50/50 to-emerald-100/40' }} p-6 sm:p-8 shadow-xs">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            @if (bccomp($totalDue, '0.00', 2) > 0)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-600 px-3 py-1 text-xs font-bold text-white shadow-xs">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <circle cx="12" cy="12" r="10" />
                                        <line x1="12" x2="12" y1="8" y2="12" />
                                        <line x1="12" x2="12.01" y1="16" y2="16" />
                                    </svg>
                                    {{ __('dashboard.total_due') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-600 px-3 py-1 text-xs font-bold text-white shadow-xs">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    {{ __('dashboard.status_all_clear') }}
                                </span>
                            @endif
                        </div>

                        <p class="mt-4 text-4xl font-extrabold tracking-tight tabular-nums {{ bccomp($totalDue, '0.00', 2) > 0 ? 'text-slate-900' : 'text-emerald-800' }}">
                            ৳{{ number_format((float) $totalDue, 2) }}
                        </p>

                        {{-- 3-Way Sub-Breakdown Cards --}}
                        <div class="mt-5 flex flex-wrap items-center gap-3 text-xs">
                            <div class="rounded-xl bg-white/90 border border-slate-200/60 px-3.5 py-2 shadow-2xs">
                                <span class="text-slate-500">{{ __('dashboard.arrears') }}:</span>
                                <strong class="ml-1 font-mono text-slate-900">৳{{ number_format((float) $arrears, 2) }}</strong>
                            </div>
                            <div class="rounded-xl bg-white/90 border border-slate-200/60 px-3.5 py-2 shadow-2xs">
                                <span class="text-slate-500">{{ __('dashboard.current_bill') }}:</span>
                                <strong class="ml-1 font-mono text-slate-900">৳{{ number_format((float) $currentMonthCharges, 2) }}</strong>
                            </div>
                            <div class="rounded-xl bg-white/90 border border-slate-200/60 px-3.5 py-2 shadow-2xs">
                                <span class="text-slate-500">{{ __('dashboard.advance_available') }}:</span>
                                <strong class="ml-1 font-mono text-emerald-700">৳{{ number_format((float) $advanceHeld, 2) }}</strong>
                            </div>
                        </div>
                    </div>

                    {{-- Primary CTAs --}}
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('flats.statement', $selectedFlat) }}"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 transition cursor-pointer">
                            <svg class="h-3.5 w-3.5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <polyline points="14 2 14 8 20 8" />
                            </svg>
                            {{ __('dashboard.view_statement') }}
                        </a>
                        <button type="button"
                                wire:click="openPaymentModal('{{ $totalDue }}')"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white shadow-md hover:bg-emerald-500 hover:shadow-lg transition cursor-pointer">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect width="20" height="14" x="2" y="5" rx="2" />
                                <line x1="2" x2="22" y1="10" y2="10" />
                            </svg>
                            {{ __('dashboard.pay_now') }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- 2. Two-Column Interactive Content Area --}}
            <div class="grid gap-8 lg:grid-cols-3">
                {{-- Left 2 Columns: Latest Bill, Payments, Submissions --}}
                <div class="space-y-8 lg:col-span-2">
                    {{-- Latest Bill Preview --}}
                    <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">{{ __('dashboard.latest_bill') }}</h2>
                                @if ($latestBill)
                                    <p class="text-xs text-slate-500">
                                        {{ __('dashboard.bill_no') }}: <span class="font-mono font-semibold text-slate-700">{{ $latestBill->bill_no }}</span> &bull; {{ \Carbon\Carbon::parse($latestBill->billing_month)->format('F Y') }}
                                    </p>
                                @endif
                            </div>
                            @if ($latestBill)
                                <div class="flex items-center gap-2.5">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $latestBill->status->badgeClasses() }}">
                                        {{ $latestBill->status->label() }}
                                    </span>
                                    <a href="{{ route('bills.print', $latestBill) }}" target="_blank"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition cursor-pointer">
                                        <svg class="h-3.5 w-3.5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="6 9 6 2 18 2 18 9" />
                                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                                            <rect width="12" height="8" x="6" y="14" />
                                        </svg>
                                        {{ __('dashboard.print_bill') }}
                                    </a>
                                </div>
                            @endif
                        </div>

                        @if (! $latestBill)
                            <p class="py-8 text-center text-xs text-slate-400">{{ __('dashboard.no_bills_yet') }}</p>
                        @else
                            <div class="mt-4 divide-y divide-slate-100 text-xs">
                                @foreach ($latestBill->items as $item)
                                    <div class="flex justify-between py-2.5">
                                        <span class="text-slate-600 font-medium">{{ $item->charge_name }}</span>
                                        <span class="font-bold tabular-nums text-slate-900">৳{{ number_format((float) $item->amount, 2) }}</span>
                                    </div>
                                @endforeach
                                <div class="flex justify-between pt-3 text-sm font-extrabold text-slate-900">
                                    <span>{{ __('billing.total_charges') }}</span>
                                    <span class="tabular-nums">৳{{ number_format((float) $latestBill->total_amount, 2) }}</span>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Recent Payments History --}}
                    <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs">
                        <h2 class="mb-4 text-base font-bold text-slate-900">{{ __('dashboard.recent_payments') }}</h2>

                        @if ($recentPayments->isEmpty())
                            <p class="py-8 text-center text-xs text-slate-400">{{ __('dashboard.no_payments_yet') }}</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr class="border-b border-slate-200 text-slate-400 uppercase tracking-wider">
                                            <th class="pb-3 font-semibold">{{ __('dashboard.receipt_no') }}</th>
                                            <th class="pb-3 font-semibold">{{ __('dashboard.payment_date') }}</th>
                                            <th class="pb-3 font-semibold">{{ __('dashboard.method') }}</th>
                                            <th class="pb-3 font-semibold text-right">{{ __('dashboard.amount') }}</th>
                                            <th class="pb-3 font-semibold text-right">{{ __('masters.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach ($recentPayments as $pay)
                                            <tr class="hover:bg-slate-50/80 transition">
                                                <td class="py-3 font-mono font-bold text-slate-800">{{ $pay->receipt_no }}</td>
                                                <td class="py-3 text-slate-600">{{ $pay->received_on->format('M d, Y') }}</td>
                                                <td class="py-3 capitalize text-slate-600 font-medium">{{ $pay->method->value }}</td>
                                                <td class="py-3 text-right font-extrabold tabular-nums text-emerald-600">৳{{ number_format((float) $pay->amount, 2) }}</td>
                                                <td class="py-3 text-right">
                                                    <a href="{{ route('payments.receipt', $pay) }}" target="_blank"
                                                       class="font-semibold text-indigo-600 hover:underline">
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

                    {{-- My Payment Submissions Tracker --}}
                    <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-base font-bold text-slate-900">{{ __('billing.payment_submissions') }}</h2>
                            <button type="button"
                                    wire:click="openPaymentModal"
                                    class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100 transition cursor-pointer">
                                <span>+</span> {{ __('billing.submit_payment') }}
                            </button>
                        </div>

                        @if ($mySubmissions->isEmpty())
                            <p class="py-8 text-center text-xs text-slate-400">{{ __('billing.no_submissions') }}</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr class="border-b border-slate-200 text-slate-400 uppercase tracking-wider">
                                            <th class="pb-3 font-semibold">{{ __('billing.reference') }}</th>
                                            <th class="pb-3 font-semibold">{{ __('dashboard.payment_date') }}</th>
                                            <th class="pb-3 font-semibold">{{ __('dashboard.method') }}</th>
                                            <th class="pb-3 font-semibold text-right">{{ __('dashboard.amount') }}</th>
                                            <th class="pb-3 font-semibold text-right">{{ __('masters.status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach ($mySubmissions as $sub)
                                            <tr class="hover:bg-slate-50/80 transition">
                                                <td class="py-3 font-mono font-semibold text-slate-800">{{ $sub->reference_number }}</td>
                                                <td class="py-3 text-slate-600">{{ $sub->payment_date->format('M d, Y') }}</td>
                                                <td class="py-3 uppercase text-slate-600 font-medium">{{ $sub->payment_method->value }}</td>
                                                <td class="py-3 text-right font-extrabold tabular-nums text-slate-900">৳{{ number_format((float) $sub->amount, 2) }}</td>
                                                <td class="py-3 text-right">
                                                    @if ($sub->status === \App\Enums\PaymentSubmissionStatus::Pending)
                                                        <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-2 py-0.5 text-xs font-bold text-amber-700">
                                                            {{ __('billing.pending') }}
                                                        </span>
                                                    @elseif ($sub->status === \App\Enums\PaymentSubmissionStatus::Approved)
                                                        <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-xs font-bold text-emerald-700">
                                                            {{ __('billing.approved') }}
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center rounded-full bg-red-50 border border-red-200 px-2 py-0.5 text-xs font-bold text-red-700" title="{{ $sub->rejection_reason }}">
                                                            {{ __('billing.rejected') }}
                                                        </span>
                                                    @endif
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
                    <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-base font-bold text-slate-900">{{ __('dashboard.notices_announcements') }}</h2>
                            <span class="text-sm">📋</span>
                        </div>

                        @if ($activeNotices->isEmpty())
                            <p class="py-8 text-center text-xs text-slate-400">{{ __('dashboard.no_notices') }}</p>
                        @else
                            <div class="divide-y divide-slate-100">
                                @foreach ($activeNotices as $noticeItem)
                                    <div class="py-3.5">
                                        <div class="flex items-center justify-between">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $noticeItem->type->badgeClasses() }}">
                                                {{ $noticeItem->type->label() }}
                                            </span>
                                            <span class="text-[11px] text-slate-400">{{ $noticeItem->published_at->diffForHumans() }}</span>
                                        </div>
                                        <h4 class="mt-1.5 text-xs font-bold text-slate-900">
                                            @if ($noticeItem->is_pinned) 📌 @endif {{ $noticeItem->title }}
                                        </h4>
                                        <p class="mt-1 text-xs text-slate-500 line-clamp-2 leading-relaxed">{{ $noticeItem->content }}</p>
                                        <button type="button" wire:click="openNoticeModal({{ $noticeItem->id }})"
                                                class="mt-2 text-xs font-bold text-indigo-600 hover:text-indigo-800 cursor-pointer">
                                            {{ __('dashboard.view_notice') }} &rarr;
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Maintenance Requests Tracker --}}
                    <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-base font-bold text-slate-900">{{ __('dashboard.maintenance_requests') }}</h2>
                            <button type="button" wire:click="openTicketModal"
                                    class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-2.5 py-1.5 text-xs font-bold text-indigo-700 hover:bg-indigo-100 transition cursor-pointer">
                                <span>+</span> {{ __('maintenance.report_issue') }}
                            </button>
                        </div>

                        @if ($myTickets->isEmpty())
                            <p class="py-8 text-center text-xs text-slate-400">{{ __('dashboard.no_issues_reported') }}</p>
                        @else
                            <div class="divide-y divide-slate-100">
                                @foreach ($myTickets as $ticket)
                                    <div class="py-3.5">
                                        <div class="flex items-center justify-between">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $ticket->status->badgeClasses() }}">
                                                {{ $ticket->status->label() }}
                                            </span>
                                            <span class="text-[11px] text-slate-400">{{ $ticket->created_at->format('M d') }}</span>
                                        </div>
                                        <h4 class="mt-1.5 text-xs font-bold text-slate-900">{{ $ticket->title }}</h4>
                                        <div class="mt-1 flex items-center gap-2 text-xs text-slate-500">
                                            <span>{{ $ticket->category->label() }}</span> &bull;
                                            <span class="font-semibold {{ $ticket->priority->badgeClasses() }} rounded px-1.5 py-0.5 text-[10px]">{{ $ticket->priority->label() }}</span>
                                        </div>
                                        @if ($ticket->resolution_notes)
                                            <div class="mt-2.5 rounded-xl bg-slate-50 border border-slate-200/60 p-2.5 text-xs text-slate-700">
                                                <span class="font-bold text-slate-900">{{ __('maintenance.resolution_notes') }}:</span>
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
                            <select wire:model="ticketCategory" class="block w-full rounded-md border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs">
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                                @endforeach
                            </select>
                        </x-form.field>

                        <x-form.field :label="__('maintenance.priority')" name="ticketPriority" required>
                            <select wire:model="ticketPriority" class="block w-full rounded-md border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs">
                                @foreach ($priorities as $pr)
                                    <option value="{{ $pr->value }}">{{ $pr->label() }}</option>
                                @endforeach
                            </select>
                        </x-form.field>

                        <x-form.field :label="__('maintenance.description')" name="ticketDescription" required class="sm:col-span-2">
                            <textarea wire:model="ticketDescription" rows="4" class="block w-full rounded-md border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs" placeholder="Please describe the issue in detail..." required></textarea>
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
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $viewingNotice->type->badgeClasses() }}">
                            {{ $viewingNotice->type->label() }}
                        </span>
                        @if ($viewingNotice->is_pinned)
                            <span class="text-xs font-semibold text-amber-700">📌 {{ __('masters.pinned') }}</span>
                        @endif
                        <span class="text-xs text-slate-400">&bull; {{ $viewingNotice->published_at->format('M d, Y H:i') }}</span>
                    </div>

                    <div class="prose prose-sm text-slate-700 whitespace-pre-line text-xs leading-relaxed">
                        {{ $viewingNotice->content }}
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-6 py-3">
                    <x-ui.button variant="secondary" wire:click="closeNoticeModal">{{ __('masters.done') }}</x-ui.button>
                </div>
            </x-ui.modal>
        @endif

        {{-- Submit Payment Modal --}}
        @if ($showPaymentModal)
            <x-ui.modal :title="__('billing.submit_payment')">
                <form wire:submit.prevent="submitPayment">
                    <div class="grid grid-cols-1 gap-4 px-6 py-5 sm:grid-cols-2">
                        <x-form.field :label="__('dashboard.amount')" name="submissionAmount" required>
                            <input type="number" step="0.01" wire:model="submissionAmount" class="block w-full rounded-md border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs" placeholder="0.00" required>
                        </x-form.field>

                        <x-form.field :label="__('dashboard.method')" name="submissionMethod" required>
                            <select wire:model="submissionMethod" class="block w-full rounded-md border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs">
                                @foreach ($methods as $m)
                                    <option value="{{ $m->value }}">{{ __('billing.methods.'.$m->value) }}</option>
                                @endforeach
                            </select>
                        </x-form.field>

                        <x-form.field :label="__('billing.reference')" name="submissionReference" required>
                            <input type="text" wire:model="submissionReference" class="block w-full rounded-md border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs" placeholder="e.g. TrxID / Deposit Ref" required>
                        </x-form.field>

                        <x-form.field :label="__('dashboard.payment_date')" name="submissionDate" required>
                            <input type="date" wire:model="submissionDate" class="block w-full rounded-md border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs" required>
                        </x-form.field>

                        <x-form.field :label="__('billing.deposit_slip')" name="submissionSlip" class="sm:col-span-2">
                            <input type="file" wire:model="submissionSlip" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            @if ($submissionSlip)
                                <p class="mt-1 text-xs text-slate-500">{{ $submissionSlip->getClientOriginalName() }}</p>
                            @endif
                        </x-form.field>

                        <x-form.field :label="__('billing.payment_notes')" name="submissionNotes" class="sm:col-span-2">
                            <textarea wire:model="submissionNotes" rows="2" class="block w-full rounded-md border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs" placeholder="{{ __('billing.payment_notes') }}"></textarea>
                        </x-form.field>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                        <x-ui.button variant="secondary" wire:click="cancelPaymentModal">{{ __('masters.cancel') }}</x-ui.button>
                        <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('billing.submit_payment') }}</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>
        @endif
    @endif
</div>
