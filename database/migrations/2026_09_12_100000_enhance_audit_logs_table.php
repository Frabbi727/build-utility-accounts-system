<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('module')->nullable()->after('action')->index();
            $table->string('entity_type')->nullable()->after('subject_id')->index();
            $table->string('entity_id')->nullable()->after('entity_type')->index();
            $table->json('old_values')->nullable()->after('entity_id');
            $table->json('new_values')->nullable()->after('old_values');
            $table->json('changed_fields')->nullable()->after('new_values');
            $table->text('description')->nullable()->after('changed_fields');
            $table->string('request_id', 64)->nullable()->after('description')->index();
            $table->string('source', 50)->nullable()->after('request_id')->index();
            $table->string('platform', 50)->nullable()->after('source');
            $table->string('app_version', 50)->nullable()->after('platform');
            $table->string('ip_address', 45)->nullable()->after('app_version');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->string('route')->nullable()->after('user_agent');
            $table->string('http_method', 10)->nullable()->after('route');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropColumn([
                'module',
                'entity_type',
                'entity_id',
                'old_values',
                'new_values',
                'changed_fields',
                'description',
                'request_id',
                'source',
                'platform',
                'app_version',
                'ip_address',
                'user_agent',
                'route',
                'http_method',
            ]);
        });
    }
};
