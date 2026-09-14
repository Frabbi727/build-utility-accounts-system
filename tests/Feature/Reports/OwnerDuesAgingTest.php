<?php

namespace Tests\Feature\Reports;

use App\Enums\Role;
use App\Livewire\Reports\OwnerDues;
use App\Models\Building;
use App\Models\Flat;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class OwnerDuesAgingTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat1;

    private Flat $flat2;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->building = Building::factory()->flatRate('2000.00')->create();
        app(CurrentBuilding::class)->set($this->building->id);

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole(Role::Accountant->value);

        $this->flat1 = Flat::factory()->for($this->building)->create(['number' => '101']);
        $this->flat2 = Flat::factory()->for($this->building)->create(['number' => '102']);
    }

    public function test_owner_dues_filters_by_aging_bucket(): void
    {
        $this->actingAs($this->accountant);

        // Generate bill in June (over 60 days overdue by September)
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-06-01'));

        Livewire::test(OwnerDues::class)
            ->set('asOf', '2026-09-01')
            ->assertSee('101')
            ->assertSee('102')
            ->set('bucketFilter', 'days_61_90')
            ->assertSee('101')
            ->set('bucketFilter', 'days_90_plus')
            ->assertOk();
    }
}
