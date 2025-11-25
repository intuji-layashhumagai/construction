<?php

namespace App\Providers;

use App\Services\Sync\SyncProtocol;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {

        $this->app->singleton(SyncProtocol::class, function () {
            return new SyncProtocol;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
