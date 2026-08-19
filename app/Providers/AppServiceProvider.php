<?php

namespace App\Providers;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
