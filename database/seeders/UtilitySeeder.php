<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Building;
use App\Models\Utility;
use Illuminate\Database\Seeder;

class UtilitySeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, name_bn: string, unit_label: string, sort_order: int}>
     */
    private const DEFAULTS = [
        [
            'name' => 'Electricity',
            'name_bn' => 'বিদ্যুৎ',
            'unit_label' => 'kWh',
            'sort_order' => 1,
        ],
        [
            'name' => 'Gas',
            'name_bn' => 'গ্যাস',
            'unit_label' => 'm³',
            'sort_order' => 2,
        ],
        [
            'name' => 'Water',
            'name_bn' => 'পানি',
            'unit_label' => 'Unit',
            'sort_order' => 3,
        ],
    ];

    public function run(): void
    {
        foreach (Building::all() as $building) {
            $this->seedForBuilding($building);
        }
    }

    public function seedForBuilding(Building $building): void
    {
        $account = Account::where('is_postable', true)
            ->where('is_active', true)
            ->where('type', AccountType::Income)
            ->orderBy('code')
            ->first();

        if ($account === null) {
            return;
        }

        foreach (self::DEFAULTS as $data) {
            Utility::firstOrCreate(
                [
                    'building_id' => $building->id,
                    'name' => $data['name'],
                ],
                [
                    'account_id' => $account->id,
                    'name_bn' => $data['name_bn'],
                    'unit_label' => $data['unit_label'],
                    'sort_order' => $data['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}
