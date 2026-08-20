<div class="space-y-6">
    <x-ui.page-header :title="__('accounting.opening_balances')" :description="__('accounting.opening_help')" />

    <x-ui.notice :message="$notice" :type="$noticeType" />

    @if ($alreadyPosted)
        <x-ui.empty-state :title="__('accounting.already_posted')"
                          :description="__('accounting.already_posted_help', ['ref' => __('accounting.opening_balances')])">
            <x-slot:actions>
                <x-ui.button :href="route('reports.trial-balance')">{{ __('nav.trial_balance') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.empty-state>
    @else
        <form wire:submit="askPost" class="space-y-6">
            <x-ui.card>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.field :label="__('accounting.as_of')" name="asOf" required>
                        <x-form.input type="date" wire:model="asOf" />
                    </x-form.field>

                    <x-form.field :label="__('accounting.cash_in_hand')" name="cash" required>
                        <x-form.input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="cash" />
                    </x-form.field>

                    <x-form.field :label="__('accounting.bank')" name="bank" required>
                        <x-form.input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="bank" />
                    </x-form.field>

                    <x-form.field :label="__('accounting.accounts_payable')" name="payables" required>
                        <x-form.input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="payables" />
                    </x-form.field>

                    <x-form.field :label="__('accounting.security_deposits')" name="deposits" required>
                        <x-form.input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="deposits" />
                    </x-form.field>
                </div>
            </x-ui.card>

            @if ($flats->isNotEmpty())
                <x-ui.card :title="__('accounting.flat_dues')">
                    <p class="mb-4 text-sm text-slate-500">{{ __('accounting.flat_dues_help') }}</p>

                    <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($flats as $flat)
                            <x-form.field :label="$flat->number" name="flatDues.{{ $flat->id }}">
                                <x-form.input type="number" step="0.01" min="0"
                                              wire:model.live.debounce.500ms="flatDues.{{ $flat->id }}" />
                            </x-form.field>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif

            <x-ui.card>
                <dl class="grid gap-3 text-sm sm:grid-cols-3">
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">{{ __('accounting.total_debits') }}</dt>
                        <dd class="font-medium text-slate-900"><x-money :amount="$totals['debits']" /></dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">{{ __('accounting.total_credits') }}</dt>
                        <dd class="font-medium text-slate-900"><x-money :amount="$totals['credits']" /></dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">{{ __('accounting.general_fund_balancing') }}</dt>
                        <dd class="font-semibold text-slate-900"><x-money :amount="$totals['fund']" /></dd>
                    </div>
                </dl>
            </x-ui.card>

            <div class="flex justify-end">
                <x-ui.button type="submit" wire:loading.attr="disabled">
                    {{ __('accounting.post_opening_balances') }}
                </x-ui.button>
            </div>
        </form>
    @endif

    @if ($this->isConfirming('post'))
        <x-ui.confirm-dialog
            :title="__('accounting.opening_confirm_title')"
            :message="__('accounting.opening_confirm_message')"
            :confirm-label="__('accounting.post_opening_balances')"
            variant="primary"
        >
            <div class="space-y-1">
                <div class="flex justify-between gap-4">
                    <span>{{ __('accounting.total_debits') }}</span>
                    <span class="font-medium tabular-nums"><x-money :amount="$totals['debits']" /></span>
                </div>
                <div class="flex justify-between gap-4">
                    <span>{{ __('accounting.total_credits') }}</span>
                    <span class="font-medium tabular-nums"><x-money :amount="$totals['credits']" /></span>
                </div>
                <div class="flex justify-between gap-4 border-t border-slate-200 pt-1">
                    <span>{{ __('accounting.general_fund_balancing') }}</span>
                    <span class="font-medium tabular-nums"><x-money :amount="$totals['fund']" /></span>
                </div>
            </div>
        </x-ui.confirm-dialog>
    @endif
</div>
