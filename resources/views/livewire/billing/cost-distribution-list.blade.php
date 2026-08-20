<div class="space-y-6">
    <x-ui.page-header :title="__('distributions.distributions')" :description="__('distributions.help')">
        <x-slot:actions>
            @can('create', App\Models\CostDistribution::class)
                <x-ui.button wire:click="create">{{ __('distributions.new_distribution') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('livewire.partials.crud-feedback')

    @if ($distributions->isEmpty())
        <x-ui.empty-state :title="__('distributions.no_distributions')" :description="__('distributions.help')">
            <x-slot:actions>
                @can('create', App\Models\CostDistribution::class)
                    <x-ui.button wire:click="create">{{ __('distributions.new_distribution') }}</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-2">{{ __('distributions.title') }}</th>
                <th class="px-4 py-2">{{ __('billing.month') }}</th>
                <th class="px-4 py-2">{{ __('distributions.basis') }}</th>
                <th class="px-4 py-2 text-right">{{ __('distributions.total_amount') }}</th>
                <th class="px-4 py-2 text-right">{{ __('distributions.allocated') }}</th>
                <th class="px-4 py-2">{{ __('masters.status') }}</th>
                <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
            </x-slot:head>

            @foreach ($distributions as $distribution)
                <tr>
                    <td class="px-4 py-2 font-medium text-slate-900">{{ $distribution->title }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $distribution->billing_month->format('M Y') }}</td>
                    <td class="px-4 py-2 text-slate-600">
                        {{ $distribution->basis->label() }}
                        @if ($distribution->utility)
                            <span class="block text-xs text-slate-500">{{ $distribution->utility->displayName() }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right"><x-money :amount="$distribution->total_amount" /></td>
                    <td class="px-4 py-2 text-right"><x-money :amount="$distribution->lines_sum_amount ?? '0'" blank-zero /></td>
                    <td class="px-4 py-2">
                        @if ($distribution->isApproved())
                            <x-ui.badge variant="success">{{ $distribution->status->label() }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="warning">{{ $distribution->status->label() }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right">
                        <div class="flex justify-end gap-3">
                            <button type="button" wire:click="viewSplit({{ $distribution->id }})"
                                    class="text-sm font-medium text-slate-600 hover:text-slate-900">{{ __('distributions.lines') }}</button>
                            @can('update', $distribution)
                                <button type="button" wire:click="edit({{ $distribution->id }})"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900">{{ __('masters.edit') }}</button>
                            @endcan
                            @can('revert', $distribution)
                                <button type="button" wire:click="askConfirm('revert', {{ $distribution->id }})"
                                        class="text-sm font-medium text-amber-600 hover:text-amber-800">{{ __('distributions.revert') }}</button>
                            @endcan
                            @can('delete', $distribution)
                                <button type="button" wire:click="confirmDelete({{ $distribution->id }})"
                                        class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('masters.delete') }}</button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    @error('lines')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror

    @if ($viewing)
        <x-ui.card :title="__('distributions.lines').' — '.$viewing->title">
            <p class="mb-3 text-sm text-slate-500">
                {{ $viewing->isApproved() ? __('distributions.lines_frozen') : __('distributions.edit_lines_hint') }}
            </p>

            <form wire:submit="saveSplit" class="space-y-4">
                <x-ui.table>
                    <x-slot:head>
                        <th class="px-4 py-2">{{ __('masters.flat') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('distributions.weight') }}</th>
                        <th class="px-4 py-2">{{ __('distributions.amount') }}</th>
                        <th class="px-4 py-2">{{ __('masters.status') }}</th>
                    </x-slot:head>

                    @foreach ($viewing->lines->sortBy('flat_id') as $line)
                        <tr>
                            <td class="px-4 py-2 font-medium text-slate-900">{{ $line->flat->number }}</td>
                            <td class="px-4 py-2 text-right text-slate-600">{{ $line->weight }}</td>
                            <td class="px-4 py-2">
                                @if ($viewing->isApproved())
                                    <x-money :amount="$line->amount" />
                                @else
                                    <x-form.input type="number" step="0.01" min="0"
                                                  wire:model="lineAmounts.{{ $line->id }}" />
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if ($line->isApplied())
                                    <x-ui.badge variant="neutral">{{ $line->bill?->bill_no ?? __('distributions.billed') }}</x-ui.badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>

                <div class="flex items-center justify-between">
                    <p class="text-sm text-slate-600">
                        {{ __('distributions.allocated') }}:
                        <x-money :amount="$viewing->allocatedAmount()" /> / <x-money :amount="$viewing->total_amount" />
                    </p>

                    @if (! $viewing->isApproved())
                        <div class="flex gap-2">
                            @can('update', $viewing)
                                <x-ui.button variant="secondary" wire:click="recalculate({{ $viewing->id }})">{{ __('distributions.recalculate') }}</x-ui.button>
                                <x-ui.button type="submit" variant="secondary">{{ __('masters.save') }}</x-ui.button>
                            @endcan
                            @can('approve', $viewing)
                                <x-ui.button wire:click="askConfirm('approve', {{ $viewing->id }})">{{ __('distributions.approve') }}</x-ui.button>
                            @endcan
                        </div>
                    @endif
                </div>
            </form>
        </x-ui.card>
    @endif

    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('distributions.edit_distribution') : __('distributions.new_distribution')">
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <x-form.field :label="__('distributions.title')" name="title" class="sm:col-span-2" required>
                        <x-form.input wire:model="title" />
                    </x-form.field>

                    <x-form.field :label="__('distributions.description')" name="description" class="sm:col-span-2">
                        <x-form.input wire:model="description" />
                    </x-form.field>

                    <x-form.field :label="__('distributions.total_amount')" name="totalAmount" required>
                        <x-form.input type="number" step="0.01" min="0" wire:model="totalAmount" />
                    </x-form.field>

                    <x-form.field :label="__('billing.month')" name="billingMonth" required>
                        <x-form.input type="month" wire:model="billingMonth" />
                    </x-form.field>

                    <x-form.field :label="__('distributions.basis')" name="basis" required>
                        <x-form.select wire:model.live="basis">
                            @foreach ($bases as $case)
                                <option value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    @if ($basis === 'by_consumption')
                        <x-form.field :label="__('distributions.utility')" name="utilityId" required>
                            <x-form.select wire:model="utilityId" :placeholder="__('masters.select')">
                                @foreach ($utilities as $utility)
                                    <option value="{{ $utility->id }}">{{ $utility->displayName() }}</option>
                                @endforeach
                            </x-form.select>
                        </x-form.field>
                    @endif

                    <x-form.field :label="__('distributions.recovery_account')" name="recoveryAccountId"
                                  :hint="__('distributions.recovery_account_hint')" required>
                        <x-form.select wire:model="recoveryAccountId" :placeholder="__('masters.select_account')">
                            @foreach ($incomeAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->displayName() }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    <x-form.field :label="__('distributions.source_expense')" name="sourceExpenseId"
                                  :hint="__('distributions.source_expense_hint')">
                        <x-form.select wire:model="sourceExpenseId" :placeholder="__('masters.select')">
                            @foreach ($expenses as $expense)
                                <option value="{{ $expense->id }}">
                                    {{ $expense->spent_on->format('d M Y') }} — {{ $expense->description }}
                                </option>
                            @endforeach
                        </x-form.select>
                    </x-form.field>

                    @unless ($this->isEditing())
                        <x-form.field :label="__('distributions.months')" name="months"
                                      :hint="__('distributions.months_hint')" required>
                            <x-form.input type="number" min="1" max="36" wire:model="months" />
                        </x-form.field>
                    @endunless
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                    <x-ui.button variant="secondary" wire:click="cancel">{{ __('masters.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('masters.save') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    @php($pendingDistribution = $this->pendingDistribution())

    @if ($pendingDistribution !== null)
        <x-ui.confirm-dialog
            :title="$pendingDistribution['title']"
            :message="$pendingDistribution['message']"
            :variant="$this->isConfirming('revert') ? 'danger' : 'primary'"
        >
            <div class="space-y-1">
                <div>{{ __('distributions.title') }}:
                    <span class="font-medium">{{ $pendingDistribution['distribution']->title }}</span>
                </div>
                <div>{{ __('billing.amount') }}:
                    <span class="font-medium tabular-nums"><x-money :amount="$pendingDistribution['distribution']->total_amount" /></span>
                </div>
                <div>{{ __('distributions.flats_affected') }}:
                    <span class="font-medium tabular-nums">{{ $pendingDistribution['distribution']->lines_count }}</span>
                </div>
            </div>
        </x-ui.confirm-dialog>
    @endif
</div>
