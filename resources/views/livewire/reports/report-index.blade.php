<div>
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-slate-900">{{ __('nav.reports') }}</h1>
        <p class="text-sm text-slate-500">{{ __('reports.derived_from_ledger') }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            'reports.owner-dues' => 'owner_dues',
            'reports.collections' => 'collection_report',
            'reports.expenses' => 'expense_by_category',
            'reports.cash-book' => 'cash_book',
            'reports.income-expenditure' => 'income_expenditure',
            'reports.balance-sheet' => 'balance_sheet',
            'reports.trial-balance' => 'trial_balance',
        ] as $route => $key)
            <a href="{{ route($route) }}"
               class="rounded-lg border border-slate-200 bg-white px-4 py-4 transition hover:border-slate-400">
                <span class="block text-sm font-medium text-slate-900">{{ __('reports.'.$key) }}</span>
                <span class="mt-1 block text-xs text-slate-500">{{ __('reports.'.$key.'_hint') }}</span>
            </a>
        @endforeach
    </div>
</div>
