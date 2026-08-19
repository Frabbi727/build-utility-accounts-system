<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\VendorBill;
use App\Models\VendorBillItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VendorBillItem> */
class VendorBillItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'vendor_bill_id' => VendorBill::factory(),
            'account_id' => Account::factory(),
            'description' => fake()->words(3, true),
            'amount' => '5000.00',
        ];
    }
}
