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
     * @var list<array{label: string, route?: string, access: string, items?: list<array{label: string, route: string, access: string}>}>
     */
    private const MENU = [
        ['label' => 'nav.dashboard', 'route' => 'dashboard', 'access' => self::ANY],
        [
            'label' => 'nav.billing', 'access' => self::MONEY, 'items' => [
                ['label' => 'nav.generate_bills', 'route' => 'billing.generate', 'access' => self::MONEY],
                ['label' => 'nav.record_payment', 'route' => 'payments.create', 'access' => self::MONEY],
            ],
        ],
        [
            'label' => 'nav.expenses', 'access' => self::STAFF, 'items' => [
                ['label' => 'nav.expenses', 'route' => 'expenses.index', 'access' => self::STAFF],
                ['label' => 'nav.vendor_bills', 'route' => 'vendor-bills.index', 'access' => self::STAFF],
            ],
        ],
        [
            'label' => 'nav.masters', 'access' => self::STAFF, 'items' => [
                ['label' => 'nav.flats', 'route' => 'flats.index', 'access' => self::STAFF],
                ['label' => 'nav.owners', 'route' => 'owners.index', 'access' => self::STAFF],
                ['label' => 'nav.tenants', 'route' => 'tenants.index', 'access' => self::STAFF],
                ['label' => 'nav.vendors', 'route' => 'vendors.index', 'access' => self::STAFF],
                ['label' => 'nav.staff', 'route' => 'staff.index', 'access' => self::STAFF],
                ['label' => 'nav.buildings', 'route' => 'buildings.index', 'access' => self::STAFF],
                ['label' => 'nav.floors', 'route' => 'floors.index', 'access' => self::STAFF],
                ['label' => 'nav.charge_heads', 'route' => 'charge-heads.index', 'access' => self::STAFF],
            ],
        ],
        ['label' => 'nav.reports', 'route' => 'reports.index', 'access' => self::STAFF],
        [
            'label' => 'nav.settings', 'access' => self::STAFF, 'items' => [
                ['label' => 'nav.accounts', 'route' => 'accounts.index', 'access' => self::STAFF],
                ['label' => 'nav.opening_balances', 'route' => 'accounting.opening-balances', 'access' => self::ADMIN],
                ['label' => 'nav.periods', 'route' => 'accounting.periods', 'access' => self::ADMIN],
                ['label' => 'nav.users', 'route' => 'users.index', 'access' => self::ADMIN],
            ],
        ],
    ];

    /**
     * The menu this user may see, with unavailable routes and empty groups removed.
     *
     * @return list<array{label: string, url: string|null, active: bool, items: list<array{label: string, url: string, active: bool}>}>
     */
    public function for(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $menu = [];

        foreach (self::MENU as $entry) {
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
                'items' => $items,
            ];
        }

        return $menu;
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
