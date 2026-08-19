<?php

namespace App\Providers;

use App\Services\Billing\ApplyAdvances;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\LineSources\AdHocChargeLines;
use App\Services\Billing\LineSources\ChargeHeadLines;
use App\Services\Billing\LineSources\CostDistributionLines;
use App\Services\Billing\LineSources\MeterReadingLines;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One instance per request so the resolved building is cached across screens.
        $this->app->scoped(CurrentBuilding::class);

        // The order is the order lines appear on a printed bill, so it is declared
        // explicitly here rather than left to container tag resolution, whose ordering
        // is unspecified. Recurring charges first, then what varied this month, then
        // one-offs last.
        $this->app->bind(GenerateMonthlyBills::class, fn ($app): GenerateMonthlyBills => new GenerateMonthlyBills(
            $app->make(JournalService::class),
            $app->make(ApplyAdvances::class),
            [
                $app->make(ChargeHeadLines::class),
                $app->make(MeterReadingLines::class),
                $app->make(CostDistributionLines::class),
                $app->make(AdHocChargeLines::class),
            ],
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
