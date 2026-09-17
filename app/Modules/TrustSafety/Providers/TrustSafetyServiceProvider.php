<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Providers;

use Illuminate\Support\ServiceProvider;

class TrustSafetyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'trust-safety');
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/customer.php');
    }
}
