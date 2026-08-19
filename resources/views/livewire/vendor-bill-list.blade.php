<div>
    <h1 class="mb-1 text-lg font-semibold text-slate-900">{{ __('expenses.vendor_bills') }}</h1>
    <p class="mb-6 text-sm text-slate-500">{{ __('expenses.vendor_bill_help') }}</p>

    @can('manage', App\Models\Flat::class)
        <form wire:submit="save" class="mb-8 grid gap-4 rounded-lg border border-slate-200 bg-white p-6 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('expenses.vendor') }}</label>
                <select wire:model="vendorId" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                    <option value="">{{ __('expenses.select_vendor') }}</option>
                    @foreach ($vendors as $vendor)
                        <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                    @endforeach
                </select>
                @error('vendorId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

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
                <label class="block text-sm font-medium text-slate-700">{{ __('expenses.bill_date') }}</label>
                <input type="date" wire:model="billDate" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                @error('billDate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('expenses.due_date') }}</label>
                <input type="date" wire:model="dueDate" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                @error('dueDate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('reports.description') }}</label>
                <input type="text" wire:model="description" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <button type="submit" wire:loading.attr="disabled"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50">
                    {{ __('expenses.save_vendor_bill') }}
                </button>
            </div>
        </form>
    @endcan

    @if ($payingBillId)
        <div class="mb-8 rounded-lg border border-slate-300 bg-white p-6">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">{{ __('expenses.settle_bill') }}</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('billing.amount') }}</label>
                    <input type="number" step="0.01" min="0" wire:model="payAmount"
                           class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                    @error('payAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('billing.method') }}</label>
                    <select wire:model="payMethod" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                        @foreach ($methods as $m)
                            <option value="{{ $m->value }}">{{ __('billing.methods.'.$m->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('expenses.paid_on') }}</label>
                    <input type="date" wire:model="payDate" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                </div>
            </div>
            <div class="mt-4 flex gap-3">
                <button wire:click="pay" wire:loading.attr="disabled"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50">
                    {{ __('expenses.confirm_payment') }}
                </button>
                <button wire:click="cancelPayment" type="button"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                    {{ __('expenses.cancel') }}
                </button>
            </div>
        </div>
    @endif

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('expenses.bill_no') }}</th>
                    <th class="px-4 py-3">{{ __('expenses.vendor') }}</th>
                    <th class="px-4 py-3">{{ __('reports.description') }}</th>
                    <th class="px-4 py-3">{{ __('expenses.due_date') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('billing.amount') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('reports.outstanding') }}</th>
                    <th class="px-4 py-3">{{ __('expenses.status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($bills as $bill)
                    <tr>
                        <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $bill->bill_no }}</td>
                        <td class="px-4 py-2">{{ $bill->vendor->name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $bill->description }}</td>
                        <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ $bill->due_date->format('d M Y') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $bill->total_amount, 2) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $bill->outstandingAmount(), 2) }}</td>
                        <td class="px-4 py-2">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs',
                                'bg-emerald-50 text-emerald-700' => $bill->status === App\Enums\VendorBillStatus::Paid,
                                'bg-amber-50 text-amber-700' => $bill->status === App\Enums\VendorBillStatus::PartiallyPaid,
                                'bg-slate-100 text-slate-600' => $bill->status === App\Enums\VendorBillStatus::Unpaid,
                            ])>{{ __('expenses.statuses.'.$bill->status->value) }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            @can('manage', App\Models\Flat::class)
                                @if ($bill->status !== App\Enums\VendorBillStatus::Paid)
                                    <button wire:click="startPayment({{ $bill->id }})"
                                            class="text-slate-600 hover:text-slate-900">{{ __('expenses.pay') }}</button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-6 text-center text-slate-400">{{ __('expenses.no_vendor_bills') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $bills->links() }}</div>
</div>
