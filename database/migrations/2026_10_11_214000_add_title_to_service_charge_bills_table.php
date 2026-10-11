<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_charge_bills', function (Blueprint $table) {
            $table->string('title')->nullable()->after('bill_no');
        });

        // Safely backfill existing historical records
        $bills = DB::table('service_charge_bills')->whereNull('title')->get();
        foreach ($bills as $bill) {
            $month = Carbon::parse($bill->billing_month)->format('F Y');
            DB::table('service_charge_bills')
                ->where('id', $bill->id)
                ->update(['title' => "Service Charge Bill — {$month}"]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_charge_bills', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
