<div>
    <x-ui.page-header :title="__('masters.notices')" :description="__('masters.notices_help')">
        <x-slot:actions>
            @if ($building !== null)
                @can('create', App\Models\Notice::class)
                    <x-ui.button wire:click="create">{{ __('masters.new_notice') }}</x-ui.button>
                @endcan
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @include('livewire.partials.crud-feedback')

    @if ($building === null)
        <x-ui.empty-state :title="__('masters.no_building')" :description="__('masters.no_building_help')">
            <x-slot:actions>
                <x-ui.button :href="route('buildings.index')">{{ __('masters.new_building') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.empty-state>
    @else
        <div class="mb-4">
            <x-form.input wire:model.live.debounce.250ms="search" :placeholder="__('masters.search_notices')" />
        </div>

        @if ($notices->isEmpty())
            <x-ui.empty-state :title="__('masters.no_notices')" :description="__('masters.no_notices_help')">
                <x-slot:actions>
                    @can('create', App\Models\Notice::class)
                        <x-ui.button wire:click="create">{{ __('masters.new_notice') }}</x-ui.button>
                    @endcan
                </x-slot:actions>
            </x-ui.empty-state>
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-2">{{ __('masters.notice_title') }}</th>
                    <th class="px-4 py-2">{{ __('masters.type') }}</th>
                    <th class="px-4 py-2">{{ __('masters.status') }}</th>
                    <th class="px-4 py-2">{{ __('masters.published_at') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('masters.actions') }}</th>
                </x-slot:head>

                @foreach ($notices as $noticeItem)
                    <tr>
                        <td class="px-4 py-2 font-medium text-slate-900">
                            <div class="flex items-center gap-2">
                                @if ($noticeItem->is_pinned)
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                        📌 {{ __('masters.pinned') }}
                                    </span>
                                @endif
                                <span>{{ $noticeItem->title }}</span>
                            </div>
                            <div class="mt-1 line-clamp-1 text-xs text-slate-500">
                                {{ Str::limit($noticeItem->content, 80) }}
                            </div>
                        </td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium {{ $noticeItem->type->badgeClasses() }}">
                                {{ $noticeItem->type->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-sm text-slate-600">
                            @if ($noticeItem->expires_at && $noticeItem->expires_at->isPast())
                                <span class="text-xs text-red-600 font-medium">{{ __('masters.expired') }}</span>
                            @elseif ($noticeItem->published_at->isFuture())
                                <span class="text-xs text-amber-600 font-medium">{{ __('masters.scheduled') }}</span>
                            @else
                                <span class="text-xs text-emerald-600 font-medium">{{ __('masters.active') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-sm text-slate-600">
                            {{ $noticeItem->published_at->format('M d, Y H:i') }}
                        </td>
                        <td class="px-4 py-2 text-right">
                            <div class="flex justify-end gap-3">
                                @can('update', $noticeItem)
                                    <button type="button" wire:click="togglePinned({{ $noticeItem->id }})"
                                            class="text-sm font-medium text-slate-600 hover:text-slate-900">
                                        {{ $noticeItem->is_pinned ? __('masters.unpin') : __('masters.pin') }}
                                    </button>
                                    <button type="button" wire:click="edit({{ $noticeItem->id }})"
                                            class="text-sm font-medium text-slate-600 hover:text-slate-900">
                                        {{ __('masters.edit') }}
                                    </button>
                                @endcan
                                @can('delete', $noticeItem)
                                    <button type="button" wire:click="confirmDelete({{ $noticeItem->id }})"
                                            class="text-sm font-medium text-red-600 hover:text-red-800">
                                        {{ __('masters.delete') }}
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="mt-4">{{ $notices->links() }}</div>
        @endif
    @endif

    @if ($showForm)
        <x-ui.modal :title="$this->isEditing() ? __('masters.edit_notice') : __('masters.new_notice')">
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <x-form.field :label="__('masters.notice_title')" name="title" required class="sm:col-span-2">
                        <x-form.input wire:model="title" required />
                    </x-form.field>

                    <x-form.field :label="__('masters.type')" name="type" required>
                        <select wire:model="type" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @foreach ($types as $typeOption)
                                <option value="{{ $typeOption->value }}">{{ $typeOption->label() }}</option>
                            @endforeach
                        </select>
                    </x-form.field>

                    <x-form.field name="isPinned" class="flex items-center pt-6">
                        <x-form.checkbox wire:model="isPinned" :label="__('masters.pinned')" />
                    </x-form.field>

                    <x-form.field :label="__('masters.content')" name="content" required class="sm:col-span-2">
                        <textarea wire:model="content" rows="4" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required></textarea>
                    </x-form.field>

                    <x-form.field :label="__('masters.published_at')" name="publishedAt">
                        <x-form.input type="datetime-local" wire:model="publishedAt" />
                    </x-form.field>

                    <x-form.field :label="__('masters.expires_at')" name="expiresAt">
                        <x-form.input type="datetime-local" wire:model="expiresAt" />
                    </x-form.field>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                    <x-ui.button variant="secondary" wire:click="cancel">{{ __('masters.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('masters.save') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
