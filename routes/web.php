<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ReceiptController;
use App\Livewire\Accounting\OpeningBalances;
use App\Livewire\Accounting\PeriodList;
use App\Livewire\AccountList;
use App\Livewire\Admin\UserList;
use App\Livewire\Billing\CostDistributionList;
use App\Livewire\Dashboard;
use App\Livewire\ExpenseList;
use App\Livewire\FlatList;
use App\Livewire\FlatStatement;
use App\Livewire\GenerateBills;
use App\Livewire\Masters\AdHocChargeList;
use App\Livewire\Masters\BuildingList;
use App\Livewire\Masters\ChargeHeadList;
use App\Livewire\Masters\FlatChargeOverrides;
use App\Livewire\Masters\FloorList;
use App\Livewire\Masters\OwnerList;
use App\Livewire\Masters\StaffList;
use App\Livewire\Masters\TenantList;
use App\Livewire\Masters\UnitTypeList;
use App\Livewire\Masters\VendorList;
use App\Livewire\RecordPaymentForm;
use App\Livewire\Reports\BalanceSheet;
use App\Livewire\Reports\CashBook;
use App\Livewire\Reports\CollectionReport;
use App\Livewire\Reports\ExpenseByCategory;
use App\Livewire\Reports\IncomeExpenditure;
use App\Livewire\Reports\OwnerDues;
use App\Livewire\Reports\ReportIndex;
use App\Livewire\Setup\Wizard;
use App\Livewire\TrialBalance;
use App\Livewire\Utilities\MeterList;
use App\Livewire\Utilities\ReadingSheet;
use App\Livewire\Utilities\UtilityList;
use App\Livewire\Utilities\UtilityTariffList;
use App\Livewire\VendorBillList;
use App\Models\Building;
use App\Support\CurrentBuilding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::post('locale', function (): RedirectResponse {
    $locale = request()->string('locale')->toString();

    if (in_array($locale, ['bn', 'en'], true)) {
        session()->put('locale', $locale);
    }

    return back();
})->name('locale.switch');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // A fresh install has no building yet, so an admin lands in the wizard.
    Route::get('setup', Wizard::class)->name('setup');

    Route::get('/', Dashboard::class)->middleware('setup')->name('dashboard');

    Route::post('buildings/switch', function (CurrentBuilding $current): RedirectResponse {
        $buildingId = request()->integer('building_id');

        if (Building::whereKey($buildingId)->exists()) {
            $current->set($buildingId);
        }

        return back();
    })->name('buildings.switch');

    // Owners reach their own statement from the dashboard; the policy guards the record.
    Route::get('flats/{flat}/statement', FlatStatement::class)->name('flats.statement');

    // Likewise the receipt: PaymentPolicy lets an owner print their own and no one else's.
    Route::get('payments/{payment}/receipt', ReceiptController::class)->name('payments.receipt');

    Route::middleware('role:admin|accountant|committee')->group(function (): void {
        Route::get('flats', FlatList::class)->name('flats.index');
        Route::get('buildings', BuildingList::class)->name('buildings.index');
        Route::get('floors', FloorList::class)->name('floors.index');
        Route::get('charge-heads', ChargeHeadList::class)->name('charge-heads.index');
        Route::get('unit-types', UnitTypeList::class)->name('unit-types.index');
        Route::get('ad-hoc-charges', AdHocChargeList::class)->name('ad-hoc-charges.index');
        Route::get('shared-costs', CostDistributionList::class)->name('shared-costs.index');
        Route::get('utilities', UtilityList::class)->name('utilities.index');
        Route::get('utilities/meters', MeterList::class)->name('meters.index');
        Route::get('utilities/tariffs', UtilityTariffList::class)->name('tariffs.index');
        Route::get('utilities/readings', ReadingSheet::class)->name('readings.index');
        Route::get('flats/{flat}/charges', FlatChargeOverrides::class)->name('flats.charges');
        Route::get('owners', OwnerList::class)->name('owners.index');
        Route::get('tenants', TenantList::class)->name('tenants.index');
        Route::get('vendors', VendorList::class)->name('vendors.index');
        Route::get('staff', StaffList::class)->name('staff.index');
        Route::get('expenses', ExpenseList::class)->name('expenses.index');
        Route::get('vendor-bills', VendorBillList::class)->name('vendor-bills.index');
        Route::get('accounts', AccountList::class)->name('accounts.index');
        // Reports are reads over the ledger, so committee members reach them too.
        Route::get('reports', ReportIndex::class)->name('reports.index');
        Route::get('reports/owner-dues', OwnerDues::class)->name('reports.owner-dues');
        Route::get('reports/collections', CollectionReport::class)->name('reports.collections');
        Route::get('reports/expense-by-category', ExpenseByCategory::class)->name('reports.expenses');
        Route::get('reports/cash-book', CashBook::class)->name('reports.cash-book');
        Route::get('reports/income-expenditure', IncomeExpenditure::class)->name('reports.income-expenditure');
        Route::get('reports/balance-sheet', BalanceSheet::class)->name('reports.balance-sheet');
        Route::get('reports/trial-balance', TrialBalance::class)->name('reports.trial-balance');
    });

    Route::middleware('role:admin')->group(function (): void {
        Route::get('accounting/opening-balances', OpeningBalances::class)->name('accounting.opening-balances');
        Route::get('accounting/periods', PeriodList::class)->name('accounting.periods');
        Route::get('users', UserList::class)->name('users.index');
    });

    Route::middleware('role:admin|accountant')->group(function (): void {
        Route::get('billing/generate', GenerateBills::class)->name('billing.generate');
        Route::get('payments/create', RecordPaymentForm::class)->name('payments.create');
    });
});
