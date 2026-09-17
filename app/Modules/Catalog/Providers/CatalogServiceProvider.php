<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Providers;

use App\Modules\Booking\Domain\Contracts\CatalogServiceReader;
use App\Modules\Catalog\Application\Listeners\ArchiveServicesOnAccountDeletedListener;
use App\Modules\Catalog\Application\Listeners\ArchiveServicesOnTypeRevokedListener;
use App\Modules\Catalog\Application\Listeners\RevalidateFrontendCatalogListener;
use App\Modules\Catalog\Application\Listeners\WriteServiceChangeRequestAuditListener;
use App\Modules\Catalog\Application\Timeline\ChangeRequestTimelineDescriptors;
use App\Modules\Catalog\Application\Timeline\ServiceTimelineDescriptors;
use App\Modules\Catalog\Domain\Contracts\VendorServicePresenceQuery;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestApproved;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestClarificationReplied;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestClarificationRequested;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestRejected;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestSubmitted;
use App\Modules\Catalog\Domain\Events\ServicePublished;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Catalog\Infrastructure\Repositories\EloquentCatalogServiceReader;
use App\Modules\Catalog\Infrastructure\Repositories\EloquentPaymentsCatalogReader;
use App\Modules\Catalog\Infrastructure\Repositories\EloquentServiceRatingWriter;
use App\Modules\Catalog\Infrastructure\Repositories\EloquentVendorServicePresenceQuery;
use App\Modules\Communication\Application\Listeners\DispatchServiceChangeApprovedNotificationListener;
use App\Modules\Communication\Application\Listeners\DispatchServiceChangeClarificationNotificationListener;
use App\Modules\Communication\Application\Listeners\DispatchServiceChangeRejectedNotificationListener;
use App\Modules\Identity\Domain\Events\VendorAccountDeleted;
use App\Modules\Identity\Domain\Events\VendorTypeRevoked;
use App\Modules\Payments\Domain\Contracts\PaymentsCatalogReader;
use App\Modules\Reviews\Domain\Contracts\ServiceRatingWriter;
use App\Modules\Shared\Application\Timeline\TimelineSourceRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CatalogServiceReader::class, EloquentCatalogServiceReader::class);
        $this->app->bind(PaymentsCatalogReader::class, EloquentPaymentsCatalogReader::class);
        $this->app->singleton(ServiceRatingWriter::class, EloquentServiceRatingWriter::class);
        $this->app->bind(VendorServicePresenceQuery::class, EloquentVendorServicePresenceQuery::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'catalog');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'catalog');
        $this->loadRoutesFrom(__DIR__.'/../Routes/customer.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/vendor.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');

        Event::listen(VendorTypeRevoked::class, ArchiveServicesOnTypeRevokedListener::class);
        Event::listen(VendorAccountDeleted::class, ArchiveServicesOnAccountDeletedListener::class);

        // Trigger ISR revalidation on the customer frontend when a service is published
        Event::listen(ServicePublished::class, RevalidateFrontendCatalogListener::class);

        // Service change request audit trail
        Event::listen(
            ServiceChangeRequestSubmitted::class,
            [WriteServiceChangeRequestAuditListener::class, 'handleSubmitted'],
        );
        Event::listen(
            ServiceChangeRequestApproved::class,
            [WriteServiceChangeRequestAuditListener::class, 'handleApproved'],
        );
        Event::listen(
            ServiceChangeRequestRejected::class,
            [WriteServiceChangeRequestAuditListener::class, 'handleRejected'],
        );
        Event::listen(
            ServiceChangeRequestClarificationRequested::class,
            [WriteServiceChangeRequestAuditListener::class, 'handleClarificationRequested'],
        );
        Event::listen(
            ServiceChangeRequestClarificationReplied::class,
            [WriteServiceChangeRequestAuditListener::class, 'handleClarificationReplied'],
        );

        // Service change request — vendor notifications
        Event::listen(
            ServiceChangeRequestApproved::class,
            DispatchServiceChangeApprovedNotificationListener::class,
        );
        Event::listen(
            ServiceChangeRequestRejected::class,
            DispatchServiceChangeRejectedNotificationListener::class,
        );
        Event::listen(
            ServiceChangeRequestClarificationRequested::class,
            DispatchServiceChangeClarificationNotificationListener::class,
        );

        $this->callAfterResolving(TimelineSourceRegistry::class, function (TimelineSourceRegistry $registry): void {
            $registry->register(Service::class, ...ServiceTimelineDescriptors::all());
            $registry->register(ServiceChangeRequest::class, ...ChangeRequestTimelineDescriptors::all());
        });
    }
}
