<?php

namespace Tests\Feature\Reports;

use App\Enums\AccountCode;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Building;
use App\Models\Flat;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use App\Services\JournalService;
use App\Services\Reporting\ReportCsvService;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportCsvServiceTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    private User $accountant;

    private JournalService $journal;

    private ReportCsvService $csvService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->journal = app(JournalService::class);
        $this->csvService = app(ReportCsvService::class);

        $this->building = Building::factory()->flatRate('3000.00')->create(['name' => 'Rose Villa']);
        app(CurrentBuilding::class)->set($this->building->id);

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole(Role::Accountant->value);

        $this->flat = Flat::factory()->for($this->building)->create(['number' => '101']);
    }

    public function test_trial_balance_csv_export(): void
    {
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        $csv = $this->csvService->trialBalance(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        $this->assertStringContainsString('Code,"Account Name",Type,"Opening Balance",Debit,Credit,"Net Balance"', $csv);
        $this->assertStringContainsString('1030', $csv); // Service Charge Receivable
        $this->assertStringContainsString('3000.00', $csv);
    }

    public function test_owner_dues_csv_export_includes_aging_buckets(): void
    {
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        $csv = $this->csvService->ownerDues(Carbon::parse('2026-08-31'));

        $this->assertStringContainsString('Flat,Building,Owner,"Current (0-30)","31-60 Days","61-90 Days","90+ Days","Total Outstanding"', $csv);
        $this->assertStringContainsString('101', $csv);
        $this->assertStringContainsString('3000.00', $csv);
    }

    public function test_collections_csv_export(): void
    {
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));
        $payment = app(RecordPayment::class)->handle(
            $this->flat,
            '3000.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-15'),
            'Cash memo 1'
        );

        $csv = $this->csvService->collections(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        $this->assertStringContainsString('Date,"Receipt No",Flat,Building,Owner,Method,Reference,Amount', $csv);
        $this->assertStringContainsString($payment->receipt_no, $csv);
        $this->assertStringContainsString('3000.00', $csv);
    }

    public function test_report_export_controller_endpoint(): void
    {
        $this->actingAs($this->accountant);

        $response = $this->get(route('reports.export', [
            'type' => 'trial-balance',
            'from' => '2026-08-01',
            'to' => '2026-08-31',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
