<?php

namespace Database\Factories;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Models\Building;
use App\Models\Flat;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceRequest> */
class MaintenanceRequestFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'flat_id' => Flat::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'category' => MaintenanceCategory::Plumbing,
            'priority' => MaintenancePriority::Medium,
            'status' => MaintenanceStatus::Open,
            'assigned_staff_id' => null,
            'assigned_vendor_id' => null,
            'resolution_notes' => null,
            'resolved_at' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MaintenanceStatus::InProgress,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MaintenanceStatus::Resolved,
            'resolved_at' => now(),
            'resolution_notes' => 'Resolved successfully',
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MaintenanceStatus::Closed,
            'resolved_at' => now(),
            'resolution_notes' => 'Issue closed',
        ]);
    }
}
