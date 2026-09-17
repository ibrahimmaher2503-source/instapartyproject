<?php

declare(strict_types=1);

namespace App\Modules\Support\Providers;

use App\Modules\Support\Domain\Models\SupportTicket;
use App\Modules\Support\Domain\Policies\SupportTicketPolicy;
use App\Modules\Support\Infrastructure\Repositories\EloquentFaqRepository;
use App\Modules\Support\Infrastructure\Repositories\EloquentSupportTicketRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            EloquentFaqRepository::class
        );
        $this->app->singleton(
            EloquentSupportTicketRepository::class
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'support');
        Gate::policy(SupportTicket::class, SupportTicketPolicy::class);
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/customer.php');
    }
}
