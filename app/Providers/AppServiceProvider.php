<?php

namespace App\Providers;

use App\Services\LoadCalculator;
use App\Services\RegionResolver;
use App\Services\RouteResolver;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoadCalculator::class, fn () => new LoadCalculator(
            (float) config('logistics.ready_ratio', 0.8)
        ));

        $this->app->singleton(RegionResolver::class, fn ($app) => new RegionResolver(
            $app->make(RouteResolver::class),
            (array) config('logistics.area_regions', []),
            (array) config('logistics.region_schedule', []),
        ));

        $this->app->singleton(RouteResolver::class, fn () => new RouteResolver(
            (array) config('logistics.area_routes', []),
            (array) config('logistics.same_day_dealers', []),
            (int) config('logistics.same_day_route', 8),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Tanggal dan nama hari/bulan dalam bahasa Indonesia
        Carbon::setLocale('id');
    }
}
