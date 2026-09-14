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
        Schema::create('backups', function (Blueprint $table): void {
            $table->id();
            $table->string('backup_name')->unique();
            $table->string('backup_type')->default('automatic'); // automatic, manual, safety
            $table->string('environment')->default('production');
            $table->string('file_path');
            $table->string('disk')->default('local');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('database_name')->nullable();
            $table->string('checksum', 64)->nullable(); // sha256
            $table->string('status')->default('pending'); // pending, running, completed, failed, corrupted, restoring, restored
            $table->string('restore_status')->nullable(); // pending, running, completed, failed, recovered
            $table->text('error_message')->nullable();
            $table->boolean('is_protected')->default(false);
            $table->jsonb('metadata')->nullable();
            $table->timestamp('retention_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('backup_type');
            $table->index('retention_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
