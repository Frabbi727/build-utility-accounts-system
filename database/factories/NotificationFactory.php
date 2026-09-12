<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['BillGenerated', 'PaymentApproved', 'AdminNotification']),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'data' => ['key' => fake()->word()],
            'reference_type' => null,
            'reference_id' => null,
            'notification_key' => fake()->unique()->uuid(),
            'is_read' => false,
            'read_at' => null,
            'sent_at' => null,
            'status' => 'pending',
        ];
    }

    /**
     * Indicate that the notification has been sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Indicate that the notification has been read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}
