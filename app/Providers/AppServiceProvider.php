<?php

namespace App\Providers;

use App\Models\PumkAngsuran;
use App\Observers\PumkAngsuranObserver;
use App\Services\PublicPages\HomeShowcaseData;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        PumkAngsuran::observe(PumkAngsuranObserver::class);
        View::composer('components.home-showcase', function (\Illuminate\View\View $view): void {
            $view->with('homeShowcase', app(HomeShowcaseData::class)->build());
        });
    }
}
