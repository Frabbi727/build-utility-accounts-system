<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\BillItem;
use App\Models\ServiceChargeBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BillItem> */
class BillItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'service_charge_bill_id' => ServiceChargeBill::factory(),
            'account_id' => Account::factory(),
            'description' => fake()->words(3, true),
            'amount' => '3000.00',
        ];
    }
}
