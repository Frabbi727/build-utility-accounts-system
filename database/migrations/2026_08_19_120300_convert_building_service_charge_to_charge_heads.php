<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Charge heads replace the single rate that used to live on `buildings`.
 *
 * Keeping both would leave two sources of truth for what a flat owes, so each
 * building's existing rate becomes one "Service Charge" head and the columns go.
 */
return new class extends Migration
{
    public function up(): void
    {
        $serviceChargeIncomeId = DB::table('accounts')->where('code', '4010')->value('id');

        if ($serviceChargeIncomeId !== null) {
            $buildings = DB::table('buildings')
                ->select('id', 'service_charge_mode', 'service_charge_rate')
                ->get();

            foreach ($buildings as $building) {
                if (bccomp((string) $building->service_charge_rate, '0', 2) <= 0) {
                    continue;
                }

                DB::table('charge_heads')->insert([
                    'building_id' => $building->id,
                    'account_id' => $serviceChargeIncomeId,
                    'name' => 'Service Charge',
                    'name_bn' => 'সার্ভিস চার্জ',
                    'basis' => $building->service_charge_mode === 'per_sqft' ? 'per_sqft' : 'per_flat',
                    'amount' => $building->service_charge_rate,
                    'sort_order' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('buildings', function (Blueprint $table): void {
            $table->dropColumn(['service_charge_mode', 'service_charge_rate']);
        });
    }

    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table): void {
            $table->string('service_charge_mode')->default('flat_rate');
            $table->decimal('service_charge_rate', 15, 2)->default(0);
        });
    }
};
