<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One unit's share of a distributed cost.
 *
 * `weight` is what the basis produced — a flat 1 for an equal split, the floor area for
 * per_sqft, the units consumed for by_consumption. It is kept because it explains the
 * amount to an owner who asks, and because an edited line should still show what it
 * started from.
 *
 * The lines are computed while the distribution is a draft and **frozen at approval**, so
 * a flat added, deactivated or resized afterwards cannot silently move money that has
 * already been agreed. Their amounts sum to the distribution total exactly: bcdiv
 * truncates, and the leftover paisa goes to the lowest-id unit, the same rule
 * `ChargeCalculator` uses for equal-share heads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_distribution_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cost_distribution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->decimal('weight', 15, 4)->default(0);
            $table->decimal('amount', 15, 2);
            $table->foreignId('applied_to_bill_id')->nullable()
                ->constrained('service_charge_bills')->nullOnDelete();
            $table->timestamps();

            // One line per unit per distribution.
            $table->unique(['cost_distribution_id', 'flat_id']);
            $table->index(['flat_id', 'applied_to_bill_id']);
        });

        // A unit may legitimately owe nothing (it consumed nothing), but never a negative.
        DB::statement('
            ALTER TABLE cost_distribution_lines
            ADD CONSTRAINT cost_distribution_lines_amount_non_negative
            CHECK (amount >= 0)
        ');

        DB::statement('
            ALTER TABLE cost_distribution_lines
            ADD CONSTRAINT cost_distribution_lines_weight_non_negative
            CHECK (weight >= 0)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_distribution_lines');
    }
};
