<?php

namespace Tests\Feature\Masters;

use App\Enums\AccountCode;
use App\Enums\Role;
use App\Livewire\Masters\AdHocChargeList;
use App\Models\AdHocCharge;
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

class AdHocChargeScreenTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->flatRate('3000.00')->create();
        $this->flat = Flat::factory()->for($this->building)->create();

        app(CurrentBuilding::class)->set($this->building->id);
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role->value]);

        return $user;
    }

    private function incomeAccountId(): int
    {
        return app(JournalService::class)->account(AccountCode::OtherIncome)->id;
    }

    public function test_an_accountant_can_record_a_one_off_charge(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(AdHocChargeList::class)
            ->call('create')
            ->set('flatId', $this->flat->id)
            ->set('accountId', $this->incomeAccountId())
            ->set('description', 'Balcony repair')
            ->set('amount', '750.00')
            ->set('effectiveMonth', '2026-06')
            ->call('save')
            ->assertHasNoErrors();

        $charge = AdHocCharge::sole();

        $this->assertSame('750.00', (string) $charge->amount);
        $this->assertSame('2026-06-01', $charge->effective_month->toDateString());
        $this->assertNull($charge->applied_to_bill_id);
    }

    public function test_a_zero_or_negative_amount_is_rejected(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(AdHocChargeList::class)
            ->call('create')
            ->set('flatId', $this->flat->id)
            ->set('accountId', $this->incomeAccountId())
            ->set('description', 'Nothing')
            ->set('amount', '0')
            ->set('effectiveMonth', '2026-06')
            ->call('save')
            ->assertHasErrors('amount');

        $this->assertSame(0, AdHocCharge::count());
    }

    public function test_a_flat_from_another_building_is_rejected(): void
    {
        $otherFlat = Flat::factory()->for(Building::factory()->create())->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(AdHocChargeList::class)
            ->call('create')
            ->set('flatId', $otherFlat->id)
            ->set('accountId', $this->incomeAccountId())
            ->set('description', 'Wrong building')
            ->set('amount', '100.00')
            ->set('effectiveMonth', '2026-06')
            ->call('save')
            ->assertHasErrors('flatId');
    }

    public function test_a_billed_charge_can_no_longer_be_edited_or_deleted(): void
    {
        $charge = AdHocCharge::factory()->create([
            'flat_id' => $this->flat->id,
            'account_id' => $this->incomeAccountId(),
            'amount' => '750.00',
            'effective_month' => Carbon::parse('2026-06-01'),
        ]);

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-06-01'));

        $this->assertTrue($charge->fresh()->isApplied());

        $accountant = $this->userWithRole(Role::Accountant);

        $this->assertFalse($accountant->can('update', $charge->fresh()));
        $this->assertFalse($accountant->can('delete', $charge->fresh()));
    }

    public function test_a_committee_member_may_look_but_not_add(): void
    {
        $committee = $this->userWithRole(Role::Committee);

        Livewire::actingAs($committee)
            ->test(AdHocChargeList::class)
            ->assertSuccessful();

        $this->assertFalse($committee->can('create', AdHocCharge::class));
    }

    public function test_an_owner_may_not_reach_the_screen(): void
    {
        $this->actingAs($this->userWithRole(Role::Owner))
            ->get(route('ad-hoc-charges.index'))
            ->assertForbidden();
    }
}
