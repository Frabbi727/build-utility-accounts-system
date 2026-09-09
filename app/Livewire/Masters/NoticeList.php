<?php

namespace App\Livewire\Masters;

use App\Enums\NoticeType;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Building;
use App\Models\Notice;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class NoticeList extends Component
{
    use WithCrudModal, WithPagination;

    public string $search = '';

    public string $title = '';

    public string $content = '';

    public string $type = 'general';

    public bool $isPinned = false;

    public ?string $publishedAt = null;

    public ?string $expiresAt = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    private function building(): ?Building
    {
        return app(CurrentBuilding::class)->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'type' => ['required', Rule::enum(NoticeType::class)],
            'isPinned' => ['boolean'],
            'publishedAt' => ['nullable', 'date'],
            'expiresAt' => ['nullable', 'date', 'after_or_equal:publishedAt'],
        ];
    }

    /**
     * @param  Notice|null  $record
     */
    protected function fillForm(?Model $record): void
    {
        $this->title = $record->title ?? '';
        $this->content = $record->content ?? '';
        $this->type = $record?->type->value ?? NoticeType::General->value;
        $this->isPinned = $record->is_pinned ?? false;
        $this->publishedAt = $record?->published_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i');
        $this->expiresAt = $record?->expires_at?->format('Y-m-d\TH:i');
    }

    protected function findRecord(int $id): Notice
    {
        return Notice::where('building_id', app(CurrentBuilding::class)->id())->findOrFail($id);
    }

    protected function authorizeAction(string $ability, ?Model $record = null): void
    {
        $this->authorize($ability, $record ?? Notice::class);
    }

    protected function persist(): Notice
    {
        $notice = $this->editingId === null ? new Notice : $this->findRecord($this->editingId);

        $notice->fill([
            'building_id' => app(CurrentBuilding::class)->getOrFail()->id,
            'created_by' => $notice->created_by ?? auth()->id(),
            'title' => $this->title,
            'content' => $this->content,
            'type' => NoticeType::from($this->type),
            'is_pinned' => $this->isPinned,
            'published_at' => $this->publishedAt ?: now(),
            'expires_at' => $this->expiresAt ?: null,
        ])->save();

        return $notice;
    }

    public function togglePinned(int $id): void
    {
        $notice = $this->findRecord($id);
        $this->authorizeAction('update', $notice);
        $notice->is_pinned = ! $notice->is_pinned;
        $notice->save();
        $this->notify(__('masters.saved'));
    }

    public function render(): View
    {
        $this->authorize('viewAny', Notice::class);

        $building = $this->building();
        $notices = $building === null
            ? new LengthAwarePaginator([], 0, 15)
            : $building->notices()
                ->with('creator')
                ->when($this->search !== '', fn ($q) => $q->where(function ($sub) {
                    $sub->where('title', 'like', "%{$this->search}%")
                        ->orWhere('content', 'like', "%{$this->search}%");
                }))
                ->latest('is_pinned')
                ->latest('published_at')
                ->paginate(15);

        return view('livewire.masters.notice-list', [
            'building' => $building,
            'notices' => $notices,
            'types' => NoticeType::cases(),
        ])->layout('components.layouts.app');
    }
}
