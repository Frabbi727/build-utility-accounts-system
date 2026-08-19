<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a utility costs per unit, from a given date.
 *
 * Tariffs are versioned rather than edited: `effective_from` is the identity, and a rate
 * change is a new row. A bill issued in March must still compute to the same number in
 * December, which it cannot do if the rate it used was overwritten.
 *
 * The rate itself lives in `utility_tariff_slabs` — a single flat rate is one slab
 * covering 0 to infinity. `fixed_charge` is the monthly minimum / demand charge added
 * regardless of consumption (a meter that turned zero units still costs the building its
 * standing charge). Any separately-named demand charge folds into this one column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_tariffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('utility_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            // Null means "still current". Closed off when a later tariff supersedes it.
            $table->date('effective_to')->nullable();
            $table->decimal('fixed_charge', 15, 2)->default(0);
            $table->timestamps();

            // One tariff per utility per start date; two would make resolution ambiguous.
            $table->unique(['utility_id', 'effective_from']);
        });

        DB::statement('
            ALTER TABLE utility_tariffs
            ADD CONSTRAINT utility_tariffs_fixed_charge_non_negative
            CHECK (fixed_charge >= 0)
        ');

        DB::statement('
            ALTER TABLE utility_tariffs
            ADD CONSTRAINT utility_tariffs_period_ordered
            CHECK (effective_to IS NULL OR effective_to >= effective_from)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_tariffs');
    }
};
