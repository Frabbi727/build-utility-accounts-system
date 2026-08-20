<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Livewire\PaymentList;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentListTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    private Flat $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->flatRate('3000.00')->create(['due_day_of_month' => 10]);
        $this->flat = Flat::factory()->for($this->building)->create(['number' => 'A-4']);
        $this->other = Flat::factory()->for($this->building)->create(['number' => 'B-2']);

        app(CurrentBuilding::class)->set($this->building->id);
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-06-01'));
    }

    private function accountant(): User
    {
        $user = User::factory()->create();
        $user->syncRoles([Role::Accountant->value]);

        return $user;
    }

    private function pay(Flat $flat, string $amount, string $on, PaymentMethod $method = PaymentMethod::Cash): void
    {
        app(RecordPayment::class)->handle($flat, $amount, $method, Carbon::parse($on), 'TRX-'.$amount);
    }

    public function test_it_lists_payments_with_what_they_settled(): void
    {
        $this->pay($this->flat, '1200.00', '2026-06-12');

        Livewire::actingAs($this->accountant())
            ->test(PaymentList::class)
            ->assertSee('A-4')
            ->assertSee('1,200.00')
            ->assertSee('TRX-1200.00')
            ->assertSee('BILL-');
    }

    public function test_an_unallocated_payment_reads_as_an_advance(): void
    {
        $spare = Flat::factory()->for($this->building)->create(['number' => 'C-9']);
        $this->pay($spare, '500.00', '2026-06-12');

        Livewire::actingAs($this->accountant())
            ->test(PaymentList::class)
            ->assertSee(__('billing.held_as_advance'));
    }

    public function test_it_filters_by_flat(): void
    {
        $this->pay($this->flat, '1200.00', '2026-06-12');
        $this->pay($this->other, '900.00', '2026-06-13');

        Livewire::actingAs($this->accountant())
            ->test(PaymentList::class)
            ->set('flatId', $this->flat->id)
            ->assertSee('1,200.00')
            ->assertDontSee('900.00');
    }

    public function test_it_filters_by_date_range_and_method(): void
    {
        $this->pay($this->flat, '1200.00', '2026-06-12');
        $this->pay($this->other, '900.00', '2026-07-13', PaymentMethod::Bkash);

        Livewire::actingAs($this->accountant())
            ->test(PaymentList::class)
            ->set('from', '2026-07-01')
            ->set('to', '2026-07-31')
            ->assertSee('900.00')
            ->assertDontSee('1,200.00')
            ->set('from', '2026-06-01')
            ->set('to', '2026-07-31')
            ->set('method', PaymentMethod::Bkash->value)
            ->assertSee('900.00')
            ->assertDontSee('1,200.00');
    }

    public function test_it_searches_receipt_no_and_reference(): void
    {
        $this->pay($this->flat, '1200.00', '2026-06-12');
        $this->pay($this->other, '900.00', '2026-06-13');

        Livewire::actingAs($this->accountant())
            ->test(PaymentList::class)
            ->set('search', 'TRX-900')
            ->assertSee('900.00')
            ->assertDontSee('1,200.00');
    }

    public function test_an_owner_may_not_open_it(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([Role::Owner->value]);
        Owner::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('payments.index'))->assertForbidden();
    }
}
