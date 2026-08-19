<?php

namespace Database\Seeders;

use App\Enums\AccountCode;
use App\Enums\ChargeBasis;
use App\Models\Account;
use App\Models\Building;
use App\Models\ChargeHead;
use Illuminate\Database\Seeder;

/**
 * A realistic set of monthly charge heads for the demo building.
 *
 * These are the components a Dhaka apartment building typically bills: fixed
 * per-flat amounts for shared staff and equipment, a per-sqft water rate, and a
 * cleaning contract split evenly. Not for production.
 */
class ChargeHeadsSeeder extends Seeder
{
    /**
     * @var list<array{name: string, name_bn: string, basis: ChargeBasis, amount: string}>
     */
    private const HEADS = [
        ['name' => 'Guard Salary Share', 'name_bn' => 'নিরাপত্তা কর্মীর বেতন', 'basis' => ChargeBasis::PerFlat, 'amount' => '1200.00'],
        ['name' => 'Lift Maintenance', 'name_bn' => 'লিফট রক্ষণাবেক্ষণ', 'basis' => ChargeBasis::PerFlat, 'amount' => '500.00'],
        ['name' => 'Common Electricity', 'name_bn' => 'সাধারণ বিদ্যুৎ', 'basis' => ChargeBasis::PerFlat, 'amount' => '400.00'],
        ['name' => 'Water (WASA)', 'name_bn' => 'পানি (ওয়াসা)', 'basis' => ChargeBasis::PerSqft, 'amount' => '1.50'],
        ['name' => 'Cleaning Contract', 'name_bn' => 'পরিচ্ছন্নতা চুক্তি', 'basis' => ChargeBasis::EqualShare, 'amount' => '8000.00'],
    ];

    public function run(): void
    {
        $income = Account::firstWhere('code', AccountCode::ServiceChargeIncome->value);

        if ($income === null) {
            return;
        }

        foreach (Building::all() as $building) {
            foreach (self::HEADS as $index => $head) {
                ChargeHead::firstOrCreate(
                    ['building_id' => $building->id, 'name' => $head['name']],
                    [
                        'account_id' => $income->id,
                        'name_bn' => $head['name_bn'],
                        'basis' => $head['basis'],
                        'amount' => $head['amount'],
                        'sort_order' => $index,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
