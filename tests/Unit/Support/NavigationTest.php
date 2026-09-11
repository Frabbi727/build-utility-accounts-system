<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\Navigation;
use Mockery;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    private function createMockUser(bool $isStaff = true, bool $canManageMoney = true, bool $isAdmin = true): User
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isStaff')->andReturn($isStaff);
        $user->shouldReceive('canManageMoney')->andReturn($canManageMoney);
        $user->shouldReceive('isAdmin')->andReturn($isAdmin);

        return $user;
    }

    public function test_for_returns_empty_array_for_guest(): void
    {
        $nav = new Navigation;
        $this->assertSame([], $nav->for(null));
    }

    public function test_all_flat_items_returns_empty_array_for_guest(): void
    {
        $nav = new Navigation;
        $this->assertSame([], $nav->allFlatItems(null));
    }

    public function test_menu_entries_include_icon_attribute_for_all_groups(): void
    {
        $user = $this->createMockUser();
        $nav = new Navigation;
        $menu = $nav->for($user);

        $expectedGroupIcons = [
            __('nav.dashboard') => 'dashboard',
            __('nav.billing') => 'billing',
            __('nav.expenses') => 'expenses',
            __('nav.masters') => 'masters',
            __('nav.utilities') => 'utilities',
            __('nav.reports') => 'reports',
            __('nav.settings') => 'settings',
        ];

        foreach ($expectedGroupIcons as $label => $expectedIcon) {
            $entry = collect($menu)->firstWhere('label', $label);
            $this->assertNotNull($entry, "Menu entry for '{$label}' should exist.");
            $this->assertArrayHasKey('icon', $entry, "Menu entry for '{$label}' must have an 'icon' key.");
            $this->assertSame($expectedIcon, $entry['icon'], "Menu entry for '{$label}' should have icon '{$expectedIcon}'.");
        }
    }

    public function test_sub_items_have_icon_key(): void
    {
        $user = $this->createMockUser();
        $nav = new Navigation;
        $menu = $nav->for($user);

        $billing = collect($menu)->firstWhere('label', __('nav.billing'));
        $this->assertNotNull($billing);
        $this->assertNotEmpty($billing['items']);

        foreach ($billing['items'] as $item) {
            $this->assertArrayHasKey('icon', $item, 'Sub-item must have an icon key (null or string).');
        }
    }

    public function test_all_flat_items_returns_accessible_leaf_items_with_structure(): void
    {
        $user = $this->createMockUser();
        $nav = new Navigation;
        $flat = $nav->allFlatItems($user);

        $this->assertNotEmpty($flat);

        foreach ($flat as $item) {
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('url', $item);
            $this->assertArrayHasKey('category', $item);
            $this->assertArrayHasKey('icon', $item);
            $this->assertIsString($item['label']);
            $this->assertIsString($item['url']);
            $this->assertIsString($item['category']);
            $this->assertNotEmpty($item['category']);
        }

        // Dashboard is standalone
        $dashboardItem = collect($flat)->firstWhere('url', route('dashboard'));
        $this->assertNotNull($dashboardItem);
        $this->assertSame(__('nav.dashboard'), $dashboardItem['category']);
        $this->assertSame('dashboard', $dashboardItem['icon']);

        // Billing item has Billing as category
        $billingItem = collect($flat)->firstWhere('url', route('billing.generate'));
        $this->assertNotNull($billingItem);
        $this->assertSame(__('nav.billing'), $billingItem['category']);
    }

    public function test_all_flat_items_filters_by_user_permissions(): void
    {
        $staffOnlyUser = $this->createMockUser(isStaff: true, canManageMoney: false, isAdmin: false);
        $nav = new Navigation;
        $flat = $nav->allFlatItems($staffOnlyUser);

        $urls = collect($flat)->pluck('url');

        // Staff can see Dashboard and Flats
        $this->assertTrue($urls->contains(route('dashboard')));
        $this->assertTrue($urls->contains(route('flats.index')));

        // Staff cannot see money-only or admin-only routes
        $this->assertFalse($urls->contains(route('billing.generate')));
        $this->assertFalse($urls->contains(route('accounting.periods')));
    }
}
