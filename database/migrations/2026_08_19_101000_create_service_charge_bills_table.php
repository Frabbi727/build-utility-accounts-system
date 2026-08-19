<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_charge_bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->string('bill_no')->unique();
            $table->date('billing_month');
            $table->date('due_date');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('status')->default('unpaid');
            $table->timestamps();

            // Guarantees bill generation is idempotent per flat per month.
            $table->unique(['flat_id', 'billing_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_charge_bills');
    }
};
