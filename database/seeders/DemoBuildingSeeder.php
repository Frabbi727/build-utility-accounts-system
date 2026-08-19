<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Floor;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A small demo building for local development. Not for production.
 */
class DemoBuildingSeeder extends Seeder
{
    public function run(): void
    {
        $building = Building::firstOrCreate(
            ['name' => 'Shapla Tower'],
            [
                'name_bn' => 'শাপলা টাওয়ার',
                'address' => 'House 12, Road 5, Dhanmondi, Dhaka',
                'due_day_of_month' => 10,
            ],
        );

        foreach (range(1, 4) as $level) {
            $floor = Floor::firstOrCreate(
                ['building_id' => $building->id, 'level' => $level],
                ['name' => "Level {$level}"],
            );

            foreach (['A', 'B'] as $suffix) {
                $number = "{$level}{$suffix}";

                $owner = Owner::firstOrCreate(
                    ['email' => strtolower("owner{$number}@example.com")],
                    ['name' => "Owner {$number}", 'phone' => '01700000000'],
                );

                if ($owner->user_id === null) {
                    $user = User::updateOrCreate(
                        ['email' => $owner->email],
                        ['name' => $owner->name, 'password' => Hash::make('password')],
                    );
                    $user->syncRoles([Role::Owner->value]);

                    $owner->user_id = $user->id;
                    $owner->save();
                }

                Flat::firstOrCreate(
                    ['building_id' => $building->id, 'number' => $number],
                    [
                        'floor_id' => $floor->id,
                        'owner_id' => $owner->id,
                        'size_sqft' => $suffix === 'A' ? '1200.00' : '1450.00',
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
