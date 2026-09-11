<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * The main menu, as data.
 *
 * Items are dropped when their route does not exist yet, so the menu grows as
 * screens are added instead of needing to be edited in lockstep with routes.
 */
class Navigation
{
    private const ANY = 'any';

    private const STAFF = 'staff';

    private const MONEY = 'money';

    private const ADMIN = 'admin';

    /**
     * @var list<array{label: string, route?: string, access: string, icon?: string, items?: list<array{label: string, route: string, access: string, icon?: string}>}>
     */
    private const MENU = [
        ['label' => 'nav.dashboard', 'route' => 'dashboard', 'access' => self::ANY, 'icon' => 'dashboard'],
        [
            'label' => 'nav.billing', 'access' => self::STAFF, 'icon' => 'billing', 'items' => [
                ['label' => 'nav.generate_bills', 'route' => 'billing.generate', 'access' => self::MONEY],
                ['label' => 'nav.record_payment', 'route' => 'payments.create', 'access' => self::MONEY],
                ['label' => 'nav.payment_submissions', 'route' => 'billing.submissions', 'access' => self::MONEY],
                ['label' => 'nav.payments', 'route' => 'payments.index', 'access' => self::STAFF],
                ['label' => 'nav.shared_costs', 'route' => 'shared-costs.index', 'access' => self::MONEY],
            ],
        ],
        [
            'label' => 'nav.expenses', 'access' => self::STAFF, 'icon' => 'expenses', 'items' => [
                ['label' => 'nav.expenses', 'route' => 'expenses.index', 'access' => self::STAFF],
                ['label' => 'nav.vendor_bills', 'route' => 'vendor-bills.index', 'access' => self::STAFF],
            ],
        ],
        [
            'label' => 'nav.masters', 'access' => self::STAFF, 'icon' => 'masters', 'items' => [
                ['label' => 'nav.flats', 'route' => 'flats.index', 'access' => self::STAFF],
                ['label' => 'nav.owners', 'route' => 'owners.index', 'access' => self::STAFF],
                ['label' => 'nav.tenants', 'route' => 'tenants.index', 'access' => self::STAFF],
                ['label' => 'nav.vendors', 'route' => 'vendors.index', 'access' => self::STAFF],
                ['label' => 'nav.staff', 'route' => 'staff.index', 'access' => self::STAFF],
                ['label' => 'nav.notices', 'route' => 'notices.index', 'access' => self::STAFF],
                ['label' => 'nav.maintenance_requests', 'route' => 'maintenance-requests.index', 'access' => self::STAFF],
                ['label' => 'nav.buildings', 'route' => 'buildings.index', 'access' => self::STAFF],
                ['label' => 'nav.floors', 'route' => 'floors.index', 'access' => self::STAFF],
                ['label' => 'nav.charge_heads', 'route' => 'charge-heads.index', 'access' => self::STAFF],
                ['label' => 'nav.unit_types', 'route' => 'unit-types.index', 'access' => self::STAFF],
                ['label' => 'nav.ad_hoc_charges', 'route' => 'ad-hoc-charges.index', 'access' => self::STAFF],
            ],
        ],
        [
            'label' => 'nav.utilities', 'access' => self::STAFF, 'icon' => 'utilities', 'items' => [
                ['label' => 'nav.readings', 'route' => 'readings.index', 'access' => self::STAFF],
                ['label' => 'nav.utilities_list', 'route' => 'utilities.index', 'access' => self::STAFF],
                ['label' => 'nav.meters', 'route' => 'meters.index', 'access' => self::STAFF],
                ['label' => 'nav.tariffs', 'route' => 'tariffs.index', 'access' => self::STAFF],
            ],
        ],
        ['label' => 'nav.reports', 'route' => 'reports.index', 'access' => self::STAFF, 'icon' => 'reports'],
        [
            'label' => 'nav.settings', 'access' => self::STAFF, 'icon' => 'settings', 'items' => [
                ['label' => 'nav.accounts', 'route' => 'accounts.index', 'access' => self::STAFF],
                ['label' => 'nav.opening_balances', 'route' => 'accounting.opening-balances', 'access' => self::ADMIN],
                ['label' => 'nav.periods', 'route' => 'accounting.periods', 'access' => self::ADMIN],
                ['label' => 'nav.users', 'route' => 'users.index', 'access' => self::ADMIN],
            ],
        ],
    ];

    /**
     * @return list<array{label: string, route?: string, access: string, icon?: string, items?: list<array{label: string, route: string, access: string, icon?: string}>}>
     */
    private function menu(): array
    {
        return self::MENU;
    }

    /**
     * The menu this user may see, with unavailable routes and empty groups removed.
     *
     * @return list<array{label: string, url: string|null, active: bool, icon: string, items: list<array{label: string, url: string, active: bool, icon: string|null}>}>
     */
    public function for(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $menu = [];

        foreach ($this->menu() as $entry) {
            if (! $this->allows($user, $entry['access'])) {
                continue;
            }

            $items = [];

            foreach ($entry['items'] ?? [] as $item) {
                if (! $this->allows($user, $item['access']) || ! Route::has($item['route'])) {
                    continue;
                }

                $items[] = [
                    'label' => __($item['label']),
                    'url' => route($item['route']),
                    'active' => request()->routeIs($item['route']),
                    'icon' => $item['icon'] ?? null,
                ];
            }

            $isGroup = isset($entry['items']);

            if ($isGroup && $items === []) {
                continue;
            }

            if (! $isGroup && ! Route::has($entry['route'])) {
                continue;
            }

            $menu[] = [
                'label' => __($entry['label']),
                'url' => $isGroup ? null : route($entry['route']),
                'active' => $isGroup
                    ? collect($items)->contains('active', true)
                    : request()->routeIs($entry['route']),
                'icon' => $entry['icon'] ?? 'folder',
                'items' => $items,
            ];
        }

        return $menu;
    }

    /**
     * Flat array of all accessible leaf menu items for search indexing.
     *
     * @return list<array{label: string, url: string, category: string, icon?: string}>
     */
    public function allFlatItems(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $flat = [];

        foreach ($this->menu() as $entry) {
            if (! $this->allows($user, $entry['access'])) {
                continue;
            }

            if (isset($entry['items'])) {
                $category = __($entry['label']);

                foreach ($entry['items'] as $item) {
                    if (! $this->allows($user, $item['access']) || ! Route::has($item['route'])) {
                        continue;
                    }

                    $flat[] = [
                        'label' => __($item['label']),
                        'url' => route($item['route']),
                        'category' => $category,
                        'icon' => $item['icon'] ?? $entry['icon'] ?? 'folder',
                    ];
                }
            } else {
                if (! Route::has($entry['route'])) {
                    continue;
                }

                $flat[] = [
                    'label' => __($entry['label']),
                    'url' => route($entry['route']),
                    'category' => __($entry['label']),
                    'icon' => $entry['icon'] ?? 'folder',
                ];
            }
        }

        return $flat;
    }

    private function allows(User $user, string $access): bool
    {
        return match ($access) {
            self::STAFF => $user->isStaff(),
            self::MONEY => $user->canManageMoney(),
            self::ADMIN => $user->isAdmin(),
            default => true,
        };
    }
}
