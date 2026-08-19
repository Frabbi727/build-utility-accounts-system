<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('building_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bill_no')->unique();
            $table->string('vendor_reference')->nullable();
            $table->date('bill_date');
            $table->date('due_date');
            $table->decimal('total_amount', 15, 2);
            $table->string('status')->default('unpaid');
            $table->string('description');
            $table->timestamps();

            $table->index('bill_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bills');
    }
};
