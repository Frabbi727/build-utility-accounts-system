<?php

namespace Tests\Feature\Layout;

use App\Enums\Role;
use App\Models\Building;
use App\Models\User;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->building = Building::factory()->create(['name' => 'Rose Villa']);
        app(CurrentBuilding::class)->set($this->building->id);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Admin->value);
    }

    public function test_guests_do_not_see_sidebar(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertDontSee('x-persist="main-sidebar"', false);
        $response->assertDontSee('aria-label="Toggle sidebar collapse"', false);
        $response->assertDontSee('aria-label="Open mobile menu"', false);
        $response->assertDontSee('open-spotlight', false);
    }

    public function test_layout_renders_sidebar_with_brand_and_navigation_groups(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertOk();

        // Check persistent wrapper
        $response->assertSee('x-persist="main-sidebar"', false);

        // Check app brand and building name
        $response->assertSee(config('app.name'));
        $response->assertSee('Rose Villa');

        // Check top-level navigation entries
        $response->assertSee(__('nav.dashboard'));
        $response->assertSee(__('nav.property_management'));
        $response->assertSee(__('nav.residents_community'));
        $response->assertSee(__('nav.utilities_metering'));
        $response->assertSee(__('nav.billing_collections'));
        $response->assertSee(__('nav.expenses_payables'));
        $response->assertSee(__('nav.accounts_reports'));
        $response->assertSee(__('nav.administration'));

        // Check wire:navigate and wire:current.exact are used on navigation links
        $response->assertSee('wire:navigate', false);
        $response->assertSee('wire:current.exact="!bg-slate-900 !text-white font-medium"', false);
        $response->assertSee('wire:current.exact="!bg-slate-100 !text-slate-900 !font-semibold"', false);

        // Check zero-FOUC hooks and styles
        $response->assertSee('data-sidebar', false);
        $response->assertSee('data-sidebar-expanded', false);
        $response->assertSee('data-sidebar-collapsed', false);
        $response->assertSee('html.sidebar-collapsed aside[data-sidebar]', false);

        // Check accessibility attributes
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-modal="true"', false);
        $response->assertSee('aria-label="Mobile Navigation"', false);
        $response->assertSee('aria-haspopup="true"', false);
    }

    public function test_active_routes_receive_active_styling_directives(): void
    {
        // On dashboard route: Dashboard link has exact active directive
        $responseDashboard = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $responseDashboard->assertOk();
        $responseDashboard->assertSee('wire:current.exact="!bg-slate-900 !text-white font-medium"', false);

        // On flats route: Flats link is rendered with exact active directive
        $responseFlats = $this->actingAs($this->admin)
            ->get(route('flats.index'));

        $responseFlats->assertOk();
        $responseFlats->assertSee(__('nav.flats'));
        $responseFlats->assertSee('wire:current.exact="!bg-slate-100 !text-slate-900 !font-semibold"', false);
    }

    public function test_search_trigger_button_is_present_with_open_spotlight_dispatch(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('$dispatch(\'open-spotlight\')', false);
        $response->assertSee('window.Livewire?.dispatch(\'open-spotlight\')', false);
        $response->assertSee(__('billing.search_flat'));
    }

    public function test_desktop_and_mobile_sidebar_controls_are_rendered(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertOk();

        // Alpine root state for sidebar
        $response->assertSee('sidebar_collapsed', false);
        $response->assertSee('toggleCollapse()', false);
        $response->assertSee('mobileOpen', false);

        // Desktop toggle and mobile menu buttons
        $response->assertSee('aria-label="Toggle sidebar collapse"', false);
        $response->assertSee('aria-label="Open mobile menu"', false);
    }

    public function test_sidebar_alpine_store_and_navigation_sync_are_rendered(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Alpine.store(\'sidebar\'', false);
        $response->assertSee('isActive(url)', false);
        $response->assertSee('isCategoryActive(items)', false);
        $response->assertSee('updateCurrentPath()', false);
        $response->assertSee('livewire:navigated', false);
        $response->assertSee('w-[4.5rem]', false);
        $response->assertSee('$store.sidebar.collapsed', false);
        $response->assertSee('$store.sidebar.mobileOpen', false);
        $response->assertSee('$store.sidebar.isActive', false);
    }
}
