<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->foreignId('flat_id')->nullable()->constrained()->nullOnDelete();
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'flat_id']);
        });

        // A line is either a debit or a credit, never both, never neither, never negative.
        DB::statement('
            ALTER TABLE journal_lines
            ADD CONSTRAINT journal_lines_debit_xor_credit
            CHECK (
                debit >= 0 AND credit >= 0
                AND (debit = 0) <> (credit = 0)
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
