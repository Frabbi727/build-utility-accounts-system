<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_heads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            // The income account credited when this head is billed.
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('basis')->default('per_flat');
            $table->decimal('amount', 15, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['building_id', 'name']);
        });

        // Amounts are rates or monthly totals, never negative.
        DB::statement('ALTER TABLE charge_heads ADD CONSTRAINT charge_heads_amount_non_negative CHECK (amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_heads');
    }
};
