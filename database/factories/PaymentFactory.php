<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Flat;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'flat_id' => Flat::factory(),
            'receipt_no' => 'RCPT-'.fake()->unique()->numerify('######'),
            'amount' => '3000.00',
            'method' => PaymentMethod::Cash,
            'received_on' => now()->toDateString(),
            'reference' => null,
            'received_by' => null,
        ];
    }
}
