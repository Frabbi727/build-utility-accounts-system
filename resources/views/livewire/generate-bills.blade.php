<div class="max-w-xl">
    <h1 class="mb-1 text-lg font-semibold text-slate-900">{{ __('billing.generate_monthly_bills') }}</h1>
    <p class="mb-6 text-sm text-slate-500">{{ __('billing.generate_help') }}</p>

    <x-ui.notice :message="$notice" :type="$noticeType" class="mb-6" />

    <div class="space-y-4 rounded-lg border border-slate-200 bg-white p-6">
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('billing.building') }}</label>
            <select wire:model="buildingId" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                @foreach ($buildings as $building)
                    <option value="{{ $building->id }}">{{ $building->name }}</option>
                @endforeach
            </select>
            @error('buildingId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('billing.month') }}</label>
            <input type="month" wire:model.live="month" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
            @error('month') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button wire:click="askGenerate" wire:loading.attr="disabled"
                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50">
            {{ __('billing.generate') }}
        </button>
    </div>

    @if ($this->isConfirming('generate'))
        <x-ui.confirm-dialog
            :title="__('billing.generate_confirm_title')"
            :message="__('billing.generate_confirm_message')"
            :confirm-label="__('billing.generate')"
            variant="primary"
        >
            <div class="space-y-1">
                <div>
                    {{ __('billing.month') }}:
                    <span class="font-medium">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y') }}</span>
                </div>
                <div>
                    {{ __('billing.flats_to_bill') }}:
                    <span class="font-medium tabular-nums">{{ $this->flatsToBill() }}</span>
                </div>

                @if ($pending['readings'] > 0 || $pending['distributions'] > 0)
                    <p class="pt-2 text-red-700">
                        {{ __('billing.generate_leaves_behind', [
                            'readings' => $pending['readings'],
                            'distributions' => $pending['distributions'],
                        ]) }}
                    </p>
                @endif
            </div>
        </x-ui.confirm-dialog>
    @endif
</div>
