<?php

namespace Database\Factories;

use App\Enums\NotificationTriggerEvent;
use App\Models\Building;
use App\Models\NotificationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationRule>
 */
class NotificationRuleFactory extends Factory
{
    protected $model = NotificationRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var NotificationTriggerEvent $event */
        $event = fake()->randomElement(NotificationTriggerEvent::cases());

        return [
            'building_id' => null,
            'trigger_event' => $event,
            'days_offset' => $event->defaultDaysOffset(),
            'title_template' => 'Notification for Flat {flat_number}',
            'body_template' => 'Hello {resident_name}, notification regarding {flat_number}.',
            'is_active' => true,
            'channels' => ['push', 'in_app'],
        ];
    }

    public function forBuilding(Building|int $building): static
    {
        return $this->state(fn (array $attributes): array => [
            'building_id' => $building instanceof Building ? $building->id : $building,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function trigger(NotificationTriggerEvent $event): static
    {
        return $this->state(fn (array $attributes): array => [
            'trigger_event' => $event,
            'days_offset' => $event->defaultDaysOffset(),
        ]);
    }
}
