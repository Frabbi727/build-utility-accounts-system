<?php

namespace Database\Factories;

use App\Enums\BillStatus;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceChargeBill> */
class ServiceChargeBillFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'flat_id' => Flat::factory(),
            'bill_no' => 'BILL-'.fake()->unique()->numerify('######'),
            'billing_month' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->startOfMonth()->addDays(9)->toDateString(),
            'total_amount' => '3000.00',
            'status' => BillStatus::Unpaid,
        ];
    }
}
