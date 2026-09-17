<?php

declare(strict_types=1);

namespace App\Modules\Shared\Providers;

use App\Modules\Shared\Application\Timeline\TimelineSourceRegistry;
use Illuminate\Support\ServiceProvider;

class TimelineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TimelineSourceRegistry::class, fn () => new TimelineSourceRegistry);
    }

    public function boot(): void
    {
        //
    }
}
