<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Providers;

use App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource\Pages\CreateTrustBadge;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
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
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'trust-safety');
        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_END,
            fn (): string => view('trust-safety::filament.trust-badge-save-status')->render(),
            CreateTrustBadge::class,
        );
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/customer.php');
    }
}
