<?php

declare(strict_types=1);

namespace App\Modules\Identity\Providers;

use App\Modules\Booking\Domain\Contracts\CoverageAreaResolver;
use App\Modules\Identity\Application\Listeners\RevokeAllVendorTypesOnStatusChange;
use App\Modules\Identity\Application\Listeners\SendDocExpiredNotificationListener;
use App\Modules\Identity\Application\Listeners\SendPasswordResetNotification;
use App\Modules\Identity\Application\Listeners\SendVendorRegistrationEmailVerification;
use App\Modules\Identity\Application\Listeners\SendVendorRegistrationVerification;
use App\Modules\Identity\Application\Listeners\UpdateLastLoginAtListener;
use App\Modules\Identity\Application\Timeline\VendorProfileTimelineDescriptors;
use App\Modules\Identity\Console\Commands\CheckDocumentExpiryCommand;
use App\Modules\Identity\Domain\Contracts\DeviceTokenRepository;
use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;
use App\Modules\Identity\Domain\Events\PasswordResetRequested;
use App\Modules\Identity\Domain\Events\VendorAutoSuspended;
use App\Modules\Identity\Domain\Events\VendorDocumentExpired;
use App\Modules\Identity\Domain\Events\VendorRegistered;
use App\Modules\Identity\Domain\Events\VendorRejected;
use App\Modules\Identity\Domain\Events\VendorSuspended;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Http\Middleware\EnsureAccountIsActive;
use App\Modules\Identity\Http\Middleware\EnsureVendorApproved;
use App\Modules\Identity\Http\Middleware\EnsureVendorNotSuspended;
use App\Modules\Identity\Http\Responses\PanelLoginResponse;
use App\Modules\Identity\Http\Responses\VendorRegistrationResponse;
use App\Modules\Identity\Infrastructure\Gateways\StubOtpGateway;
use App\Modules\Identity\Infrastructure\Loyalty\EloquentVendorLookup;
use App\Modules\Identity\Infrastructure\Repositories\EloquentCoverageAreaResolver;
use App\Modules\Identity\Infrastructure\Repositories\EloquentDeviceTokenRepository;
use App\Modules\Identity\Infrastructure\Repositories\EloquentVendorRatingWriter;
use App\Modules\Loyalty\Domain\Contracts\VendorLookup;
use App\Modules\Reviews\Domain\Contracts\VendorRatingWriter;
use App\Modules\Shared\Application\Timeline\TimelineSourceRegistry;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Http\Responses\Auth\Contracts\RegistrationResponse;
use Illuminate\Auth\Events\Login;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LoginResponse::class, PanelLoginResponse::class);
        $this->app->bind(RegistrationResponse::class, VendorRegistrationResponse::class);
        $this->app->bind(OtpGatewayInterface::class, StubOtpGateway::class);
        $this->app->singleton(DeviceTokenRepository::class, EloquentDeviceTokenRepository::class);
        $this->app->singleton(VendorRatingWriter::class, EloquentVendorRatingWriter::class);
        $this->app->singleton(VendorLookup::class, EloquentVendorLookup::class);
        $this->app->bind(CoverageAreaResolver::class, EloquentCoverageAreaResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'identity');

        $this->commands([
            CheckDocumentExpiryCommand::class,
        ]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('identity:check-document-expiry')->dailyAt('02:00');
        });

        $this->registerEventListeners();
        $this->registerRoutes();

        $this->callAfterResolving(TimelineSourceRegistry::class, function (TimelineSourceRegistry $registry): void {
            $registry->register(VendorProfile::class, ...VendorProfileTimelineDescriptors::all());
        });

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('ensure.account.active', EnsureAccountIsActive::class);
        $router->aliasMiddleware('vendor.not_suspended', EnsureVendorNotSuspended::class);
        $router->aliasMiddleware('vendor.approved', EnsureVendorApproved::class);
    }

    private function registerEventListeners(): void
    {
        Event::listen(Login::class, UpdateLastLoginAtListener::class);
        Event::listen(VendorRegistered::class, SendVendorRegistrationEmailVerification::class);
        Event::listen(VendorRegistered::class, SendVendorRegistrationVerification::class);
        Event::listen(VendorSuspended::class, RevokeAllVendorTypesOnStatusChange::class);
        Event::listen(VendorRejected::class, RevokeAllVendorTypesOnStatusChange::class);
        Event::listen(VendorAutoSuspended::class, RevokeAllVendorTypesOnStatusChange::class);
        Event::listen(VendorAutoSuspended::class, SendDocExpiredNotificationListener::class);
        Event::listen(VendorDocumentExpired::class, SendDocExpiredNotificationListener::class);
        Event::listen(PasswordResetRequested::class, SendPasswordResetNotification::class);
    }

    private function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/customer.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/vendor.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');
    }
}
