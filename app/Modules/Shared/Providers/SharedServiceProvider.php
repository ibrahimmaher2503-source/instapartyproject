<?php

declare(strict_types=1);

namespace App\Modules\Shared\Providers;

use App\Modules\Shared\Application\Listeners\PingFrontendRevalidate;
use App\Modules\Shared\Application\Listeners\StateTransitionObserver;
use App\Modules\Shared\Console\Commands\BuildTutorialManifestCommand;
use App\Modules\Shared\Domain\Contracts\CdnCacheBuster;
use App\Modules\Shared\Domain\Contracts\SignedUrlService;
use App\Modules\Shared\Domain\Contracts\StateTransitionLogger;
use App\Modules\Shared\Domain\Contracts\SvgSanitizer;
use App\Modules\Shared\Domain\Events\PublicThemeChanged;
use App\Modules\Shared\Infrastructure\Services\EnshrinedSvgSanitizer;
use App\Modules\Shared\Infrastructure\Services\NullCdnCacheBuster;
use App\Modules\Shared\Infrastructure\Services\SpacesCdnCacheBuster;
use App\Modules\Shared\Infrastructure\Services\SpacesSignedUrlService;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\ModelStates\Events\StateChanged;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StateTransitionLogger::class, StateTransitionObserver::class);

        // Media foundation (ADR-0047 §3). Bindings are env-conditional so MinIO/dev
        // gets a no-op CDN buster while prod hits DigitalOcean Spaces.
        $this->app->bind(SignedUrlService::class, SpacesSignedUrlService::class);
        $this->app->bind(SvgSanitizer::class, EnshrinedSvgSanitizer::class);

        $this->app->bind(CdnCacheBuster::class, function ($app): CdnCacheBuster {
            $endpointId = (string) config('services.spaces.cdn_endpoint_id', '');
            $token = (string) config('services.spaces.api_token', '');

            if ($endpointId === '' || $token === '') {
                return new NullCdnCacheBuster;
            }

            return new SpacesCdnCacheBuster(
                http: $app->make(HttpClient::class),
                endpointId: $endpointId,
                apiToken: $token,
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'shared');
        $this->registerRoutes();

        $this->commands([
            BuildTutorialManifestCommand::class,
        ]);

        Event::listen(StateChanged::class, StateTransitionObserver::class);
        Event::listen(PublicThemeChanged::class, PingFrontendRevalidate::class);
    }

    private function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/customer.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/vendor.php');
    }
}
