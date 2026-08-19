<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReceiptTest extends TestCase
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
        $this->flat = Flat::factory()->for($this->building)->create(['number' => 'A-4']);

        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-06-01'));
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role->value]);

        return $user;
    }

    private function pay(string $amount = '3000.00'): Payment
    {
        return app(RecordPayment::class)->handle(
            $this->flat,
            $amount,
            PaymentMethod::Bkash,
            Carbon::parse('2026-06-12'),
            'TRX-9911',
        );
    }

    public function test_it_shows_the_receipt_details_and_what_the_payment_settled(): void
    {
        $payment = $this->pay();

        $this->actingAs($this->userWithRole(Role::Accountant))
            ->get(route('payments.receipt', $payment))
            ->assertSuccessful()
            ->assertSee($payment->receipt_no)
            ->assertSee('A-4')
            ->assertSee('TRX-9911')
            ->assertSee($this->building->name)
            ->assertSee('3,000.00');
    }

    public function test_an_unallocated_payment_reads_as_an_advance(): void
    {
        // Nothing outstanding yet, so the whole payment is held as credit.
        $payment = app(RecordPayment::class)->handle(
            Flat::factory()->for($this->building)->create(),
            '1000.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-06-12'),
        );

        $this->actingAs($this->userWithRole(Role::Accountant))
            ->get(route('payments.receipt', $payment))
            ->assertSuccessful()
            ->assertSee(__('billing.held_as_advance'));
    }

    public function test_an_owner_may_print_their_own_receipt(): void
    {
        $user = $this->userWithRole(Role::Owner);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $this->flat->update(['owner_id' => $owner->id]);

        $this->actingAs($user)
            ->get(route('payments.receipt', $this->pay()))
            ->assertSuccessful();
    }

    public function test_an_owner_may_not_print_another_flats_receipt(): void
    {
        $payment = $this->pay();

        $user = $this->userWithRole(Role::Owner);
        Owner::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('payments.receipt', $payment))
            ->assertForbidden();
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get(route('payments.receipt', $this->pay()))->assertRedirect(route('login'));
    }

    public function test_it_renders_in_bangla(): void
    {
        $payment = $this->pay();

        $this->actingAs($this->userWithRole(Role::Accountant))
            ->withSession(['locale' => 'bn'])
            ->get(route('payments.receipt', $payment))
            ->assertSuccessful()
            ->assertSee(__('billing.receipt', [], 'bn'))
            ->assertSee(__('billing.amount_received', [], 'bn'));
    }
}
