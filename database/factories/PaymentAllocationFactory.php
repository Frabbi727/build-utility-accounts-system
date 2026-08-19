<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\ServiceChargeBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentAllocation> */
class PaymentAllocationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'service_charge_bill_id' => ServiceChargeBill::factory(),
            'amount' => '3000.00',
        ];
    }
}
