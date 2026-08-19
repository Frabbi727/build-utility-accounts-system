<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A metered thing a building sells on to its units — electricity, gas, water.
 *
 * Deliberately a table and not an enum. A market bills gas and electricity, an office
 * block might bill chilled water, and the operator must be able to add one without a
 * deployment. The same reasoning as charge heads.
 *
 * `unit_label` (kWh, m3, unit) is what the bill prints beside the quantity. It is copied
 * onto the bill line at billing time rather than joined at print time, because a renamed
 * unit must not change a bill an owner already holds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            // The income account credited when consumption of this utility is billed.
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('unit_label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['building_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utilities');
    }
};
