<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A physical meter, either on one unit or on the building as a whole.
 *
 * `flat_id` null marks a **common meter** — the building's main incoming meter, or one on
 * a shared load like the lift or the pump. A common meter is never billed to anybody: its
 * job is to be a denominator and a loss check (`main - sum(sub-meters)` is system loss or
 * theft). The money the building actually owes is the utility's invoice, recorded as an
 * expense, and recovered from units through a cost distribution.
 *
 * `digits` is the dial count, which is what makes rollover computable: a 5-digit meter
 * reading 99990 last month and 10 this month turned over, it did not run backwards.
 *
 * A meter is never edited to reflect a replacement — deactivate it and create a new one
 * with the new `initial_reading`, so the old readings keep explaining the old bills.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->foreignId('utility_id')->constrained()->restrictOnDelete();
            // Null = a common meter for the whole building, billed to nobody.
            $table->foreignId('flat_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('meter_no');
            $table->unsignedTinyInteger('digits')->default(6);
            $table->decimal('initial_reading', 15, 3)->default(0);
            $table->date('installed_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['utility_id', 'meter_no']);
            $table->index(['building_id', 'is_active']);
        });

        DB::statement('
            ALTER TABLE meters
            ADD CONSTRAINT meters_initial_reading_non_negative
            CHECK (initial_reading >= 0)
        ');

        // A meter with no dials cannot roll over, and 10^0 would break the wrap maths.
        DB::statement('ALTER TABLE meters ADD CONSTRAINT meters_digits_positive CHECK (digits > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('meters');
    }
};
