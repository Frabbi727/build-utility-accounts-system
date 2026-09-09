<div class="space-y-6">
    <x-ui.page-header :title="__('billing.payment_submissions')" :description="__('billing.review_submission')" />

    <x-ui.notice :message="$notice" :type="$noticeType" class="mb-6" />

    <div class="flex flex-wrap items-center justify-between gap-4">
        {{-- Status Filter Tabs --}}
        <div class="flex rounded-md shadow-sm">
            <button type="button"
                    wire:click="$set('statusFilter', 'pending')"
                    class="rounded-l-md px-3.5 py-2 text-xs font-semibold {{ $statusFilter === 'pending' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-50' }} border border-slate-300">
                {{ __('billing.pending') }}
            </button>
            <button type="button"
                    wire:click="$set('statusFilter', 'all')"
                    class="px-3.5 py-2 text-xs font-semibold {{ $statusFilter === 'all' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-50' }} border-y border-r border-slate-300">
                {{ __('masters.all') }}
            </button>
            <button type="button"
                    wire:click="$set('statusFilter', 'approved')"
                    class="px-3.5 py-2 text-xs font-semibold {{ $statusFilter === 'approved' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-50' }} border-y border-r border-slate-300">
                {{ __('billing.approved') }}
            </button>
            <button type="button"
                    wire:click="$set('statusFilter', 'rejected')"
                    class="rounded-r-md px-3.5 py-2 text-xs font-semibold {{ $statusFilter === 'rejected' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-50' }} border-y border-r border-slate-300">
                {{ __('billing.rejected') }}
            </button>
        </div>

        {{-- Search Input --}}
        <div class="w-full sm:w-72">
            <input type="search" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('billing.reference') }} / {{ __('billing.flat') }} / {{ __('billing.owner') }}..."
                   class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th class="px-4 py-3">{{ __('dashboard.payment_date') }}</th>
            <th class="px-4 py-3">{{ __('billing.flat') }}</th>
            <th class="px-4 py-3">{{ __('billing.owner') }}</th>
            <th class="px-4 py-3">{{ __('dashboard.method') }}</th>
            <th class="px-4 py-3">{{ __('billing.reference') }}</th>
            <th class="px-4 py-3">{{ __('billing.deposit_slip') }}</th>
            <th class="px-4 py-3 text-right">{{ __('dashboard.amount') }}</th>
            <th class="px-4 py-3 text-center">{{ __('masters.status') }}</th>
            <th class="px-4 py-3 text-right">{{ __('masters.actions') }}</th>
        </x-slot:head>

        @forelse ($submissions as $sub)
            <tr wire:key="submission-{{ $sub->id }}" class="hover:bg-slate-50/50">
                <td class="px-4 py-3 whitespace-nowrap text-slate-600">{{ $sub->payment_date->format('d M Y') }}</td>
                <td class="px-4 py-3 whitespace-nowrap font-medium text-slate-900">
                    <a href="{{ route('flats.statement', $sub->flat) }}" class="hover:underline hover:text-indigo-600">
                        {{ $sub->flat->number }}
                    </a>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-slate-600">
                    <div>{{ $sub->flat->owner?->name ?? $sub->user->name }}</div>
                    @if ($sub->flat->owner && $sub->user->name !== $sub->flat->owner->name)
                        <div class="text-xs text-slate-400">By: {{ $sub->user->name }}</div>
                    @endif
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 uppercase">
                        {{ $sub->payment_method->value }}
                    </span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap font-mono text-xs font-semibold text-slate-800">
                    {{ $sub->reference_number }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    @if ($sub->slip_path)
                        <a href="{{ asset('storage/' . $sub->slip_path) }}" target="_blank"
                           class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">
                            📎 {{ __('masters.view') }}
                        </a>
                    @else
                        <span class="text-xs text-slate-400">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-right font-semibold tabular-nums text-slate-900">
                    <x-money :amount="$sub->amount" />
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-center">
                    @if ($sub->status === \App\Enums\PaymentSubmissionStatus::Pending)
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                            {{ __('billing.pending') }}
                        </span>
                    @elseif ($sub->status === \App\Enums\PaymentSubmissionStatus::Approved)
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                            {{ __('billing.approved') }}
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700" title="{{ $sub->rejection_reason }}">
                            {{ __('billing.rejected') }}
                        </span>
                    @endif
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-right">
                    <div class="inline-flex items-center gap-2">
                        @if ($sub->status === \App\Enums\PaymentSubmissionStatus::Pending)
                            <button type="button"
                                    wire:click="approve({{ $sub->id }})"
                                    wire:confirm="Confirm approval of BDT {{ number_format((float) $sub->amount, 2) }} for flat {{ $sub->flat->number }}? This will post directly to the ledger."
                                    class="inline-flex items-center rounded-md bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
                                ✓ {{ __('billing.approve_and_post') }}
                            </button>
                            <button type="button"
                                    wire:click="openRejectModal({{ $sub->id }})"
                                    class="inline-flex items-center rounded-md border border-red-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-red-600 shadow-sm hover:bg-red-50">
                                ✕ {{ __('billing.reject') }}
                            </button>
                        @elseif ($sub->payment)
                            <a href="{{ route('payments.receipt', $sub->payment) }}" target="_blank"
                               class="text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                                {{ __('dashboard.print_receipt') }}
                            </a>
                        @endif

                        <button type="button"
                                wire:click="openDetailModal({{ $sub->id }})"
                                class="text-xs font-medium text-slate-500 hover:text-slate-800">
                            {{ __('masters.details') }}
                        </button>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="px-4 py-8 text-center text-sm text-slate-400">
                    {{ __('billing.no_submissions') }}
                </td>
            </tr>
        @endforelse
    </x-ui.table>

    {{ $submissions->links() }}

    {{-- Reject Modal --}}
    @if ($showRejectModal)
        <x-ui.modal :title="__('billing.reject')">
            <form wire:submit.prevent="reject">
                <div class="px-6 py-5">
                    <x-form.field :label="__('billing.rejection_reason')" name="rejectionReason" required>
                        <textarea wire:model="rejectionReason" rows="3"
                                  class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                  placeholder="e.g. Transaction ID was not found in statement or invalid amount." required></textarea>
                    </x-form.field>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                    <x-ui.button variant="secondary" wire:click="closeRejectModal">{{ __('masters.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" variant="danger" wire:loading.attr="disabled">{{ __('billing.reject') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    {{-- View Detail Modal --}}
    @if ($showDetailModal && $viewingSubmission)
        <x-ui.modal :title="__('billing.review_submission')">
            <div class="space-y-4 px-6 py-5 text-sm">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="block text-xs font-medium text-slate-500 uppercase">{{ __('billing.flat') }}</span>
                        <span class="font-semibold text-slate-900">{{ $viewingSubmission->flat->number }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-medium text-slate-500 uppercase">{{ __('dashboard.amount') }}</span>
                        <span class="font-semibold tabular-nums text-slate-900">{{ number_format((float) $viewingSubmission->amount, 2) }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-medium text-slate-500 uppercase">{{ __('dashboard.method') }}</span>
                        <span class="uppercase font-medium text-slate-800">{{ $viewingSubmission->payment_method->value }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-medium text-slate-500 uppercase">{{ __('billing.reference') }}</span>
                        <span class="font-mono font-medium text-slate-800">{{ $viewingSubmission->reference_number }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-medium text-slate-500 uppercase">{{ __('dashboard.payment_date') }}</span>
                        <span class="text-slate-800">{{ $viewingSubmission->payment_date->format('M d, Y') }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-medium text-slate-500 uppercase">{{ __('masters.status') }}</span>
                        <span class="capitalize font-semibold text-slate-800">{{ $viewingSubmission->status->value }}</span>
                    </div>
                </div>

                @if ($viewingSubmission->resident_notes)
                    <div class="rounded-md bg-slate-50 p-3">
                        <span class="block text-xs font-medium text-slate-500">{{ __('billing.payment_notes') }}</span>
                        <p class="mt-1 text-slate-700">{{ $viewingSubmission->resident_notes }}</p>
                    </div>
                @endif

                @if ($viewingSubmission->slip_path)
                    <div>
                        <span class="mb-2 block text-xs font-medium text-slate-500 uppercase">{{ __('billing.deposit_slip') }}</span>
                        <a href="{{ asset('storage/' . $viewingSubmission->slip_path) }}" target="_blank" class="block">
                            <img src="{{ asset('storage/' . $viewingSubmission->slip_path) }}" alt="Deposit Slip"
                                 class="max-h-64 rounded-md border border-slate-200 object-contain shadow-sm hover:opacity-90">
                        </a>
                    </div>
                @endif

                @if ($viewingSubmission->rejection_reason)
                    <div class="rounded-md bg-red-50 p-3 text-red-700">
                        <span class="block text-xs font-bold uppercase">{{ __('billing.rejection_reason') }}</span>
                        <p class="mt-1 text-sm">{{ $viewingSubmission->rejection_reason }}</p>
                    </div>
                @endif

                @if ($viewingSubmission->reviewed_at)
                    <div class="text-xs text-slate-500">
                        Reviewed by {{ $viewingSubmission->reviewer?->name ?? 'Staff' }} on {{ $viewingSubmission->reviewed_at->format('M d, Y H:i') }}
                    </div>
                @endif
            </div>

            <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-6 py-3">
                <x-ui.button variant="secondary" wire:click="closeDetailModal">{{ __('masters.done') }}</x-ui.button>
            </div>
        </x-ui.modal>
    @endif
</div>
