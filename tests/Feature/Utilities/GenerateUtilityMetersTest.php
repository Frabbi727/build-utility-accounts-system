<?php

namespace Tests\Feature\Utilities;

use App\Models\Building;
use App\Models\Flat;
use App\Models\FlatUtilityConfig;
use App\Models\JournalLine;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\ServiceChargeBill;
use App\Models\Utility;
use App\Services\Utilities\GenerateUtilityMeters;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GenerateUtilityMetersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_previews_and_generates_meters_for_multiple_flats_and_utilities(): void
    {
        $building = Building::factory()->create();
        $flats = Flat::factory()->count(10)->create(['building_id' => $building->id]);
        $electricity = Utility::factory()->create(['building_id' => $building->id, 'name' => 'Electricity']);
        $gas = Utility::factory()->create(['building_id' => $building->id, 'name' => 'Gas']);

        $service = new GenerateUtilityMeters;
        $flatIds = $flats->pluck('id')->all();
        $utilityIds = [$electricity->id, $gas->id];

        // 1. Preview check
        $preview = $service->preview($building, $flatIds, $utilityIds);
        $this->assertSame(10, $preview->totalFlatsCount);
        $this->assertSame(20, $preview->eligibleMetersCount);
        $this->assertSame(0, $preview->skippedMetersCount);
        $this->assertSame(0, $preview->excludedFlatsCount);
        $this->assertSame([], $preview->skippedDetails);
        $this->assertSame([], $preview->inactiveWarnings);
        $this->assertSame([
            $electricity->id => 10,
            $gas->id => 10,
        ], $preview->utilityCounts);

        // 2. Execute generation
        $result = $service->handle(
            building: $building,
            flatIds: $flatIds,
            utilityIds: $utilityIds,
            utilityPrefixes: [
                $electricity->id => 'ELEC',
                $gas->id => 'GAS',
            ],
        );

        $this->assertSame(10, $result->flatsConsidered);
        $this->assertSame(20, $result->metersCreated);
        $this->assertSame(0, $result->metersSkipped);
        $this->assertSame(0, $result->flatsExcluded);
        $this->assertSame([], $result->errors);
        $this->assertSame(20, Meter::where('building_id', $building->id)->count());

        // 3. Assert meter numbering pattern: {PREFIX}-{FLAT_NUMBER}
        foreach ($flats as $flat) {
            $elecMeter = Meter::where('flat_id', $flat->id)->where('utility_id', $electricity->id)->firstOrFail();
            $this->assertSame("ELEC-{$flat->number}", $elecMeter->meter_no);
            $this->assertSame(6, $elecMeter->digits);
            $this->assertSame('0.000', (string) $elecMeter->initial_reading);
            $this->assertSame(now()->toDateString(), $elecMeter->installed_on?->toDateString());
            $this->assertTrue($elecMeter->is_active);

            $gasMeter = Meter::where('flat_id', $flat->id)->where('utility_id', $gas->id)->firstOrFail();
            $this->assertSame("GAS-{$flat->number}", $gasMeter->meter_no);
            $this->assertSame(6, $gasMeter->digits);
            $this->assertSame('0.000', (string) $gasMeter->initial_reading);
            $this->assertSame(now()->toDateString(), $gasMeter->installed_on?->toDateString());
            $this->assertTrue($gasMeter->is_active);
        }

        // 4. Assert downstream safety: 0 readings, 0 bills, 0 journal lines
        $this->assertSame(0, MeterReading::count());
        $this->assertSame(0, ServiceChargeBill::count());
        $this->assertSame(0, JournalLine::count());

        // 5. Assert idempotency: re-running handle() skips all existing meters
        $rerunResult = $service->handle($building, $flatIds, $utilityIds);
        $this->assertSame(10, $rerunResult->flatsConsidered);
        $this->assertSame(0, $rerunResult->metersCreated);
        $this->assertSame(20, $rerunResult->metersSkipped);
        $this->assertSame(0, $rerunResult->flatsExcluded);
        $this->assertSame(20, Meter::where('building_id', $building->id)->count());
    }

    public function test_it_respects_flat_exclusions_via_config_and_overrides(): void
    {
        $building = Building::factory()->create();
        $flat1 = Flat::factory()->create(['building_id' => $building->id, 'number' => '101']);
        $flat2 = Flat::factory()->create(['building_id' => $building->id, 'number' => '102']);
        $flat3 = Flat::factory()->create(['building_id' => $building->id, 'number' => '103']);

        $utility = Utility::factory()->create(['building_id' => $building->id, 'name' => 'Gas']);

        // Flat 1 is excluded via FlatUtilityConfig in database
        FlatUtilityConfig::create([
            'flat_id' => $flat1->id,
            'utility_id' => $utility->id,
            'is_enabled' => false,
        ]);

        $service = new GenerateUtilityMeters;
        $flatIds = [$flat1->id, $flat2->id, $flat3->id];
        $utilityIds = [$utility->id];

        // Flat 2 is excluded via inline flatUtilityOverrides
        $overrides = [
            "{$flat2->id}-{$utility->id}" => false,
        ];

        $preview = $service->preview(
            building: $building,
            flatIds: $flatIds,
            utilityIds: $utilityIds,
            flatUtilityOverrides: $overrides,
        );

        $this->assertSame(3, $preview->totalFlatsCount);
        $this->assertSame(1, $preview->eligibleMetersCount);
        $this->assertSame(0, $preview->skippedMetersCount);
        $this->assertSame(2, $preview->excludedFlatsCount);
        $this->assertCount(2, $preview->skippedDetails);

        // Run generation with persistOverrides = true
        $result = $service->handle(
            building: $building,
            flatIds: $flatIds,
            utilityIds: $utilityIds,
            utilityPrefixes: [$utility->id => 'GAS'],
            flatUtilityOverrides: $overrides,
            persistOverrides: true,
        );

        $this->assertSame(3, $result->flatsConsidered);
        $this->assertSame(1, $result->metersCreated);
        $this->assertSame(0, $result->metersSkipped);
        $this->assertSame(2, $result->flatsExcluded);

        // Only flat3 should have a meter created
        $this->assertDatabaseHas('meters', [
            'building_id' => $building->id,
            'flat_id' => $flat3->id,
            'utility_id' => $utility->id,
            'meter_no' => 'GAS-103',
        ]);
        $this->assertDatabaseMissing('meters', [
            'flat_id' => $flat1->id,
            'utility_id' => $utility->id,
        ]);
        $this->assertDatabaseMissing('meters', [
            'flat_id' => $flat2->id,
            'utility_id' => $utility->id,
        ]);

        // Verify flat2 override was persisted to FlatUtilityConfig
        $this->assertDatabaseHas('flat_utility_configs', [
            'flat_id' => $flat2->id,
            'utility_id' => $utility->id,
            'is_enabled' => false,
        ]);
    }

    public function test_it_identifies_inactive_meters_with_warnings_and_does_not_reactivate_or_duplicate(): void
    {
        $building = Building::factory()->create();
        $flat = Flat::factory()->create(['building_id' => $building->id, 'number' => '2A']);
        $utility = Utility::factory()->create(['building_id' => $building->id, 'name' => 'Electricity']);

        $existingInactiveMeter = Meter::factory()->create([
            'building_id' => $building->id,
            'flat_id' => $flat->id,
            'utility_id' => $utility->id,
            'meter_no' => 'OLD-ELEC-2A',
            'is_active' => false,
        ]);

        $service = new GenerateUtilityMeters;
        $preview = $service->preview($building, [$flat->id], [$utility->id]);

        $this->assertSame(1, $preview->totalFlatsCount);
        $this->assertSame(0, $preview->eligibleMetersCount);
        $this->assertSame(1, $preview->skippedMetersCount);
        $this->assertCount(1, $preview->inactiveWarnings);
        $this->assertSame('OLD-ELEC-2A', $preview->inactiveWarnings[0]['meter_no']);
        $this->assertSame($flat->id, $preview->inactiveWarnings[0]['flat_id']);

        $result = $service->handle($building, [$flat->id], [$utility->id], [$utility->id => 'ELEC']);

        $this->assertSame(1, $result->flatsConsidered);
        $this->assertSame(0, $result->metersCreated);
        $this->assertSame(1, $result->metersSkipped);

        // Meter remains inactive and unchanged
        $existingInactiveMeter->refresh();
        $this->assertFalse($existingInactiveMeter->is_active);
        $this->assertSame('OLD-ELEC-2A', $existingInactiveMeter->meter_no);

        // Total meters for building is still 1
        $this->assertSame(1, Meter::where('building_id', $building->id)->count());
    }

    public function test_it_enforces_building_isolation(): void
    {
        $buildingA = Building::factory()->create();
        $buildingB = Building::factory()->create();

        $flatA = Flat::factory()->create(['building_id' => $buildingA->id, 'number' => '1A']);
        $flatB = Flat::factory()->create(['building_id' => $buildingB->id, 'number' => '1A']);

        $utilityA = Utility::factory()->create(['building_id' => $buildingA->id, 'name' => 'Water']);
        $utilityB = Utility::factory()->create(['building_id' => $buildingB->id, 'name' => 'Water']);

        $service = new GenerateUtilityMeters;

        // Passing flatIds and utilityIds including foreign ones for building A
        $preview = $service->preview(
            building: $buildingA,
            flatIds: [$flatA->id, $flatB->id],
            utilityIds: [$utilityA->id, $utilityB->id],
        );

        // Foreign flats/utilities should be ignored by the building scope
        $this->assertSame(1, $preview->totalFlatsCount);
        $this->assertSame(1, $preview->eligibleMetersCount);

        $result = $service->handle(
            building: $buildingA,
            flatIds: [$flatA->id, $flatB->id],
            utilityIds: [$utilityA->id, $utilityB->id],
            utilityPrefixes: [$utilityA->id => 'WTR'],
        );

        $this->assertSame(1, $result->flatsConsidered);
        $this->assertSame(1, $result->metersCreated);

        $this->assertSame(1, Meter::where('building_id', $buildingA->id)->count());
        $this->assertSame(0, Meter::where('building_id', $buildingB->id)->count());
    }

    public function test_meter_number_collision_fallback(): void
    {
        $building = Building::factory()->create();
        $flat = Flat::factory()->create(['building_id' => $building->id, 'number' => '101']);
        $utility = Utility::factory()->create(['building_id' => $building->id, 'name' => 'Electricity']);

        // Another meter already took the number ELEC-101 (e.g. A common meter or another flat)
        Meter::factory()->create([
            'building_id' => $building->id,
            'utility_id' => $utility->id,
            'flat_id' => null,
            'meter_no' => 'ELEC-101',
        ]);

        $service = new GenerateUtilityMeters;
        $result = $service->handle(
            building: $building,
            flatIds: [$flat->id],
            utilityIds: [$utility->id],
            utilityPrefixes: [$utility->id => 'ELEC'],
        );

        $this->assertSame(1, $result->metersCreated);

        $newMeter = Meter::where('building_id', $building->id)
            ->where('flat_id', $flat->id)
            ->where('utility_id', $utility->id)
            ->firstOrFail();

        $this->assertStringStartsWith('ELEC-101-', $newMeter->meter_no);
    }

    public function test_handle_rolls_back_transaction_on_error(): void
    {
        $building = Building::factory()->create();
        $flat1 = Flat::factory()->create(['building_id' => $building->id, 'number' => '101']);
        $flat2 = Flat::factory()->create(['building_id' => $building->id, 'number' => '102']);
        $utility = Utility::factory()->create(['building_id' => $building->id, 'name' => 'Electricity']);

        // We simulate a failure on the 2nd meter creation
        Meter::creating(function (Meter $meter) {
            if ($meter->meter_no === 'ELEC-102') {
                throw new Exception('Simulated database write failure');
            }
        });

        $service = new GenerateUtilityMeters;

        try {
            $service->handle(
                building: $building,
                flatIds: [$flat1->id, $flat2->id],
                utilityIds: [$utility->id],
                utilityPrefixes: [$utility->id => 'ELEC'],
            );
            $this->fail('Expected Exception was not thrown.');
        } catch (Exception $e) {
            $this->assertSame('Simulated database write failure', $e->getMessage());
        }

        // Neither meter should exist due to rollback
        $this->assertSame(0, Meter::where('building_id', $building->id)->count());
    }
}
