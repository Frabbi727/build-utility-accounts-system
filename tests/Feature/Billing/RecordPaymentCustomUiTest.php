<?php

namespace Tests\Feature\Billing;

use App\Enums\BillStatus;
use App\Enums\Role;
use App\Livewire\RecordPaymentForm;
use App\Models\Building;
use App\Models\Flat;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class RecordPaymentCustomUiTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private User $accountant;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->building = Building::factory()->flatRate('2000.00')->create();
        app(CurrentBuilding::class)->set($this->building->id);

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole(Role::Accountant->value);

        $this->flat = Flat::factory()->for($this->building)->create();
    }

    public function test_recording_payment_with_custom_allocation_mode(): void
    {
        $this->actingAs($this->accountant);

        // Generate 2 months of bills
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-06-01'));
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-07-01'));

        $bills = $this->flat->serviceChargeBills()->orderBy('billing_month')->get();

        Livewire::test(RecordPaymentForm::class)
            ->set('flatId', $this->flat->id)
            ->set('amount', '2000.00')
            ->set('method', 'cash')
            ->set('receivedOn', '2026-07-15')
            ->set('allocationMode', 'custom')
            ->set("customAllocations.{$bills[1]->id}", '2000.00')
            ->call('save')
            ->assertHasNoErrors();

        $bills[0]->refresh();
        $bills[1]->refresh();

        // Bill 0 should still be unpaid, Bill 1 should be paid
        $this->assertSame(BillStatus::Unpaid, $bills[0]->status);
        $this->assertSame(BillStatus::Paid, $bills[1]->status);
    }
}
