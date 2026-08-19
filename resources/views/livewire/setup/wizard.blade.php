<div class="mx-auto max-w-3xl space-y-6">
    <x-ui.page-header :title="__('setup.title')" :description="__('setup.intro')" />

    @php
        $steps = [
            1 => __('setup.step_building'),
            2 => __('setup.step_flats'),
            3 => __('setup.step_owners'),
            4 => __('setup.step_charges'),
            5 => __('setup.step_opening'),
            6 => __('setup.step_done'),
        ];
    @endphp

    <ol class="flex flex-wrap gap-2 text-xs">
        @foreach ($steps as $number => $label)
            <li>
                <button type="button" wire:click="goToStep({{ $number }})"
                        @class([
                            'rounded-full px-3 py-1 font-medium transition',
                            'bg-slate-900 text-white' => $number === $step,
                            'bg-white text-slate-500 ring-1 ring-slate-200 hover:text-slate-900' => $number !== $step,
                        ])>
                    {{ $number }}. {{ $label }}
                </button>
            </li>
        @endforeach
    </ol>

    {{-- Step 1: building --}}
    @if ($step === 1)
        <x-ui.card :title="__('setup.step_building')">
            <p class="mb-4 text-sm text-slate-500">{{ __('setup.step_building_help') }}</p>

            <form wire:submit="saveBuilding" class="grid gap-4 sm:grid-cols-2">
                <x-form.field :label="__('masters.name')" name="buildingName" required>
                    <x-form.input wire:model="buildingName" />
                </x-form.field>

                <x-form.field :label="__('masters.name_bn')" name="buildingNameBn">
                    <x-form.input wire:model="buildingNameBn" />
                </x-form.field>

                <x-form.field :label="__('masters.address')" name="address" class="sm:col-span-2">
                    <x-form.input wire:model="address" />
                </x-form.field>

                <x-form.field :label="__('masters.due_day_of_month')" name="dueDayOfMonth"
                              :hint="__('masters.due_day_help')" required>
                    <x-form.input type="number" min="1" max="28" wire:model="dueDayOfMonth" />
                </x-form.field>

                <div class="flex items-end sm:col-span-2">
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('setup.next') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    {{-- Step 2: flats --}}
    @if ($step === 2)
        <x-ui.card :title="__('setup.step_flats')">
            <p class="mb-4 text-sm text-slate-500">{{ __('setup.step_flats_help') }}</p>

            @if ($flatCount > 0)
                <p class="mb-4 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    {{ __('setup.flats_summary', ['flats' => $flatCount, 'floors' => $floorCount]) }}
                </p>
            @endif

            <form wire:submit="saveFlats" class="grid gap-4 sm:grid-cols-2">
                <x-form.field :label="__('masters.from_level')" name="fromLevel" required>
                    <x-form.input type="number" min="0" wire:model="fromLevel" />
                </x-form.field>

                <x-form.field :label="__('masters.to_level')" name="toLevel" required>
                    <x-form.input type="number" min="0" wire:model="toLevel" />
                </x-form.field>

                <x-form.field :label="__('masters.flat_suffixes')" name="suffixes"
                              :hint="__('masters.flat_suffixes_help')" required>
                    <x-form.input wire:model="suffixes" />
                </x-form.field>

                <x-form.field :label="__('masters.default_size_sqft')" name="defaultSizeSqft" required>
                    <x-form.input type="number" step="0.01" min="0" wire:model="defaultSizeSqft" />
                </x-form.field>

                <div class="flex items-end gap-2 sm:col-span-2">
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('setup.next') }}</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="goToStep(1)">{{ __('setup.back') }}</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="goToStep(3)">{{ __('setup.skip') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    {{-- Step 3: owners --}}
    @if ($step === 3)
        <x-ui.card :title="__('setup.step_owners')">
            <p class="mb-4 text-sm text-slate-500">{{ __('setup.step_owners_help') }}</p>

            <form wire:submit="addOwner" class="mb-4 grid gap-4 sm:grid-cols-3">
                <x-form.field :label="__('setup.owner_name')" name="ownerName" required>
                    <x-form.input wire:model="ownerName" />
                </x-form.field>

                <x-form.field :label="__('masters.phone')" name="ownerPhone">
                    <x-form.input type="tel" wire:model="ownerPhone" />
                </x-form.field>

                <div class="flex items-end">
                    <x-ui.button variant="secondary" type="submit">{{ __('setup.add_owner') }}</x-ui.button>
                </div>
            </form>

            @if ($owners->isEmpty())
                <p class="text-sm text-slate-400">{{ __('setup.nothing_yet') }}</p>
            @else
                <p class="mb-2 text-sm text-slate-700">{{ __('setup.owners_added', ['count' => $owners->count()]) }}</p>
                <ul class="flex flex-wrap gap-2">
                    @foreach ($owners as $owner)
                        <li><x-ui.badge>{{ $owner->name }}</x-ui.badge></li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-6 flex gap-2">
                <x-ui.button wire:click="goToStep(4)">{{ __('setup.next') }}</x-ui.button>
                <x-ui.button variant="secondary" wire:click="goToStep(2)">{{ __('setup.back') }}</x-ui.button>
            </div>
        </x-ui.card>
    @endif

    {{-- Step 4: charge heads --}}
    @if ($step === 4)
        <x-ui.card :title="__('setup.step_charges')">
            <p class="mb-4 text-sm text-slate-500">{{ __('setup.step_charges_help') }}</p>

            @if ($heads->isEmpty())
                <div class="mb-4 rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-sm font-medium text-slate-900">{{ __('setup.suggested_heads') }}</p>
                    <p class="mb-3 text-sm text-slate-500">{{ __('setup.suggested_heads_help') }}</p>
                    <x-ui.button variant="secondary" wire:click="addSuggestedHeads">{{ __('setup.add_suggested') }}</x-ui.button>
                </div>
            @else
                <ul class="mb-4 divide-y divide-slate-100 rounded-md border border-slate-200">
                    @foreach ($heads as $head)
                        <li class="flex items-center justify-between px-4 py-2 text-sm">
                            <span class="font-medium text-slate-900">{{ $head->displayName() }}</span>
                            <span class="text-slate-500">
                                {{ $head->basis->label() }} · <x-money :amount="$head->amount" />
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form wire:submit="addHead" class="grid gap-4 sm:grid-cols-4">
                <x-form.field :label="__('masters.name')" name="headName" required>
                    <x-form.input wire:model="headName" />
                </x-form.field>

                <x-form.field :label="__('masters.basis')" name="headBasis" required>
                    <x-form.select wire:model.live="headBasis">
                        @foreach ($bases as $case)
                            <option value="{{ $case->value }}">{{ $case->label() }}</option>
                        @endforeach
                    </x-form.select>
                </x-form.field>

                <x-form.field :label="__('masters.amount')" name="headAmount"
                              :hint="App\Enums\ChargeBasis::from($headBasis)->amountLabel()" required>
                    <x-form.input type="number" step="0.01" min="0" wire:model="headAmount" />
                </x-form.field>

                <div class="flex items-end">
                    <x-ui.button variant="secondary" type="submit">{{ __('masters.create') }}</x-ui.button>
                </div>
            </form>

            <div class="mt-6 flex gap-2">
                <x-ui.button wire:click="goToStep(5)">{{ __('setup.next') }}</x-ui.button>
                <x-ui.button variant="secondary" wire:click="goToStep(3)">{{ __('setup.back') }}</x-ui.button>
            </div>
        </x-ui.card>
    @endif

    {{-- Step 5: opening balances --}}
    @if ($step === 5)
        <x-ui.card :title="__('setup.step_opening')">
            <p class="mb-4 text-sm text-slate-500">{{ __('setup.step_opening_help') }}</p>

            <form wire:submit="saveOpeningBalances" class="grid gap-4 sm:grid-cols-3">
                <x-form.field :label="__('accounting.as_of')" name="openingAsOf" required>
                    <x-form.input type="date" wire:model="openingAsOf" />
                </x-form.field>

                <x-form.field :label="__('accounting.cash_in_hand')" name="cash" required>
                    <x-form.input type="number" step="0.01" min="0" wire:model="cash" />
                </x-form.field>

                <x-form.field :label="__('accounting.bank')" name="bank" required>
                    <x-form.input type="number" step="0.01" min="0" wire:model="bank" />
                </x-form.field>

                <div class="flex items-end gap-2 sm:col-span-3">
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('setup.finish') }}</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="goToStep(4)">{{ __('setup.back') }}</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="goToStep(6)">{{ __('setup.skip') }}</x-ui.button>
                </div>
            </form>

            <p class="mt-4 text-sm text-slate-500">
                {{ __('accounting.opening_help') }}
            </p>
        </x-ui.card>
    @endif

    {{-- Step 6: done --}}
    @if ($step === 6)
        <x-ui.empty-state :title="__('setup.done_title')" :description="__('setup.done_body')">
            <x-slot:actions>
                <x-ui.button :href="route('billing.generate')">{{ __('setup.go_to_billing') }}</x-ui.button>
                <x-ui.button variant="secondary" :href="route('dashboard')">{{ __('setup.go_to_dashboard') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.empty-state>

        <x-ui.card>
            <dl class="grid gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-slate-500">{{ __('masters.building') }}</dt>
                    <dd class="font-medium text-slate-900">{{ $building?->displayName() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">{{ __('masters.flats') }}</dt>
                    <dd class="font-medium text-slate-900">
                        {{ __('setup.flats_summary', ['flats' => $flatCount, 'floors' => $floorCount]) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">{{ __('masters.charge_heads') }}</dt>
                    <dd class="font-medium text-slate-900">
                        {{ __('setup.heads_summary', ['count' => $heads->count()]) }}
                    </dd>
                </div>
            </dl>
        </x-ui.card>
    @endif
</div>
