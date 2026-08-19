<?php

namespace Tests\Feature\Accounting;

use App\Enums\AccountCode;
use App\Exceptions\InvalidJournalEntryException;
use App\Exceptions\PeriodLockedException;
use App\Exceptions\UnbalancedEntryException;
use App\Models\AccountingPeriod;
use App\Models\Flat;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\JournalService;
use App\Support\JournalLineData;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class JournalServiceTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->journal = app(JournalService::class);
    }

    public function test_it_posts_a_balanced_entry(): void
    {
        $cash = $this->journal->account(AccountCode::CashInHand);
        $income = $this->journal->account(AccountCode::OtherIncome);

        $entry = $this->journal->post(
            Carbon::parse('2026-08-19'),
            'Donation received',
            [
                JournalLineData::debit($cash, '500.00'),
                JournalLineData::credit($income, '500.00'),
            ],
        );

        $this->assertSame(2, $entry->lines->count());
        $this->assertSame('500.00', $this->journal->balanceFor($cash));
        $this->assertSame('500.00', $this->journal->balanceFor($income));
        $this->assertNotNull($entry->ref_no);
    }

    public function test_an_unbalanced_entry_throws_and_persists_nothing(): void
    {
        $cash = $this->journal->account(AccountCode::CashInHand);
        $income = $this->journal->account(AccountCode::OtherIncome);

        try {
            $this->journal->post(
                Carbon::parse('2026-08-19'),
                'Bad entry',
                [
                    JournalLineData::debit($cash, '500.00'),
                    JournalLineData::credit($income, '400.00'),
                ],
            );
            $this->fail('Expected UnbalancedEntryException.');
        } catch (UnbalancedEntryException) {
            // expected
        }

        $this->assertSame(0, JournalEntry::count());
        $this->assertSame(0, JournalLine::count());
    }

    public function test_a_single_line_entry_is_rejected(): void
    {
        $this->expectException(InvalidJournalEntryException::class);

        $this->journal->post(
            Carbon::parse('2026-08-19'),
            'Half an entry',
            [JournalLineData::debit($this->journal->account(AccountCode::CashInHand), '500.00')],
        );
    }

    public function test_it_refuses_to_post_into_a_locked_period(): void
    {
        AccountingPeriod::factory()->locked()->create(['year' => 2026, 'month' => 8]);

        $this->expectException(PeriodLockedException::class);

        $this->journal->post(
            Carbon::parse('2026-08-19'),
            'Too late',
            [
                JournalLineData::debit($this->journal->account(AccountCode::CashInHand), '500.00'),
                JournalLineData::credit($this->journal->account(AccountCode::OtherIncome), '500.00'),
            ],
        );

        $this->assertSame(0, JournalEntry::count());
    }

    public function test_reversal_creates_a_contra_entry_and_nets_to_zero(): void
    {
        $cash = $this->journal->account(AccountCode::CashInHand);
        $income = $this->journal->account(AccountCode::OtherIncome);

        $entry = $this->journal->post(
            Carbon::parse('2026-08-19'),
            'Donation received',
            [
                JournalLineData::debit($cash, '500.00'),
                JournalLineData::credit($income, '500.00'),
            ],
        );

        $reversal = $this->journal->reverse($entry->fresh('lines'));

        $this->assertSame('0.00', $this->journal->balanceFor($cash));
        $this->assertSame('0.00', $this->journal->balanceFor($income));
        $this->assertSame($reversal->id, $entry->fresh()->reversed_by_id);
        // The original is preserved, never deleted.
        $this->assertSame(2, JournalEntry::count());
    }

    public function test_an_entry_cannot_be_reversed_twice(): void
    {
        $entry = $this->journal->post(
            Carbon::parse('2026-08-19'),
            'Donation received',
            [
                JournalLineData::debit($this->journal->account(AccountCode::CashInHand), '500.00'),
                JournalLineData::credit($this->journal->account(AccountCode::OtherIncome), '500.00'),
            ],
        );

        $this->journal->reverse($entry->fresh('lines'));

        $this->expectException(InvalidJournalEntryException::class);
        $this->journal->reverse($entry->fresh('lines'));
    }

    public function test_flat_dimension_scopes_the_receivable_sub_ledger(): void
    {
        $receivable = $this->journal->account(AccountCode::ServiceChargeReceivable);
        $income = $this->journal->account(AccountCode::ServiceChargeIncome);
        $flatA = Flat::factory()->create();
        $flatB = Flat::factory()->create();

        $this->journal->post(Carbon::parse('2026-08-19'), 'Charge A', [
            JournalLineData::debit($receivable, '3000.00', $flatA->id),
            JournalLineData::credit($income, '3000.00'),
        ]);

        $this->journal->post(Carbon::parse('2026-08-19'), 'Charge B', [
            JournalLineData::debit($receivable, '4500.00', $flatB->id),
            JournalLineData::credit($income, '4500.00'),
        ]);

        $this->assertSame('3000.00', $this->journal->balanceFor($receivable, $flatA->id));
        $this->assertSame('4500.00', $this->journal->balanceFor($receivable, $flatB->id));
        $this->assertSame('7500.00', $this->journal->balanceFor($receivable));
    }
}
