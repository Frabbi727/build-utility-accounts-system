<?php

namespace Tests\Feature;

use App\Enums\AccountCode;
use App\Livewire\Dashboard;
use App\Livewire\GenerateBills;
use App\Livewire\Masters\ChargeHeadList;
use App\Livewire\RecordPaymentForm;
use App\Livewire\Reports\OwnerDues;
use App\Livewire\Setup\Wizard;
use App\Livewire\TrialBalance;
use App\Models\BillItem;
use App\Models\Building;
use App\Models\ChargeHead;
use App\Models\Flat;
use App\Models\FlatChargeOverride;
use App\Models\JournalEntry;
use App\Models\Owner;
use App\Models\ServiceChargeBill;
use App\Models\User;
use App\Services\JournalService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The whole product, from an empty database to a reconciled ledger.
 *
 * This is the walkthrough a new operator actually performs: land on the wizard,
 * build the building, configure what it charges, generate a month of bills, take a
 * payment, and check the books balance.
 */
class EndToEndSetupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // A production-shaped install: accounts, roles and one administrator.
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $this->admin = User::firstWhere('email', 'admin@example.com');
    }

    public function test_a_new_operator_gets_from_an_empty_database_to_a_balanced_ledger(): void
    {
        $journal = app(JournalService::class);

        // 1. A fresh install sends the administrator to the wizard.
        $this->actingAs($this->admin)->get(route('dashboard'))->assertRedirect(route('setup'));

        // 2. Building, floors and 24 flats across 6 levels.
        $wizard = Livewire::actingAs($this->admin)->test(Wizard::class)
            ->set('buildingName', 'Shapla Tower')
            ->set('address', 'House 12, Road 5, Dhanmondi, Dhaka')
            ->set('dueDayOfMonth', 10)
            ->call('saveBuilding')
            ->set('fromLevel', 1)
            ->set('toLevel', 6)
            ->set('suffixes', 'A,B,C,D')
            ->set('defaultSizeSqft', '1200')
            ->call('saveFlats')
            ->assertHasNoErrors();

        $building = Building::firstOrFail();
        $this->assertSame(24, Flat::count());
        $this->assertSame(6, $building->floors()->count());

        // 3. An owner, and the standard set of charge heads.
        $wizard->set('ownerName', 'Rahim Uddin')->call('addOwner')
            ->call('goToStep', 4)
            ->call('addSuggestedHeads');

        $this->assertSame(5, ChargeHead::count());

        // 4. Opening balances: what the building already held.
        $wizard->call('goToStep', 5)
            ->set('openingAsOf', '2026-07-01')
            ->set('cash', '5000')
            ->set('bank', '95000')
            ->call('saveOpeningBalances')
            ->assertSet('step', 6);

        $this->assertSame('100000.00', $journal->balanceFor($journal->account(AccountCode::GeneralFund)));

        // 5. The charge-head preview agrees with what will be billed.
        $firstFlat = Flat::orderBy('id')->firstOrFail();
        $preview = Livewire::actingAs($this->admin)->test(ChargeHeadList::class)->viewData('preview');
        $previewTotal = $preview->firstWhere('flat.id', $firstFlat->id)['total'];

        // 1200 guard + 500 lift + 400 electricity + (1.50 x 1200 = 1800) water
        // + cleaning: 8000/24 truncates to 333.33 each, which leaves 0.08 that the
        // lowest-id flat absorbs, so this flat pays 333.41.
        $this->assertSame('4233.41', $previewTotal);

        // 6. Generate the month.
        Livewire::actingAs($this->admin)->test(GenerateBills::class)
            ->assertSet('buildingId', $building->id)
            ->set('month', '2026-08')
            ->call('generate')
            ->assertHasNoErrors();

        $this->assertSame(24, ServiceChargeBill::count());

        $bill = ServiceChargeBill::where('flat_id', $firstFlat->id)->firstOrFail();
        $this->assertCount(5, $bill->items);
        $this->assertSame($previewTotal, (string) $bill->total_amount);
        $this->assertSame('2026-08-10', $bill->due_date->toDateString());

        // The cleaning contract is fully recovered across the 24 flats.
        $cleaning = ChargeHead::firstWhere('name', 'Cleaning Contract');
        $cleaningBilled = BillItem::where('description', $cleaning->displayName())->sum('amount');
        $this->assertSame('8000.00', bcadd((string) $cleaningBilled, '0', 2));

        // 7. Take a payment against the first flat's bill.
        Livewire::actingAs($this->admin)->test(RecordPaymentForm::class)
            ->set('flatId', $firstFlat->id)
            ->set('amount', (string) $bill->total_amount)
            ->set('method', 'cash')
            ->set('receivedOn', '2026-08-05')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('0.00', $journal->balanceFor(
            $journal->account(AccountCode::ServiceChargeReceivable),
            $firstFlat->id,
        ));

        // 8. Every entry ever posted is balanced, and the trial balance agrees.
        foreach (JournalEntry::with('lines')->get() as $entry) {
            $this->assertSame(
                bcadd((string) $entry->lines->sum('debit'), '0', 2),
                bcadd((string) $entry->lines->sum('credit'), '0', 2),
                "Entry {$entry->ref_no} is unbalanced.",
            );
        }

        $trialBalance = Livewire::actingAs($this->admin)->test(TrialBalance::class);
        $this->assertSame(
            bcadd((string) $trialBalance->viewData('totalDebit'), '0', 2),
            bcadd((string) $trialBalance->viewData('totalCredit'), '0', 2),
        );

        // 9. Owner dues show the 23 flats that have not paid, not the one that has.
        $dues = Livewire::actingAs($this->admin)->test(OwnerDues::class)->viewData('rows');
        $this->assertCount(23, collect($dues)->filter(
            fn (array $row): bool => bccomp((string) $row['outstanding'], '0', 2) > 0,
        ));

        // 10. Every screen still loads with real data behind it.
        foreach (['dashboard', 'flats.index', 'charge-heads.index', 'reports.trial-balance', 'reports.owner-dues'] as $name) {
            $this->actingAs($this->admin)->get(route($name))->assertOk("Route {$name} did not load.");
        }

        // The dashboard no longer nags now that flats and charge heads exist.
        $dashboard = Livewire::actingAs($this->admin)->test(Dashboard::class);
        $this->assertFalse($dashboard->viewData('needsFlats'));
        $this->assertFalse($dashboard->viewData('needsChargeHeads'));

        // 11. A per-flat exemption changes the next month's bill, not this one.
        $lift = ChargeHead::firstWhere('name', 'Lift Maintenance');
        FlatChargeOverride::create([
            'flat_id' => $firstFlat->id,
            'charge_head_id' => $lift->id,
            'is_exempt' => true,
        ]);

        Livewire::actingAs($this->admin)->test(GenerateBills::class)
            ->set('month', '2026-09')
            ->call('generate');

        $september = ServiceChargeBill::where('flat_id', $firstFlat->id)
            ->whereDate('billing_month', '2026-09-01')
            ->firstOrFail();

        $this->assertCount(4, $september->items);
        $this->assertSame(bcsub($previewTotal, '500.00', 2), (string) $september->total_amount);
        $this->assertSame((string) $bill->total_amount, (string) $bill->fresh()->total_amount);
    }
}
