<?php

namespace Tests\Feature\Billing;

use App\Enums\AccountCode;
use App\Enums\DistributionStatus;
use App\Enums\Role;
use App\Livewire\Billing\CostDistributionList;
use App\Models\Building;
use App\Models\CostDistribution;
use App\Models\Flat;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CostDistributionScreenTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->create();
        Flat::factory()->count(2)->for($this->building)->create(['size_sqft' => '1000.00']);

        app(CurrentBuilding::class)->set($this->building->id);
    }

    private function accountant(): User
    {
        $user = User::factory()->create();
        $user->syncRoles([Role::Accountant->value]);

        return $user;
    }

    private function incomeAccountId(): int
    {
        return app(JournalService::class)->account(AccountCode::OtherIncome)->id;
    }

    public function test_an_accountant_can_raise_a_shared_cost(): void
    {
        Livewire::actingAs($this->accountant())
            ->test(CostDistributionList::class)
            ->call('create')
            ->set('title', 'Generator repair')
            ->set('totalAmount', '60000')
            ->set('recoveryAccountId', $this->incomeAccountId())
            ->set('basis', 'equal')
            ->set('billingMonth', '2026-08')
            ->call('save')
            ->assertHasNoErrors();

        $distribution = CostDistribution::firstOrFail();

        $this->assertSame('60000.00', (string) $distribution->total_amount);
        $this->assertSame(DistributionStatus::Draft, $distribution->status);
    }

    public function test_spreading_over_months_creates_one_sibling_per_month(): void
    {
        Livewire::actingAs($this->accountant())
            ->test(CostDistributionList::class)
            ->call('create')
            ->set('title', 'Generator repair')
            ->set('totalAmount', '60000')
            ->set('recoveryAccountId', $this->incomeAccountId())
            ->set('basis', 'equal')
            ->set('billingMonth', '2026-08')
            ->set('months', 6)
            ->call('save')
            ->assertHasNoErrors();

        $siblings = CostDistribution::orderBy('billing_month')->get();

        $this->assertCount(6, $siblings);
        $this->assertSame('10000.00', (string) $siblings->first()->total_amount);
        $this->assertSame('2026-08-01', $siblings->first()->billing_month->toDateString());
        $this->assertSame('2027-01-01', $siblings->last()->billing_month->toDateString());

        // The months add back up to what was actually spent.
        $total = $siblings->reduce(fn (string $c, CostDistribution $d): string => bcadd($c, (string) $d->total_amount, 2), '0.00');
        $this->assertSame('60000.00', $total);
    }

    public function test_an_awkward_total_spread_over_months_still_sums_exactly(): void
    {
        Livewire::actingAs($this->accountant())
            ->test(CostDistributionList::class)
            ->call('create')
            ->set('title', 'Road work')
            ->set('totalAmount', '10000')
            ->set('recoveryAccountId', $this->incomeAccountId())
            ->set('basis', 'equal')
            ->set('billingMonth', '2026-08')
            ->set('months', 3)
            ->call('save')
            ->assertHasNoErrors();

        $siblings = CostDistribution::orderBy('billing_month')->get();

        // 10000 / 3 truncates to 3333.33; the first month absorbs the stray paisa.
        $this->assertSame('3333.34', (string) $siblings[0]->total_amount);
        $this->assertSame('3333.33', (string) $siblings[1]->total_amount);
        $this->assertSame('3333.33', (string) $siblings[2]->total_amount);
    }

    public function test_a_shared_cost_cannot_be_credited_to_an_expense_account(): void
    {
        Livewire::actingAs($this->accountant())
            ->test(CostDistributionList::class)
            ->call('create')
            ->set('title', 'Generator repair')
            ->set('totalAmount', '60000')
            ->set('recoveryAccountId', app(JournalService::class)->account(AccountCode::GeneratorFuel)->id)
            ->set('basis', 'equal')
            ->set('billingMonth', '2026-08')
            ->call('save')
            ->assertHasErrors('recoveryAccountId');

        $this->assertSame(0, CostDistribution::count());
    }

    public function test_a_consumption_split_requires_a_utility(): void
    {
        Livewire::actingAs($this->accountant())
            ->test(CostDistributionList::class)
            ->call('create')
            ->set('title', 'Electricity recovery')
            ->set('totalAmount', '5000')
            ->set('recoveryAccountId', $this->incomeAccountId())
            ->set('basis', 'by_consumption')
            ->set('billingMonth', '2026-08')
            ->call('save')
            ->assertHasErrors('utilityId');
    }

    public function test_viewing_the_split_computes_the_draft_lines(): void
    {
        $distribution = CostDistribution::factory()->create([
            'building_id' => $this->building->id,
            'recovery_account_id' => $this->incomeAccountId(),
            'total_amount' => '10000.00',
        ]);

        Livewire::actingAs($this->accountant())
            ->test(CostDistributionList::class)
            ->call('viewSplit', $distribution->id)
            ->assertHasNoErrors();

        $this->assertCount(2, $distribution->refresh()->lines);
        $this->assertSame('10000.00', $distribution->allocatedAmount());
    }

    public function test_an_edited_split_can_be_saved_and_approved(): void
    {
        $distribution = CostDistribution::factory()->create([
            'building_id' => $this->building->id,
            'recovery_account_id' => $this->incomeAccountId(),
            'total_amount' => '10000.00',
        ]);

        $component = Livewire::actingAs($this->accountant())
            ->test(CostDistributionList::class)
            ->call('viewSplit', $distribution->id);

        $lines = $distribution->refresh()->lines->sortBy('flat_id')->values();

        $component
            ->set("lineAmounts.{$lines[0]->id}", '7000')
            ->set("lineAmounts.{$lines[1]->id}", '3000')
            ->call('saveSplit')
            ->call('approve', $distribution->id)
            ->assertHasNoErrors();

        $distribution->refresh();

        $this->assertSame(DistributionStatus::Approved, $distribution->status);
        $this->assertSame('7000.00', (string) $distribution->lines()->orderBy('flat_id')->first()->amount);
        // Approval settles the split; it never touches the ledger.
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_approving_a_split_that_does_not_add_up_is_refused(): void
    {
        $distribution = CostDistribution::factory()->create([
            'building_id' => $this->building->id,
            'recovery_account_id' => $this->incomeAccountId(),
            'total_amount' => '10000.00',
        ]);

        $component = Livewire::actingAs($this->accountant())
            ->test(CostDistributionList::class)
            ->call('viewSplit', $distribution->id);

        $lines = $distribution->refresh()->lines->sortBy('flat_id')->values();

        $component
            ->set("lineAmounts.{$lines[0]->id}", '1')
            ->call('saveSplit')
            ->call('approve', $distribution->id)
            ->assertHasErrors('lines');

        $this->assertSame(DistributionStatus::Draft, $distribution->refresh()->status);
    }

    public function test_a_committee_member_cannot_approve_a_shared_cost(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([Role::Committee->value]);

        $distribution = CostDistribution::factory()->create([
            'building_id' => $this->building->id,
            'recovery_account_id' => $this->incomeAccountId(),
        ]);

        Livewire::actingAs($user)
            ->test(CostDistributionList::class)
            ->call('approve', $distribution->id)
            ->assertForbidden();
    }

    public function test_an_approved_shared_cost_can_be_sent_back_to_draft_before_billing(): void
    {
        $distribution = CostDistribution::factory()->create([
            'building_id' => $this->building->id,
            'recovery_account_id' => $this->incomeAccountId(),
            'total_amount' => '10000.00',
        ]);

        Livewire::actingAs($this->accountant())
            ->test(CostDistributionList::class)
            ->call('viewSplit', $distribution->id)
            ->call('approve', $distribution->id)
            ->call('revert', $distribution->id)
            ->assertHasNoErrors();

        $this->assertSame(DistributionStatus::Draft, $distribution->refresh()->status);
        $this->assertNull($distribution->approved_at);
    }
}
