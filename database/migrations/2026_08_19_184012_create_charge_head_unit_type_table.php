<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which unit types a charge head applies to.
 *
 * **An empty set means the head applies to every unit.** That is what keeps every existing
 * building billing exactly as it did before this table existed, and it is the assumption
 * `ChargeCalculator` is written around — do not invert it to "empty means nobody".
 *
 * The consequence worth knowing: an `equal_share` head restricted to shops divides its
 * monthly total across the *shops*, not across every flat in the building. Each head
 * therefore has its own divisor and its own remainder absorber.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_head_unit_type', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('charge_head_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_type_id')->constrained()->cascadeOnDelete();

            $table->unique(['charge_head_id', 'unit_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_head_unit_type');
    }
};
