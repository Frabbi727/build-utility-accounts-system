<?php

namespace Tests\Feature\Accounting;

use App\Enums\AccountCode;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\JournalService;
use App\Support\JournalLineData;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * "Full audit log" is one of the accounting non-negotiables: who posted or voided what,
 * and when. Every write goes through JournalService, so these assertions pin the two
 * places it records history.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->journal = app(JournalService::class);
    }

    private function postEntry(string $description = 'Donation received'): JournalEntry
    {
        return $this->journal->post(
            Carbon::parse('2026-08-19'),
            $description,
            [
                JournalLineData::debit($this->journal->account(AccountCode::CashInHand), '500.00'),
                JournalLineData::credit($this->journal->account(AccountCode::OtherIncome), '500.00'),
            ],
        );
    }

    public function test_posting_records_the_entry_its_description_and_every_line(): void
    {
        $entry = $this->postEntry();

        $log = AuditLog::where('action', 'journal_entry.posted')->sole();

        $this->assertSame($entry->getMorphClass(), $log->subject_type);
        $this->assertSame($entry->id, $log->subject_id);
        $this->assertSame($entry->ref_no, $log->payload['ref_no']);
        $this->assertSame('Donation received', $log->payload['description']);
        $this->assertCount(2, $log->payload['lines']);

        // The payload carries the money, so a tampered ledger row is still detectable.
        $this->assertSame('500.00', $log->payload['lines'][0]['debit']);
        $this->assertSame('500.00', $log->payload['lines'][1]['credit']);
        $this->assertNotNull($log->created_at);
    }

    public function test_it_records_who_posted_the_entry(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->postEntry();

        $this->assertSame($user->id, AuditLog::where('action', 'journal_entry.posted')->sole()->user_id);
    }

    public function test_an_unauthenticated_posting_is_still_logged_without_a_user(): void
    {
        $this->postEntry();

        $log = AuditLog::where('action', 'journal_entry.posted')->sole();

        // Seeders and console commands post with nobody logged in; the entry is still recorded.
        $this->assertNull($log->user_id);
    }

    public function test_reversing_records_a_separate_entry_naming_the_contra(): void
    {
        $entry = $this->postEntry();

        $reversal = $this->journal->reverse($entry, null, 'Duplicate receipt');

        $log = AuditLog::where('action', 'journal_entry.reversed')->sole();

        $this->assertSame($entry->id, $log->subject_id);
        $this->assertSame($reversal->ref_no, $log->payload['reversed_by']);
        $this->assertSame('Duplicate receipt', $log->payload['reason']);

        // The reversal is itself a posting, so the original plus both postings are logged.
        $this->assertSame(2, AuditLog::where('action', 'journal_entry.posted')->count());
    }

    public function test_a_rejected_entry_leaves_no_audit_trail(): void
    {
        try {
            $this->journal->post(
                Carbon::parse('2026-08-19'),
                'Unbalanced',
                [
                    JournalLineData::debit($this->journal->account(AccountCode::CashInHand), '500.00'),
                    JournalLineData::credit($this->journal->account(AccountCode::OtherIncome), '400.00'),
                ],
            );
        } catch (\Throwable) {
            // Expected: the entry never reaches the database.
        }

        $this->assertSame(0, AuditLog::count());
    }
}
