<?php

namespace Database\Factories;

use App\Enums\NoticeType;
use App\Models\Building;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Notice> */
class NoticeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(),
            'content' => fake()->paragraphs(2, true),
            'type' => NoticeType::General,
            'is_pinned' => false,
            'published_at' => now(),
            'expires_at' => null,
        ];
    }

    public function emergency(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NoticeType::Emergency,
            'is_pinned' => true,
        ]);
    }

    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pinned' => true,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => now()->subDays(10),
            'expires_at' => now()->subDay(),
        ]);
    }
}
