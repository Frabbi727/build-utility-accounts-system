<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserDevice>
 */
class UserDeviceFactory extends Factory
{
    protected $model = UserDevice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_id' => fake()->unique()->uuid(),
            'device_token' => fake()->unique()->sha256(),
            'platform' => fake()->randomElement(['android', 'ios']),
            'device_model' => fake()->randomElement(['Pixel 8', 'Samsung S24', 'iPhone 15']),
            'os_version' => fake()->numerify('##.#'),
            'app_version' => fake()->numerify('#.#.#'),
            'is_active' => true,
            'last_seen_at' => now(),
        ];
    }

    /**
     * Indicate that the device is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
