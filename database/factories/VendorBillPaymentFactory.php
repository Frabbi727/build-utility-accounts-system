<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\VendorBill;
use App\Models\VendorBillPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VendorBillPayment> */
class VendorBillPaymentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'vendor_bill_id' => VendorBill::factory(),
            'voucher_no' => 'PV-'.fake()->unique()->numerify('######'),
            'amount' => '5000.00',
            'method' => PaymentMethod::Bank,
            'paid_on' => now()->toDateString(),
            'reference' => null,
            'paid_by' => null,
        ];
    }
}
