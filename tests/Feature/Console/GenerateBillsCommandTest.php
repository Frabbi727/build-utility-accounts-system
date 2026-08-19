<?php

namespace Tests\Feature\Console;

use App\Models\Building;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateBillsCommandTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->building = Building::factory()->flatRate('3000.00')->create();
        Flat::factory()->count(2)->for($this->building)->create();
    }

    public function test_it_generates_bills_for_the_given_month(): void
    {
        $this->artisan('billing:generate', ['--month' => '2026-07'])
            ->assertSuccessful();

        $this->assertSame(2, ServiceChargeBill::whereDate('billing_month', '2026-07-01')->count());
    }

    public function test_it_is_safe_to_run_twice(): void
    {
        $this->artisan('billing:generate', ['--month' => '2026-07'])->assertSuccessful();
        $this->artisan('billing:generate', ['--month' => '2026-07'])->assertSuccessful();

        $this->assertSame(2, ServiceChargeBill::count());
    }

    public function test_it_can_be_restricted_to_one_building(): void
    {
        $other = Building::factory()->flatRate('1000.00')->create();
        Flat::factory()->for($other)->create();

        $this->artisan('billing:generate', ['--month' => '2026-07', '--building' => $this->building->id])
            ->assertSuccessful();

        $this->assertSame(2, ServiceChargeBill::count());
    }

    public function test_it_rejects_an_unreadable_month(): void
    {
        $this->artisan('billing:generate', ['--month' => 'July'])->assertFailed();

        $this->assertSame(0, ServiceChargeBill::count());
    }
}
