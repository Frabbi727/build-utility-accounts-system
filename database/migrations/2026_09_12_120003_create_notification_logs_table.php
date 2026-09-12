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
        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users', 'id', 'nl_user_id_foreign')->cascadeOnDelete();
            $table->foreignId('user_device_id')->nullable()->constrained('user_devices', 'id', 'nl_user_device_id_foreign')->nullOnDelete();
            $table->string('status', 20)->index();
            $table->text('error_message')->nullable();
            $table->jsonb('response_payload')->nullable();
            $table->timestamps();

            $table->index('notification_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
