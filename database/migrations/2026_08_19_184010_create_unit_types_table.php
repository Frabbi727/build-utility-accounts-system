<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What kind of unit a `flat` row is — Shop, Office, Flat, Garage, Godown.
 *
 * A table rather than an enum because the answer differs per deployment: a market has
 * shops and godowns, an apartment building has flats and garages, and an operator must be
 * able to add one without a release.
 *
 * `flats` stays one table. Splitting shops into their own would need a second dimension on
 * journal_lines, bills, payments and every report, which is a far larger change than the
 * one thing actually needed: telling a shop apart from a flat when deciding what to bill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['building_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_types');
    }
};
