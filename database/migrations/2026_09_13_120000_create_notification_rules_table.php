<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('building_id')->nullable()->constrained('buildings')->cascadeOnDelete();
            $table->string('trigger_event', 50)->index();
            $table->integer('days_offset')->default(0);
            $table->string('title_template', 255);
            $table->text('body_template');
            $table->boolean('is_active')->default(true)->index();
            $table->jsonb('channels')->default('["push", "in_app"]');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_rules');
    }
};
