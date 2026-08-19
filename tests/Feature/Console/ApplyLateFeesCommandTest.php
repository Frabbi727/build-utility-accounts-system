<?php

namespace Tests\Feature\Console;

use App\Enums\LateFeeType;
use App\Models\Building;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Services\Billing\GenerateMonthlyBills;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ApplyLateFeesCommandTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->building = Building::factory()
            ->flatRate('3000.00')
            ->lateFee(LateFeeType::Fixed, '200.00')
            ->create(['due_day_of_month' => 10]);

        Flat::factory()->for($this->building)->create();

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-06-01'));
    }

    public function test_it_charges_overdue_bills(): void
    {
        $this->artisan('billing:late-fees', ['--on' => '2026-06-20'])->assertSuccessful();

        $this->assertSame('3200.00', (string) ServiceChargeBill::sole()->total_amount);
    }

    public function test_running_it_daily_charges_only_once_a_month(): void
    {
        foreach (['2026-06-15', '2026-06-16', '2026-06-17'] as $day) {
            $this->artisan('billing:late-fees', ['--on' => $day])->assertSuccessful();
        }

        $this->assertSame('3200.00', (string) ServiceChargeBill::sole()->total_amount);
    }

    public function test_it_rejects_an_unreadable_date(): void
    {
        $this->artisan('billing:late-fees', ['--on' => 'yesterday-ish'])->assertFailed();

        $this->assertSame('3000.00', (string) ServiceChargeBill::sole()->total_amount);
    }
}
