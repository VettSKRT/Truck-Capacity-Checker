<?php

namespace App\Providers;

use App\Models\Area;
use App\Models\Dealer;
use App\Services\LoadCalculator;
use App\Services\RegionResolver;
use App\Services\RouteResolver;
use Illuminate\Database\QueryException;
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

        // Pengaturan area dan dealer Same Day dibaca dari database (menu Pengaturan).
        // Dipakai bind (bukan singleton) agar perubahan pengaturan langsung berlaku.
        // Jika tabel belum dimigrasi, dipakai nilai awal dari config/logistics.php.
        $this->app->bind(RouteResolver::class, function () {
            try {
                $routes = Area::whereNotNull('route')->pluck('route', 'code')->all();
                $sameDay = Dealer::where('same_day', true)->pluck('code')->all();
            } catch (QueryException) {
                $routes = (array) config('logistics.area_routes', []);
                $sameDay = (array) config('logistics.same_day_dealers', []);
            }

            return new RouteResolver($routes, $sameDay, (int) config('logistics.same_day_route', 8));
        });

        $this->app->bind(RegionResolver::class, function ($app) {
            try {
                $regions = Area::whereNotNull('region')->pluck('region', 'code')->all();
            } catch (QueryException) {
                $regions = (array) config('logistics.area_regions', []);
            }

            return new RegionResolver(
                $app->make(RouteResolver::class),
                $regions,
                (array) config('logistics.region_schedule', []),
            );
        });
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
