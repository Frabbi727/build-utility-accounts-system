<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ChartOfAccountsSeeder::class,
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            DemoBuildingSeeder::class,
            ChargeHeadsSeeder::class,
            VendorsAndStaffSeeder::class,
        ]);
    }
}
