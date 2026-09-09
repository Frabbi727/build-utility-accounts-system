<?php

namespace Tests\Feature\Billing;

use App\Enums\Role;
use App\Livewire\FlatList;
use App\Livewire\RecordPaymentForm;
use App\Livewire\Reports\OwnerDues;
use App\Models\Building;
use App\Models\Flat;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecordPaymentDeepLinkTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->flatRate('3000.00')->create(['due_day_of_month' => 10]);
        $this->flat = Flat::factory()->for($this->building)->create(['number' => '501']);
        app(CurrentBuilding::class)->set($this->building->id);

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole(Role::Accountant->value);
    }

    public function test_record_payment_form_preselects_flat_from_mount_argument(): void
    {
        Livewire::actingAs($this->accountant)
            ->test(RecordPaymentForm::class, ['flat_id' => $this->flat->id])
            ->assertSet('flatId', $this->flat->id);
    }

    public function test_flat_list_renders_collect_payment_link_for_money_handler(): void
    {
        Livewire::actingAs($this->accountant)
            ->test(FlatList::class)
            ->assertSee(__('billing.collect'))
            ->assertSee(route('payments.create', ['flat_id' => $this->flat->id]));
    }

    public function test_owner_dues_report_renders_collect_link(): void
    {
        app(GenerateMonthlyBills::class)->handle($this->building, now()->subMonth());

        Livewire::actingAs($this->accountant)
            ->test(OwnerDues::class)
            ->assertSee(__('billing.collect'))
            ->assertSee(route('payments.create', ['flat_id' => $this->flat->id]));
    }
}
