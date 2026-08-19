<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes a bill line self-describing.
 *
 * A metered line has to print as "Electricity 120.000 kWh x 8.5000 = 1020.00", which the
 * amount alone cannot express. Quantity, rate and unit are denormalised onto the line
 * deliberately: tariffs are versioned and utility names change, but a bill that has been
 * handed to an owner must render identically forever.
 *
 * `source_type`/`source_id` point back at whatever produced the line — a charge head, an
 * ad-hoc charge, a meter reading, a cost distribution line. That is what makes every
 * taka on a bill traceable to its origin without guessing from the description text.
 *
 * All three detail columns are nullable because a flat-amount line (a charge head, a late
 * fee) has no quantity or rate, and rows that predate this migration have neither.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_items', function (Blueprint $table): void {
            $table->decimal('quantity', 15, 3)->nullable()->after('description');
            $table->decimal('unit_rate', 15, 4)->nullable()->after('quantity');
            $table->string('unit_label')->nullable()->after('unit_rate');
            $table->nullableMorphs('source');
        });

        // A quantity may legitimately be zero, but never negative.
        DB::statement('
            ALTER TABLE bill_items
            ADD CONSTRAINT bill_items_quantity_non_negative
            CHECK (quantity IS NULL OR quantity >= 0)
        ');

        DB::statement('
            ALTER TABLE bill_items
            ADD CONSTRAINT bill_items_unit_rate_non_negative
            CHECK (unit_rate IS NULL OR unit_rate >= 0)
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bill_items DROP CONSTRAINT IF EXISTS bill_items_quantity_non_negative');
        DB::statement('ALTER TABLE bill_items DROP CONSTRAINT IF EXISTS bill_items_unit_rate_non_negative');

        Schema::table('bill_items', function (Blueprint $table): void {
            $table->dropMorphs('source');
            $table->dropColumn(['quantity', 'unit_rate', 'unit_label']);
        });
    }
};
