<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flat_charge_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_head_id')->constrained()->cascadeOnDelete();
            // A negotiated amount for this flat; ignored when is_exempt is true.
            $table->decimal('amount', 15, 2)->nullable();
            $table->boolean('is_exempt')->default(false);
            $table->timestamps();

            $table->unique(['flat_id', 'charge_head_id']);
        });

        DB::statement('ALTER TABLE flat_charge_overrides ADD CONSTRAINT flat_charge_overrides_amount_non_negative CHECK (amount IS NULL OR amount >= 0)');

        // An override must either exempt the flat or name an amount, never neither.
        DB::statement('ALTER TABLE flat_charge_overrides ADD CONSTRAINT flat_charge_overrides_meaningful CHECK (is_exempt OR amount IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('flat_charge_overrides');
    }
};
