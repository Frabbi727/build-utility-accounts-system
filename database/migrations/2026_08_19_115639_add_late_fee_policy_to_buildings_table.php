<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The late-fee policy, alongside the existing `due_day_of_month`.
 *
 * This is a billing *policy* parameter, not a bill amount — unlike the old
 * `service_charge_rate`, nothing else expresses it, so it is not a second source of
 * truth. What a flat is billed each month still comes only from `charge_heads`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buildings', function (Blueprint $table): void {
            $table->string('late_fee_type')->default('none');
            $table->decimal('late_fee_amount', 15, 2)->default(0);
            $table->unsignedSmallInteger('late_fee_grace_days')->default(0);
        });

        DB::statement('
            ALTER TABLE buildings
            ADD CONSTRAINT buildings_late_fee_amount_non_negative
            CHECK (late_fee_amount >= 0)
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE buildings DROP CONSTRAINT IF EXISTS buildings_late_fee_amount_non_negative');

        Schema::table('buildings', function (Blueprint $table): void {
            $table->dropColumn(['late_fee_type', 'late_fee_amount', 'late_fee_grace_days']);
        });
    }
};
