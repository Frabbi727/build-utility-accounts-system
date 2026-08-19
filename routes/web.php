<?php

use App\Http\Controllers\Auth\LoginController;
use App\Livewire\AccountList;
use App\Livewire\Dashboard;
use App\Livewire\ExpenseList;
use App\Livewire\FlatList;
use App\Livewire\FlatStatement;
use App\Livewire\GenerateBills;
use App\Livewire\RecordPaymentForm;
use App\Livewire\Reports\BalanceSheet;
use App\Livewire\Reports\CashBook;
use App\Livewire\Reports\CollectionReport;
use App\Livewire\Reports\ExpenseByCategory;
use App\Livewire\Reports\IncomeExpenditure;
use App\Livewire\Reports\OwnerDues;
use App\Livewire\Reports\ReportIndex;
use App\Livewire\TrialBalance;
use App\Livewire\VendorBillList;
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

    Route::get('/', Dashboard::class)->name('dashboard');

    // Owners reach their own statement from the dashboard; the policy guards the record.
    Route::get('flats/{flat}/statement', FlatStatement::class)->name('flats.statement');

    Route::middleware('role:admin|accountant|committee')->group(function (): void {
        Route::get('flats', FlatList::class)->name('flats.index');
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

    Route::middleware('role:admin|accountant')->group(function (): void {
        Route::get('billing/generate', GenerateBills::class)->name('billing.generate');
        Route::get('payments/create', RecordPaymentForm::class)->name('payments.create');
    });
});
