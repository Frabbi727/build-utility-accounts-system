<?php

namespace Database\Factories;

use App\Enums\VendorBillStatus;
use App\Models\Vendor;
use App\Models\VendorBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VendorBill> */
class VendorBillFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'building_id' => null,
            'bill_no' => 'VB-'.fake()->unique()->numerify('######'),
            'vendor_reference' => null,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'total_amount' => '5000.00',
            'status' => VendorBillStatus::Unpaid,
            'description' => fake()->sentence(),
        ];
    }
}
