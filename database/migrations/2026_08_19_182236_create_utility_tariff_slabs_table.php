<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One band of a progressive tariff: units from `from_unit` up to `to_unit` cost
 * `rate_per_unit` each.
 *
 * This is how DPDC, DESCO and Titas actually bill — the first 75 units at one rate, the
 * next 125 at a higher one, and so on, each band charged at its own rate rather than the
 * whole consumption jumping to the top rate. A building on a single flat rate has exactly
 * one slab, from 0 with no upper bound.
 *
 * `to_unit` null means unbounded, so the last slab absorbs any consumption. Slabs must be
 * contiguous and non-overlapping starting at 0; that is a whole-collection invariant no
 * column constraint can express, so it is enforced in App\Services\Billing\SlabValidator
 * when a tariff is saved.
 *
 * Quantities are scale 3 and the rate is scale 4 — neither is money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_tariff_slabs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('utility_tariff_id')->constrained()->cascadeOnDelete();
            $table->decimal('from_unit', 15, 3);
            $table->decimal('to_unit', 15, 3)->nullable();
            $table->decimal('rate_per_unit', 15, 4);
            $table->timestamps();

            $table->unique(['utility_tariff_id', 'from_unit']);
        });

        DB::statement('
            ALTER TABLE utility_tariff_slabs
            ADD CONSTRAINT utility_tariff_slabs_bounds_ordered
            CHECK (from_unit >= 0 AND (to_unit IS NULL OR to_unit > from_unit))
        ');

        DB::statement('
            ALTER TABLE utility_tariff_slabs
            ADD CONSTRAINT utility_tariff_slabs_rate_non_negative
            CHECK (rate_per_unit >= 0)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_tariff_slabs');
    }
};
