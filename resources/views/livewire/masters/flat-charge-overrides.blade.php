<div class="space-y-6">
    <x-ui.page-header :title="__('masters.overrides_for', ['flat' => $flat->number])"
                      :description="__('masters.overrides_help')">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('flats.index')">{{ __('masters.flats') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.notice :message="$notice" :type="$noticeType" />

    @if ($heads->isEmpty())
        <x-ui.empty-state :title="__('masters.no_charge_heads')" :description="__('masters.no_charge_heads_help')">
            <x-slot:actions>
                <x-ui.button :href="route('charge-heads.index')">{{ __('masters.charge_heads') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.empty-state>
    @else
        <form wire:submit="save" class="space-y-4">
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-2">{{ __('masters.charge_heads') }}</th>
                    <th class="px-4 py-2">{{ __('masters.basis') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('masters.standard_amount') }}</th>
                    <th class="px-4 py-2">{{ __('masters.status') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('masters.override_amount') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('masters.effective_amount') }}</th>
                </x-slot:head>

                @foreach ($heads as $head)
                    <tr @class(['bg-slate-50/60' => ! $head->is_active])>
                        <td class="px-4 py-2 font-medium text-slate-900">
                            {{ $head->displayName() }}
                            @unless ($head->is_active)
                                <x-ui.badge class="ml-1">{{ __('masters.inactive') }}</x-ui.badge>
                            @endunless
                        </td>
                        <td class="px-4 py-2 text-slate-600">{{ $head->basis->label() }}</td>
                        <td class="px-4 py-2 text-right text-slate-600">
                            <x-money :amount="$standard[$head->id]" />
                        </td>
                        <td class="px-4 py-2">
                            <x-form.select wire:model.live="rows.{{ $head->id }}.mode" class="py-1 text-xs">
                                <option value="standard">{{ __('masters.standard') }}</option>
                                <option value="override">{{ __('masters.override_amount') }}</option>
                                <option value="exempt">{{ __('masters.exempt') }}</option>
                            </x-form.select>
                        </td>
                        <td class="px-4 py-2 text-right">
                            @if (($rows[$head->id]['mode'] ?? 'standard') === 'override')
                                <x-form.input type="number" step="0.01" min="0"
                                              wire:model="rows.{{ $head->id }}.amount"
                                              class="w-32 py-1 text-right text-xs" />
                                @error("rows.{$head->id}.amount")
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right font-medium text-slate-900">
                            @if (isset($effective[$head->id]))
                                <x-money :amount="$effective[$head->id]" />
                            @else
                                <span class="text-xs text-slate-400">{{ __('masters.exempt') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach

                <tr class="bg-slate-50 font-semibold text-slate-900">
                    <td class="px-4 py-2" colspan="5">{{ __('masters.preview_total') }}</td>
                    <td class="px-4 py-2 text-right"><x-money :amount="$total" /></td>
                </tr>
            </x-ui.table>

            @can('manage', App\Models\Flat::class)
                <div class="flex justify-end">
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('masters.save') }}</x-ui.button>
                </div>
            @endcan
        </form>
    @endif
</div>
