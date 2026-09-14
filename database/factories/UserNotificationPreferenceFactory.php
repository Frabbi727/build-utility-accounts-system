<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserNotificationPreference> */
class UserNotificationPreferenceFactory extends Factory
{
    protected $model = UserNotificationPreference::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'channel' => 'push',
            'category' => 'bills',
            'is_enabled' => true,
        ];
    }
}
