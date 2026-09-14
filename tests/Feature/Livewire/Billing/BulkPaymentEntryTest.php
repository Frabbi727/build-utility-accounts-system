<?php

namespace Tests\Feature\Livewire\Billing;

use App\Enums\AccountCode;
use App\Enums\Role;
use App\Livewire\Billing\BulkPaymentEntry;
use App\Models\Building;
use App\Models\Flat;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class BulkPaymentEntryTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private User $accountant;

    private JournalService $journal;

    /** @var list<Flat> */
    private array $flats;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->journal = app(JournalService::class);

        $this->building = Building::factory()->flatRate('2500.00')->create();
        app(CurrentBuilding::class)->set($this->building->id);

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole(Role::Accountant->value);

        $this->flats = [
            Flat::factory()->for($this->building)->create(['number' => '101']),
            Flat::factory()->for($this->building)->create(['number' => '102']),
            Flat::factory()->for($this->building)->create(['number' => '103']),
        ];
    }

    public function test_accountant_can_view_bulk_payment_entry_screen(): void
    {
        $this->actingAs($this->accountant);

        Livewire::test(BulkPaymentEntry::class)
            ->assertOk()
            ->assertSee('101')
            ->assertSee('102')
            ->assertSee('103');
    }

    public function test_auto_fill_dues_populates_amounts_for_flats_with_unpaid_bills(): void
    {
        $this->actingAs($this->accountant);

        // Generate bills for the month so flats have dues
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        Livewire::test(BulkPaymentEntry::class)
            ->call('autoFillDues')
            ->assertSet("entries.{$this->flats[0]->id}.enabled", true)
            ->assertSet("entries.{$this->flats[0]->id}.amount", '2500.00')
            ->assertSet("entries.{$this->flats[1]->id}.enabled", true)
            ->assertSet("entries.{$this->flats[1]->id}.amount", '2500.00');
    }

    public function test_saving_bulk_payments_records_all_selected_payments_atomically(): void
    {
        $this->actingAs($this->accountant);

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        $flat1 = $this->flats[0];
        $flat2 = $this->flats[1];

        Livewire::test(BulkPaymentEntry::class)
            ->set('defaultReceivedOn', '2026-08-20')
            ->set("entries.{$flat1->id}.enabled", true)
            ->set("entries.{$flat1->id}.amount", '2500.00')
            ->set("entries.{$flat1->id}.method", 'cash')
            ->set("entries.{$flat1->id}.reference", 'Cash receipt 01')
            ->set("entries.{$flat2->id}.enabled", true)
            ->set("entries.{$flat2->id}.amount", '2500.00')
            ->set("entries.{$flat2->id}.method", 'bank')
            ->set("entries.{$flat2->id}.reference", 'EFT 9988')
            ->call('save')
            ->assertHasNoErrors();

        // Both bills should now be fully paid
        $receivable = $this->journal->account(AccountCode::ServiceChargeReceivable);
        $this->assertSame('0.00', $this->journal->balanceFor($receivable, $flat1->id));
        $this->assertSame('0.00', $this->journal->balanceFor($receivable, $flat2->id));

        // Cash and Bank accounts should have received the money
        $this->assertSame('2500.00', $this->journal->balanceFor($this->journal->account(AccountCode::CashInHand)));
        $this->assertSame('2500.00', $this->journal->balanceFor($this->journal->account(AccountCode::Bank)));
    }

    public function test_validation_fails_if_enabled_entry_has_no_amount(): void
    {
        $this->actingAs($this->accountant);

        $flat1 = $this->flats[0];

        Livewire::test(BulkPaymentEntry::class)
            ->set("entries.{$flat1->id}.enabled", true)
            ->set("entries.{$flat1->id}.amount", '')
            ->call('save')
            ->assertHasErrors(["entries.{$flat1->id}.amount"]);
    }
}
