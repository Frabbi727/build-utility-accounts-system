<?php

namespace Database\Seeders;

use App\Enums\AccountCode;
use App\Models\Account;
use App\Models\Building;
use App\Models\Staff;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Demo vendors and staff for local development. Not for production.
 */
class VendorsAndStaffSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Dhaka Lift Services', 'phone' => '01711000001'],
            ['name' => 'Clean Sweep Ltd', 'phone' => '01711000002'],
            ['name' => 'Rahim Electricals', 'phone' => '01711000003'],
        ] as $vendor) {
            Vendor::firstOrCreate(['name' => $vendor['name']], $vendor + ['is_active' => true]);
        }

        $building = Building::first();

        if ($building === null) {
            return;
        }

        foreach ([
            ['name' => 'Abdul Karim', 'designation' => 'Guard', 'salary' => '12000.00', 'code' => AccountCode::GuardSalary],
            ['name' => 'Nasima Begum', 'designation' => 'Cleaner', 'salary' => '9000.00', 'code' => AccountCode::Cleaning],
        ] as $member) {
            Staff::firstOrCreate(
                ['name' => $member['name'], 'building_id' => $building->id],
                [
                    'designation' => $member['designation'],
                    'phone' => '01822000000',
                    'monthly_salary' => $member['salary'],
                    'expense_account_id' => Account::where('code', $member['code']->value)->value('id'),
                    'joined_on' => now()->subYears(2),
                    'is_active' => true,
                ],
            );
        }
    }
}
