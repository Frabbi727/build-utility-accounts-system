<?php

namespace Tests\Feature\Utilities;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Building;
use App\Models\Utility;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\UtilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilitySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_default_utilities_idempotently(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $building = Building::factory()->create();

        $seeder = new UtilitySeeder;
        $seeder->seedForBuilding($building);

        $incomeAccount = Account::where('is_postable', true)
            ->where('is_active', true)
            ->where('type', AccountType::Income)
            ->orderBy('code')
            ->first();

        $this->assertNotNull($incomeAccount);
        $this->assertSame(3, Utility::where('building_id', $building->id)->count());

        $this->assertDatabaseHas('utilities', [
            'building_id' => $building->id,
            'account_id' => $incomeAccount->id,
            'name' => 'Electricity',
            'name_bn' => 'বিদ্যুৎ',
            'unit_label' => 'kWh',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('utilities', [
            'building_id' => $building->id,
            'account_id' => $incomeAccount->id,
            'name' => 'Gas',
            'name_bn' => 'গ্যাস',
            'unit_label' => 'm³',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('utilities', [
            'building_id' => $building->id,
            'account_id' => $incomeAccount->id,
            'name' => 'Water',
            'name_bn' => 'পানি',
            'unit_label' => 'Unit',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        // Second run must not create duplicates
        $seeder->seedForBuilding($building);
        $this->assertSame(3, Utility::where('building_id', $building->id)->count());
    }

    public function test_run_seeds_for_all_buildings(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $buildings = Building::factory()->count(2)->create();

        $seeder = new UtilitySeeder;
        $seeder->run();

        foreach ($buildings as $building) {
            $this->assertSame(3, Utility::where('building_id', $building->id)->count());
        }
    }
}
