<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table): void {
            $table->timestamp('due_by')->nullable()->after('resolved_at');
            $table->string('before_photo_path')->nullable()->after('due_by');
            $table->string('after_photo_path')->nullable()->after('before_photo_path');
            $table->unsignedTinyInteger('rating')->nullable()->after('after_photo_path');
            $table->text('rating_comment')->nullable()->after('rating');
            $table->decimal('cost', 12, 2)->default('0.00')->after('rating_comment');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'due_by',
                'before_photo_path',
                'after_photo_path',
                'rating',
                'rating_comment',
                'cost',
            ]);
        });
    }
};
