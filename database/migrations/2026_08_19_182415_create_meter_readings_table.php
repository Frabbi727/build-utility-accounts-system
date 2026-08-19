<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a meter read for one month, and what that consumption is worth.
 *
 * Three decisions worth knowing before changing anything here:
 *
 * 1. **`consumption` is stored, not derived.** It is normally
 *    `current_reading - previous_reading`, but a meter that rolls past all-nines makes
 *    that difference negative, and a replaced meter makes the pair of readings stop
 *    explaining the consumption at all. It is computed once, at entry, and the
 *    calculator reads only this column.
 *
 * 2. **`utility_tariff_id` is stamped when the reading is confirmed**, not resolved when
 *    the bill is generated. A reading confirmed in July can land on a September bill —
 *    resolving by date at that point would price it at a rate that did not exist when it
 *    was taken. Stamping also defines when a tariff becomes immutable: the moment any
 *    reading points at it.
 *
 * 3. **`status` gates billing.** Only a confirmed reading is billable, which is what lets
 *    a consumption-based cost distribution be approved against a settled set of numbers
 *    without bill generation ever having to compute one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meter_id')->constrained()->cascadeOnDelete();
            // Null while the reading is still a draft; set when it is confirmed.
            $table->foreignId('utility_tariff_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('billing_month');
            $table->date('reading_date');
            $table->decimal('previous_reading', 15, 3);
            $table->decimal('current_reading', 15, 3);
            $table->decimal('consumption', 15, 3);
            $table->boolean('is_estimated')->default(false);
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->foreignId('applied_to_bill_id')->nullable()
                ->constrained('service_charge_bills')->nullOnDelete();
            $table->timestamps();

            // One reading per meter per month makes entry and billing both idempotent.
            $table->unique(['meter_id', 'billing_month']);
            $table->index(['status', 'applied_to_bill_id']);
        });

        // Rollover is resolved at entry, so a stored consumption is never negative.
        DB::statement('
            ALTER TABLE meter_readings
            ADD CONSTRAINT meter_readings_consumption_non_negative
            CHECK (consumption >= 0)
        ');

        DB::statement('
            ALTER TABLE meter_readings
            ADD CONSTRAINT meter_readings_dials_non_negative
            CHECK (previous_reading >= 0 AND current_reading >= 0)
        ');

        // A confirmed reading must carry the tariff it was priced at; a draft must not,
        // because nothing has been settled yet.
        DB::statement("
            ALTER TABLE meter_readings
            ADD CONSTRAINT meter_readings_confirmed_has_tariff
            CHECK (status <> 'confirmed' OR utility_tariff_id IS NOT NULL)
        ");

        // Only a confirmed reading can have reached a bill.
        DB::statement("
            ALTER TABLE meter_readings
            ADD CONSTRAINT meter_readings_billed_only_when_confirmed
            CHECK (applied_to_bill_id IS NULL OR status = 'confirmed')
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_readings');
    }
};
