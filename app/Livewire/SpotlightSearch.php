<?php

namespace App\Livewire;

use App\Models\Flat;
use App\Services\Reporting\LedgerReports;
use App\Support\CurrentBuilding;
use App\Support\DuesReminder;
use App\Support\Navigation;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class SpotlightSearch extends Component
{
    public bool $isOpen = false;

    public string $query = '';

    public ?string $errorMessage = null;

    #[On('open-spotlight')]
    public function open(): void
    {
        $this->isOpen = true;
        $this->query = '';
        $this->errorMessage = null;
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->query = '';
        $this->errorMessage = null;
    }

    public function clear(): void
    {
        $this->query = '';
        $this->errorMessage = null;
    }

    /**
     * @return list<array{
     *     title: string,
     *     route: string,
     *     category: string,
     *     icon: string
     * }>
     */
    private function navigationShortcuts(): array
    {
        $user = auth()->user();
        if ($user === null) {
            return [];
        }

        $items = app(Navigation::class)->allFlatItems($user);

        return array_map(fn (array $item): array => [
            'title' => $item['label'],
            'route' => $item['url'],
            'category' => $item['category'],
            'icon' => $item['icon'] ?? 'folder',
        ], $items);
    }

    public function render(LedgerReports $reports): View
    {
        $user = auth()->user();
        $building = app(CurrentBuilding::class)->get();
        $trimmed = trim($this->query);

        /** @var EloquentCollection<int, Flat>|Collection<int, never> $flats */
        $flats = collect();
        $dues = [];
        $reminders = [];

        if ($user !== null && $building !== null && $trimmed !== '') {
            $flatQuery = Flat::query()
                ->where('building_id', $building->id)
                ->where(function ($q) use ($trimmed): void {
                    $q->where('number', 'ilike', "%{$trimmed}%")
                        ->orWhereHas('owner', function ($oq) use ($trimmed): void {
                            $oq->where('name', 'ilike', "%{$trimmed}%")
                                ->orWhere('phone', 'ilike', "%{$trimmed}%");
                        })
                        ->orWhereHas('tenants', function ($tq) use ($trimmed): void {
                            $tq->where('name', 'ilike', "%{$trimmed}%")
                                ->orWhere('phone', 'ilike', "%{$trimmed}%");
                        });
                })
                ->with(['owner', 'floor', 'building'])
                ->orderBy('number')
                ->limit(8);

            if (! $user->isStaff()) {
                $flatQuery->where(function ($q) use ($user): void {
                    if ($user->owner !== null) {
                        $q->where('owner_id', $user->owner->id);
                    } elseif ($user->tenant !== null) {
                        $q->where('id', $user->tenant->flat_id);
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                });
            }

            $flats = $flatQuery->get();

            if ($flats->isNotEmpty()) {
                try {
                    $outstanding = $reports->outstandingByFlat();

                    foreach ($flats as $flat) {
                        $due = $outstanding[$flat->id] ?? '0.00';
                        $dues[$flat->id] = $due;
                        if (bccomp($due, '0.00', 2) > 0) {
                            $reminders[$flat->id] = DuesReminder::for($flat, $due);
                        }
                    }
                    $this->errorMessage = null;
                } catch (\Throwable $e) {
                    report($e);
                    $this->errorMessage = 'Could not calculate current dues. Showing flat details without ledger balances.';
                    foreach ($flats as $flat) {
                        $dues[$flat->id] = '0.00';
                    }
                }
            }
        } else {
            $this->errorMessage = null;
        }

        $shortcuts = $this->navigationShortcuts();
        if ($trimmed !== '') {
            $shortcuts = array_values(array_filter($shortcuts, function ($item) use ($trimmed): bool {
                return str_contains(mb_strtolower($item['title']), mb_strtolower($trimmed))
                    || str_contains(mb_strtolower($item['category']), mb_strtolower($trimmed));
            }));
        }

        return view('livewire.spotlight-search', [
            'building' => $building,
            'flats' => $flats,
            'dues' => $dues,
            'reminders' => $reminders,
            'shortcuts' => $shortcuts,
            'errorMessage' => $this->errorMessage,
        ]);
    }
}
