<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nullable on purpose. Every existing flat predates unit types and has no answer, and a
 * building that never needs the distinction should never be forced to invent one.
 *
 * `restrictOnDelete` so a type still in use cannot be deleted out from under its flats —
 * WithCrudModal already catches that QueryException and reports it as a blocked delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flats', function (Blueprint $table): void {
            $table->foreignId('unit_type_id')->nullable()->after('owner_id')
                ->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('flats', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_type_id');
        });
    }
};
