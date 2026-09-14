<?php

namespace Tests\Feature\Billing;

use App\Enums\ReadingStatus;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Utility;
use App\Models\UtilityTariff;
use App\Services\Billing\MeterReadingAnomalyDetector;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MeterReadingAnomalyDetectorTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    private Utility $utility;

    private Meter $meter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->building = Building::factory()->create();
        $this->flat = Flat::factory()->for($this->building)->create(['is_active' => true]);
        $this->utility = Utility::factory()->electricity()->create(['building_id' => $this->building->id]);
        $this->meter = Meter::factory()->for($this->flat)->create([
            'building_id' => $this->building->id,
            'utility_id' => $this->utility->id,
            'initial_reading' => '100.000',
        ]);
    }

    public function test_it_computes_3_month_rolling_average_and_detects_spikes(): void
    {
        $tariff = UtilityTariff::factory()->for($this->utility)->flatRate('8.5000')->create();

        // Month 1: 100 consumption
        MeterReading::factory()->for($this->meter)->create([
            'utility_tariff_id' => $tariff->id,
            'billing_month' => '2026-05-01',
            'previous_reading' => '100.000',
            'current_reading' => '200.000',
            'consumption' => '100.000',
            'status' => ReadingStatus::Confirmed,
        ]);

        // Month 2: 100 consumption
        MeterReading::factory()->for($this->meter)->create([
            'utility_tariff_id' => $tariff->id,
            'billing_month' => '2026-06-01',
            'previous_reading' => '200.000',
            'current_reading' => '300.000',
            'consumption' => '100.000',
            'status' => ReadingStatus::Confirmed,
        ]);

        // Month 3: 100 consumption
        MeterReading::factory()->for($this->meter)->create([
            'utility_tariff_id' => $tariff->id,
            'billing_month' => '2026-07-01',
            'previous_reading' => '300.000',
            'current_reading' => '400.000',
            'consumption' => '100.000',
            'status' => ReadingStatus::Confirmed,
        ]);

        $detector = app(MeterReadingAnomalyDetector::class);

        // August prospect: current reading 600 -> consumption 200 (100% higher than average 100)
        $anomaly = $detector->detect($this->meter, '600.000', Carbon::parse('2026-08-01'), '400.000');

        $this->assertSame('100.000', $anomaly->averageConsumption);
        $this->assertSame(100.0, $anomaly->variancePercentage);
        $this->assertTrue($anomaly->hasSpike);
        $this->assertFalse($anomaly->isNegative);
        $this->assertFalse($anomaly->isZeroOccupied);
        $this->assertNotNull($anomaly->warningMessage);
    }

    public function test_it_detects_negative_reading_and_zero_usage_on_active_unit(): void
    {
        $detector = app(MeterReadingAnomalyDetector::class);

        // Current reading 50 when previous was 100 -> negative reading
        $negativeAnomaly = $detector->detect($this->meter, '50.000', Carbon::parse('2026-08-01'), '100.000');
        $this->assertTrue($negativeAnomaly->isNegative);

        // Current reading 100 when previous was 100 -> 0 usage on active unit
        $zeroAnomaly = $detector->detect($this->meter, '100.000', Carbon::parse('2026-08-01'), '100.000');
        $this->assertTrue($zeroAnomaly->isZeroOccupied);
    }
}
