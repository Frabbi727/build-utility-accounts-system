<div class="max-w-xl">
    <h1 class="mb-1 text-lg font-semibold text-slate-900">{{ __('billing.record_payment') }}</h1>
    <p class="mb-6 text-sm text-slate-500">{{ __('billing.allocation_help') }}</p>

    @if ($lastPaymentId !== null)
        <div class="mb-6 flex items-center justify-between rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
            <span class="text-sm text-slate-600">{{ __('billing.receipt_ready') }}</span>
            <a href="{{ route('payments.receipt', $lastPaymentId) }}" target="_blank"
               class="text-sm font-medium text-slate-900 underline">{{ __('billing.view_receipt') }}</a>
        </div>
    @endif

    <x-ui.notice :message="$notice" :type="$noticeType" class="mb-6" />

    <form wire:submit="askSave" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6">
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('billing.flat') }}</label>
            <select wire:model="flatId" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                <option value="">{{ __('billing.select_flat') }}</option>
                @foreach ($flats as $flat)
                    <option value="{{ $flat->id }}">{{ $flat->building->name }} — {{ $flat->number }}</option>
                @endforeach
            </select>
            @error('flatId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
            <label class="block text-sm font-medium text-slate-700">{{ __('billing.received_on') }}</label>
            <input type="date" wire:model="receivedOn" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
            @error('receivedOn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('billing.reference') }}</label>
            <input type="text" wire:model="reference" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
            @error('reference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled"
                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50">
            {{ __('billing.save_payment') }}
        </button>
    </form>

    @php($preview = $this->isConfirming('save') ? $this->allocationPreview() : null)

    @if ($preview !== null)
        <x-ui.confirm-dialog
            :title="__('billing.payment_confirm_title')"
            :message="__('billing.payment_confirm_message')"
            :confirm-label="__('billing.save_payment')"
            variant="primary"
        >
            <div class="space-y-2">
                <div>
                    {{ __('billing.flat') }}:
                    <span class="font-medium">{{ $preview['flat']->building->name }} — {{ $preview['flat']->number }}</span>
                </div>
                <div>
                    {{ __('billing.amount') }}:
                    <span class="font-medium tabular-nums"><x-money :amount="$amount" /></span>
                    ({{ __('billing.methods.'.$method) }})
                </div>

                @if ($preview['lines'] !== [])
                    <div class="pt-2 font-medium">{{ __('billing.will_settle') }}</div>
                    <ul class="space-y-1">
                        @foreach ($preview['lines'] as $line)
                            <li class="flex justify-between gap-4">
                                <span>{{ $line['bill']->bill_no }}
                                    ({{ $line['bill']->billing_month->translatedFormat('F Y') }})</span>
                                <span class="tabular-nums"><x-money :amount="$line['amount']" /></span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="pt-2">{{ __('billing.settles_nothing') }}</p>
                @endif

                @if (bccomp($preview['advance'], '0', 2) > 0)
                    <p class="pt-2 text-amber-700">
                        {{ __('billing.goes_to_advance', ['amount' => $preview['advance']]) }}
                    </p>
                @endif
            </div>
        </x-ui.confirm-dialog>
    @endif
</div>
