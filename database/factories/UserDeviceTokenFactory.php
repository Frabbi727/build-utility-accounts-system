<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserDeviceToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserDeviceToken>
 */
class UserDeviceTokenFactory extends Factory
{
    protected $model = UserDeviceToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => fake()->unique()->uuid(),
            'device_type' => 'android',
        ];
    }
}
