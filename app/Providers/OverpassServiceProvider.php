<?php

namespace App\Providers;

use App\Services\Overpass;
use Illuminate\Support\ServiceProvider;

class OverpassServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('overpass', function ($app) {
            return new Overpass;
        });

        $this->mergeConfigFrom(
            base_path('config/overpass.php'),
            'overpass'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                base_path('config/overpass.php') => config_path('overpass.php'),
            ], 'overpass-config');
        }
    }
}
