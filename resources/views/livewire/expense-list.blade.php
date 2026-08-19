<div>
    <h1 class="mb-1 text-lg font-semibold text-slate-900">{{ __('expenses.expenses') }}</h1>
    <p class="mb-6 text-sm text-slate-500">{{ __('expenses.expense_help') }}</p>

    @can('manage', App\Models\Flat::class)
        <form wire:submit="save" class="mb-8 grid gap-4 rounded-lg border border-slate-200 bg-white p-6 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('expenses.category') }}</label>
                <select wire:model="accountId" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                    <option value="">{{ __('expenses.select_category') }}</option>
                    @foreach ($expenseAccounts as $account)
                        <option value="{{ $account->id }}">
                            {{ app()->getLocale() === 'bn' ? ($account->name_bn ?? $account->name) : $account->name }}
                        </option>
                    @endforeach
                </select>
                @error('accountId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('billing.amount') }}</label>
                <input type="number" step="0.01" min="0" wire:model="amount"
                       class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('billing.method') }}</label>
                <select wire:model="method" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                    @foreach ($methods as $m)
                        <option value="{{ $m->value }}">{{ __('billing.methods.'.$m->value) }}</option>
                    @endforeach
                </select>
                @error('method') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('expenses.spent_on') }}</label>
                <input type="date" wire:model="spentOn" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                @error('spentOn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">{{ __('reports.description') }}</label>
                <input type="text" wire:model="description" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <button type="submit" wire:loading.attr="disabled"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50">
                    {{ __('expenses.save_expense') }}
                </button>
            </div>
        </form>
    @endcan

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('expenses.voucher') }}</th>
                    <th class="px-4 py-3">{{ __('reports.date') }}</th>
                    <th class="px-4 py-3">{{ __('expenses.category') }}</th>
                    <th class="px-4 py-3">{{ __('reports.description') }}</th>
                    <th class="px-4 py-3">{{ __('billing.method') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('billing.amount') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($expenses as $expense)
                    <tr>
                        <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $expense->voucher_no }}</td>
                        <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ $expense->spent_on->format('d M Y') }}</td>
                        <td class="px-4 py-2">
                            {{ app()->getLocale() === 'bn' ? ($expense->account->name_bn ?? $expense->account->name) : $expense->account->name }}
                        </td>
                        <td class="px-4 py-2 text-slate-600">{{ $expense->description }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ __('billing.methods.'.$expense->method->value) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $expense->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">{{ __('expenses.no_expenses') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $expenses->links() }}</div>
</div>
