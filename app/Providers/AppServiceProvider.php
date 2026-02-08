<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // <--- 1. IMPORTANTE: Agregamos esta línea para usar URL

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
        // 2. IMPORTANTE: Agregamos este bloque para forzar HTTPS en la nube
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}