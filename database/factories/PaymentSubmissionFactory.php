<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentSubmissionStatus;
use App\Models\Building;
use App\Models\Flat;
use App\Models\PaymentSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentSubmission> */
class PaymentSubmissionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'flat_id' => Flat::factory(),
            'user_id' => User::factory(),
            'amount' => '3000.00',
            'payment_method' => PaymentMethod::Bkash,
            'reference_number' => 'TRX'.fake()->bothify('##??####'),
            'payment_date' => now()->toDateString(),
            'slip_path' => null,
            'resident_notes' => null,
            'status' => PaymentSubmissionStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
            'payment_id' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentSubmissionStatus::Approved,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentSubmissionStatus::Rejected,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'rejection_reason' => 'Invalid transaction reference',
        ]);
    }
}
