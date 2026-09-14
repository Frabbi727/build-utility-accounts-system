<?php

namespace Tests\Feature\Livewire;

use App\Enums\Role;
use App\Livewire\GenerateBills;
use App\Models\Building;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GenerateBillsScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Building $building;

    private Flat $flat1;

    private Flat $flat2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ChartOfAccountsSeeder::class, RolesAndPermissionsSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Admin->value);

        $this->building = Building::factory()->flatRate('3500.00')->create(['due_day_of_month' => 10]);
        $this->flat1 = Flat::factory()->for($this->building)->create(['number' => '101']);
        $this->flat2 = Flat::factory()->for($this->building)->create(['number' => '102']);
    }

    public function test_admin_can_load_generate_bills_screen(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(GenerateBills::class)
            ->assertOk()
            ->assertSee(__('billing.generate_monthly_bills'))
            ->assertSee(__('billing.simulate_btn'));
    }

    public function test_can_run_simulation_and_view_results(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(GenerateBills::class)
            ->set('buildingId', $this->building->id)
            ->set('month', '2026-08')
            ->call('simulate')
            ->assertSee(__('billing.simulation_title'))
            ->assertSee('101')
            ->assertSee('102')
            ->assertSee('7,000.00')
            ->assertSee(__('billing.ready_to_bill'));
    }

    public function test_can_search_and_filter_simulation_results(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(GenerateBills::class)
            ->set('buildingId', $this->building->id)
            ->set('month', '2026-08')
            ->call('simulate')
            ->set('search', '101')
            ->assertSee('101')
            ->assertDontSee('102');
    }

    public function test_can_confirm_and_generate_bills_from_screen(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(GenerateBills::class)
            ->set('buildingId', $this->building->id)
            ->set('month', '2026-08')
            ->call('askGenerate')
            ->assertSet('pendingConfirm.action', 'generate')
            ->call('runConfirmed')
            ->assertSee(__('billing.bills_generated', ['count' => 2]));

        $this->assertSame(2, ServiceChargeBill::count());
    }
}
