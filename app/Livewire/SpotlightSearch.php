<?php

namespace App\Livewire;

use App\Models\Flat;
use App\Services\Reporting\LedgerReports;
use App\Support\CurrentBuilding;
use App\Support\DuesReminder;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class SpotlightSearch extends Component
{
    public bool $isOpen = false;

    public string $query = '';

    #[On('open-spotlight')]
    public function open(): void
    {
        $this->isOpen = true;
        $this->query = '';
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->query = '';
    }

    /**
     * @return array<int, array{
     *     title: string,
     *     description: string,
     *     route: string,
     *     category: string,
     *     can: bool
     * }>
     */
    private function navigationShortcuts(): array
    {
        $user = auth()->user();
        $isStaff = $user && ($user->hasRole('admin') || $user->hasRole('accountant') || $user->hasRole('committee'));
        $canManageMoney = $user && ($user->hasRole('admin') || $user->hasRole('accountant'));

        $items = [
            [
                'title' => __('nav.dashboard'),
                'description' => __('dashboard.overview'),
                'route' => route('dashboard'),
                'category' => __('nav.general'),
                'can' => true,
            ],
            [
                'title' => __('nav.flats'),
                'description' => __('billing.all_flats'),
                'route' => Route::has('flats.index') ? route('flats.index') : '',
                'category' => __('nav.masters'),
                'can' => $isStaff,
            ],
            [
                'title' => __('billing.generate_bills'),
                'description' => __('billing.generate_help'),
                'route' => Route::has('billing.generate') ? route('billing.generate') : '',
                'category' => __('nav.billing'),
                'can' => $canManageMoney,
            ],
            [
                'title' => __('billing.record_payment'),
                'description' => __('billing.record_payment_help'),
                'route' => Route::has('payments.create') ? route('payments.create') : '',
                'category' => __('nav.billing'),
                'can' => $canManageMoney,
            ],
            [
                'title' => __('reports.owner_dues'),
                'description' => __('reports.dues_from_ledger'),
                'route' => Route::has('reports.owner-dues') ? route('reports.owner-dues') : '',
                'category' => __('nav.reports'),
                'can' => $isStaff,
            ],
            [
                'title' => __('nav.maintenance'),
                'description' => __('maintenance.title'),
                'route' => Route::has('maintenance-requests.index') ? route('maintenance-requests.index') : '',
                'category' => __('nav.community'),
                'can' => $isStaff,
            ],
            [
                'title' => __('nav.notices'),
                'description' => __('notices.title'),
                'route' => Route::has('notices.index') ? route('notices.index') : '',
                'category' => __('nav.community'),
                'can' => $isStaff,
            ],
            [
                'title' => __('nav.expenses'),
                'description' => __('expenses.title'),
                'route' => Route::has('expenses.index') ? route('expenses.index') : '',
                'category' => __('nav.expenses'),
                'can' => $isStaff,
            ],
            [
                'title' => __('nav.reports'),
                'description' => __('reports.financial_reports'),
                'route' => Route::has('reports.index') ? route('reports.index') : '',
                'category' => __('nav.reports'),
                'can' => $isStaff,
            ],
        ];

        return array_filter($items, fn ($item) => $item['can'] && $item['route'] !== '');
    }

    public function render(LedgerReports $reports): View
    {
        $building = app(CurrentBuilding::class)->get();
        $trimmed = trim($this->query);

        $flats = collect();
        $dues = [];
        $reminders = [];

        if ($building !== null && $trimmed !== '') {
            $flats = Flat::query()
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
                ->limit(8)
                ->get();

            $outstanding = $reports->outstandingByFlat();

            foreach ($flats as $flat) {
                $due = $outstanding[$flat->id] ?? '0.00';
                $dues[$flat->id] = $due;
                if (bccomp($due, '0.00', 2) > 0) {
                    $reminders[$flat->id] = DuesReminder::for($flat, $due);
                }
            }
        }

        $shortcuts = $this->navigationShortcuts();
        if ($trimmed !== '') {
            $shortcuts = array_filter($shortcuts, function ($item) use ($trimmed): bool {
                return str_contains(mb_strtolower($item['title']), mb_strtolower($trimmed))
                    || str_contains(mb_strtolower($item['description']), mb_strtolower($trimmed))
                    || str_contains(mb_strtolower($item['category']), mb_strtolower($trimmed));
            });
        }

        return view('livewire.spotlight-search', [
            'building' => $building,
            'flats' => $flats,
            'dues' => $dues,
            'reminders' => $reminders,
            'shortcuts' => $shortcuts,
        ]);
    }
}
