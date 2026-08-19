<div>
    <h1 class="mb-1 text-lg font-semibold text-slate-900">{{ __('nav.accounts') }}</h1>
    <p class="mb-6 text-sm text-slate-500">{{ __('reports.derived_from_ledger') }}</p>

    <div class="space-y-6">
        @foreach ($groups as $group)
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                    <span class="font-mono text-xs text-slate-500">{{ $group->code }}</span>
                    <span class="ml-2 font-semibold text-slate-900">
                        {{ app()->getLocale() === 'bn' ? ($group->name_bn ?? $group->name) : $group->name }}
                    </span>
                </div>
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($group->children->sortBy('code') as $account)
                            <tr>
                                <td class="w-24 px-4 py-2 font-mono text-xs text-slate-500">{{ $account->code }}</td>
                                <td class="px-4 py-2">
                                    {{ app()->getLocale() === 'bn' ? ($account->name_bn ?? $account->name) : $account->name }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ number_format((float) ($balances[$account->id] ?? '0.00'), 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</div>
