<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-off charges against a single flat — a repair recharge, a penalty, a share of
 * something the building paid for on the owner's behalf.
 *
 * These are deliberately not charge heads: a charge head is what a building bills
 * *every* month. An ad-hoc charge is billed once, picked up by the next monthly bill
 * generated on or after its effective month, and stamped with the bill that took it so
 * re-running generation cannot charge it twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_hoc_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->date('effective_month');
            $table->foreignId('applied_to_bill_id')->nullable()
                ->constrained('service_charge_bills')->nullOnDelete();
            $table->timestamps();

            $table->index(['flat_id', 'applied_to_bill_id']);
        });

        DB::statement('
            ALTER TABLE ad_hoc_charges
            ADD CONSTRAINT ad_hoc_charges_amount_positive
            CHECK (amount > 0)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_hoc_charges');
    }
};
