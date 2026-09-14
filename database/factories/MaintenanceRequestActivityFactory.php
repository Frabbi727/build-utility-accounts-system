<?php

namespace Database\Factories;

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceRequestActivity> */
class MaintenanceRequestActivityFactory extends Factory
{
    protected $model = MaintenanceRequestActivity::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'maintenance_request_id' => MaintenanceRequest::factory(),
            'user_id' => User::factory(),
            'type' => 'created',
            'description' => 'Maintenance request created',
            'metadata' => null,
        ];
    }
}
