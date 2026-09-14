<?php

namespace Tests\Feature\Billing;

use App\Enums\ReadingStatus;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Utility;
use App\Services\Billing\MeterReadingCsvService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MeterReadingCsvServiceTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Utility $utility;

    private Flat $flat;

    private Meter $meter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->building = Building::factory()->create();
        $this->flat = Flat::factory()->for($this->building)->create(['number' => '201']);
        $this->utility = Utility::factory()->electricity()->create(['building_id' => $this->building->id]);
        $this->meter = Meter::factory()->for($this->flat)->create([
            'building_id' => $this->building->id,
            'utility_id' => $this->utility->id,
            'meter_no' => 'MTR-201',
            'initial_reading' => '50.000',
        ]);
    }

    public function test_it_exports_meter_reading_csv_template(): void
    {
        $service = app(MeterReadingCsvService::class);
        $csv = $service->export($this->building, Carbon::parse('2026-08-01'));

        $this->assertStringContainsString('meter_id,flat_number,utility,meter_no', $csv);
        $this->assertStringContainsString((string) $this->meter->id, $csv);
        $this->assertStringContainsString('201', $csv);
        $this->assertStringContainsString('MTR-201', $csv);
        $this->assertStringContainsString('50.000', $csv);
    }

    public function test_it_imports_meter_readings_from_csv(): void
    {
        $service = app(MeterReadingCsvService::class);

        $csv = "meter_id,flat_number,utility,meter_no,previous_reading,current_reading,reading_date,is_estimated,note\n"
            ."{$this->meter->id},201,Electricity,MTR-201,50.000,180.000,2026-08-25,no,Routine check\n";

        $result = $service->import($this->building, Carbon::parse('2026-08-01'), $csv);

        $this->assertSame(1, $result['saved']);
        $this->assertSame(0, $result['skipped']);
        $this->assertEmpty($result['errors']);

        $reading = MeterReading::where('meter_id', $this->meter->id)
            ->whereDate('billing_month', '2026-08-01')
            ->firstOrFail();

        $this->assertSame('180.000', (string) $reading->current_reading);
        $this->assertSame('130.000', (string) $reading->consumption);
        $this->assertSame('2026-08-25', $reading->reading_date->toDateString());
        $this->assertSame(ReadingStatus::Draft, $reading->status);
        $this->assertSame('Routine check', $reading->note);
    }

    public function test_it_validates_and_skips_invalid_rows_during_import(): void
    {
        $service = app(MeterReadingCsvService::class);

        $csv = "meter_id,flat_number,utility,meter_no,previous_reading,current_reading,reading_date,is_estimated,note\n"
            ."99999,999,Electricity,MTR-NONE,0,200.000,2026-08-25,no,\n"
            ."{$this->meter->id},201,Electricity,MTR-201,50.000,INVALID_TEXT,2026-08-25,no,\n";

        $result = $service->import($this->building, Carbon::parse('2026-08-01'), $csv);

        $this->assertSame(0, $result['saved']);
        $this->assertSame(2, $result['skipped']);
        $this->assertCount(2, $result['errors']);
    }
}
