<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When this bill was last charged a late fee.
 *
 * A bill is charged at most once per calendar month, so the fee run is idempotent
 * without needing a separate table: a bill whose stamp already falls in the run's
 * month is skipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_charge_bills', function (Blueprint $table): void {
            $table->date('last_late_fee_on')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('service_charge_bills', function (Blueprint $table): void {
            $table->dropColumn('last_late_fee_on');
        });
    }
};
