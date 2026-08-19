<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A one-off cost spread across units — a generator repair, road work, an Eid bonus.
 *
 * The lifecycle is draft → approved → billed, and each step matters:
 *
 * - **Draft** computes per-unit lines from the chosen basis. They are editable, which is
 *   what replaces any need for saved weighting rules: pick a basis to get a sensible
 *   starting table, then adjust whatever the basis could not know.
 * - **Approving freezes the lines and posts nothing to the ledger.** The money is accrued
 *   when the bill is generated. Posting at approval *as well* would double-count the
 *   income, and because both entries would balance, a ledger-balance check would not
 *   catch it.
 * - Billing stamps each line, so a re-run cannot charge it twice.
 *
 * `recovery_account_id` is an **income** account, deliberately not the account the source
 * expense was debited to. Recovering a cost from owners is revenue: `Dr Receivable /
 * Cr Recovery Income`. Crediting the expense account instead would quietly understate
 * expenses in every report, and JournalService would accept it without complaint because
 * it never inspects account types.
 *
 * `source_expense_id` is provenance only — it is never read while posting.
 *
 * Splitting a cost over several months creates one sibling distribution per month rather
 * than instalment rows, so there is no second level of remainder arithmetic and each
 * month stays independently correctable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_distributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recovery_account_id')->constrained('accounts')->restrictOnDelete();
            // Provenance: which expense this recovers. Never used to decide the posting.
            $table->foreignId('source_expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            // Only meaningful for the by_consumption basis.
            $table->foreignId('utility_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('description')->nullable();
            $table->decimal('total_amount', 15, 2);
            $table->string('basis');
            $table->date('billing_month');
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['building_id', 'billing_month']);
        });

        DB::statement('
            ALTER TABLE cost_distributions
            ADD CONSTRAINT cost_distributions_total_positive
            CHECK (total_amount > 0)
        ');

        // An approved distribution records who approved it and when; a draft records neither.
        DB::statement("
            ALTER TABLE cost_distributions
            ADD CONSTRAINT cost_distributions_approved_is_stamped
            CHECK (status <> 'approved' OR approved_at IS NOT NULL)
        ");

        // by_consumption needs something to measure; the other bases must not carry one.
        DB::statement("
            ALTER TABLE cost_distributions
            ADD CONSTRAINT cost_distributions_utility_matches_basis
            CHECK ((basis = 'by_consumption') = (utility_id IS NOT NULL))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_distributions');
    }
};
