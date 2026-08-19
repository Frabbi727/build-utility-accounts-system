<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Livewire\FlatStatement;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The per-flat statement is the owner-facing view of the receivable sub-ledger.
 * Its running balance must reconcile with the ledger, not with the bill table.
 */
class FlatStatementTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->flatRate('3000.00')->create(['due_day_of_month' => 10]);
        $this->flat = Flat::factory()->for($this->building)->create();
    }

    private function actingAsAccountant(): void
    {
        $user = User::factory()->create();
        $user->assignRole('accountant');

        $this->actingAs($user);
    }

    public function test_it_lists_each_accrual_with_a_running_balance(): void
    {
        $this->actingAsAccountant();

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-06-01'));
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-07-01'));

        $rows = Livewire::test(FlatStatement::class, ['flat' => $this->flat])->viewData('rows');

        $this->assertCount(2, $rows);
        $this->assertSame('3000.00', $rows[0]['debit']);
        $this->assertSame('3000.00', $rows[0]['balance']);
        $this->assertSame('6000.00', $rows[1]['balance']);
    }

    public function test_a_payment_appears_as_a_credit_and_reduces_the_balance(): void
    {
        $this->actingAsAccountant();

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-06-01'));
        app(RecordPayment::class)->handle($this->flat, '1000.00', PaymentMethod::Cash, Carbon::parse('2026-06-15'));

        $component = Livewire::test(FlatStatement::class, ['flat' => $this->flat]);
        $rows = $component->viewData('rows');

        $this->assertSame('1000.00', $rows[1]['credit']);
        $this->assertSame('2000.00', $rows[1]['balance']);
        $component->assertViewHas('outstanding', '2000.00');
    }

    public function test_an_overpayment_is_reported_as_an_advance(): void
    {
        $this->actingAsAccountant();

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-06-01'));
        app(RecordPayment::class)->handle($this->flat, '5000.00', PaymentMethod::Cash, Carbon::parse('2026-06-15'));

        Livewire::test(FlatStatement::class, ['flat' => $this->flat])
            ->assertViewHas('outstanding', '0.00')
            ->assertViewHas('advance', '2000.00');
    }

    public function test_an_owner_may_open_their_own_statement(): void
    {
        $user = User::factory()->create();
        $user->assignRole('owner');

        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $this->flat->update(['owner_id' => $owner->id]);

        $this->actingAs($user)
            ->get(route('flats.statement', $this->flat))
            ->assertSuccessful();
    }

    public function test_an_owner_may_not_open_another_flats_statement(): void
    {
        $user = User::factory()->create();
        $user->assignRole('owner');

        Owner::factory()->create(['user_id' => $user->id]);

        $other = Flat::factory()->for($this->building)->create();

        $this->actingAs($user)
            ->get(route('flats.statement', $other))
            ->assertForbidden();
    }
}
