<?php

namespace Tests\Feature\Utilities;

use App\Models\Flat;
use App\Models\FlatUtilityConfig;
use App\Models\Meter;
use App\Models\Utility;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlatUtilityConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_flat_utility_config_with_enabled_and_disabled_states(): void
    {
        $flat = Flat::factory()->create();
        $utility1 = Utility::factory()->create();
        $utility2 = Utility::factory()->create();

        $config1 = FlatUtilityConfig::create([
            'flat_id' => $flat->id,
            'utility_id' => $utility1->id,
            'is_enabled' => true,
        ]);

        $config2 = FlatUtilityConfig::create([
            'flat_id' => $flat->id,
            'utility_id' => $utility2->id,
            'is_enabled' => false,
        ]);

        $this->assertDatabaseHas('flat_utility_configs', [
            'id' => $config1->id,
            'flat_id' => $flat->id,
            'utility_id' => $utility1->id,
            'is_enabled' => true,
        ]);

        $this->assertDatabaseHas('flat_utility_configs', [
            'id' => $config2->id,
            'flat_id' => $flat->id,
            'utility_id' => $utility2->id,
            'is_enabled' => false,
        ]);

        $this->assertTrue($config1->is_enabled);
        $this->assertFalse($config2->is_enabled);
    }

    public function test_flat_and_utility_relationships(): void
    {
        $flat = Flat::factory()->create();
        $utility = Utility::factory()->create();

        $config = FlatUtilityConfig::create([
            'flat_id' => $flat->id,
            'utility_id' => $utility->id,
            'is_enabled' => true,
        ]);

        $this->assertTrue($flat->utilityConfigs->contains($config));
        $this->assertTrue($utility->flatConfigs->contains($config));
        $this->assertTrue($config->flat->is($flat));
        $this->assertTrue($config->utility->is($utility));
    }

    public function test_flat_has_many_meters_relationship(): void
    {
        $flat = Flat::factory()->create();
        $meter = Meter::factory()->create([
            'building_id' => $flat->building_id,
            'flat_id' => $flat->id,
        ]);

        $this->assertTrue($flat->meters->contains($meter));
    }

    public function test_flat_and_utility_combination_must_be_unique(): void
    {
        $flat = Flat::factory()->create();
        $utility = Utility::factory()->create();

        FlatUtilityConfig::create([
            'flat_id' => $flat->id,
            'utility_id' => $utility->id,
            'is_enabled' => true,
        ]);

        $this->expectException(QueryException::class);

        FlatUtilityConfig::create([
            'flat_id' => $flat->id,
            'utility_id' => $utility->id,
            'is_enabled' => false,
        ]);
    }
}
