{{-- The notice and the delete confirmation every WithCrudModal screen shares.
     Included rather than made a component so it can read the Livewire component's own
     state ($notice, $deletingId) directly. --}}
<x-ui.notice :message="$notice" :type="$noticeType" />

@php($pendingDelete = $this->pendingDelete())

@if ($pendingDelete !== null)
    <x-ui.confirm-dialog
        :title="$pendingDelete['title']"
        :message="$pendingDelete['message']"
        :confirm-label="__('confirmations.delete_confirm')"
        :confirm-action="'delete('.$deletingId.')'"
        cancel-action="cancelDelete"
    >{{ $pendingDelete['details'] }}</x-ui.confirm-dialog>
@endif
