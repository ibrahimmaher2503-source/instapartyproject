<?php

declare(strict_types=1);

namespace App\Modules\Communication\Providers;

use App\Modules\Booking\Domain\Contracts\BookingHistoryReader;
use App\Modules\Booking\Infrastructure\Repositories\EloquentBookingHistoryReader;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\Listeners\CreateChatThreadOnBookingConfirmed;
use App\Modules\Communication\Application\Listeners\DispatchChatFlagEscalatedNotificationsListener;
use App\Modules\Communication\Application\Listeners\DispatchChatThreadFrozenNotificationsListener;
use App\Modules\Communication\Application\Listeners\DispatchChatThreadUnfrozenNotificationsListener;
use App\Modules\Communication\Application\Listeners\DispatchServiceChangesRequestedNotificationListener;
use App\Modules\Communication\Application\Listeners\DispatchServiceResubmittedNotificationListener;
use App\Modules\Communication\Application\Listeners\DispatchVendorChangesRequestedNotificationListener;
use App\Modules\Communication\Application\Listeners\DispatchVendorResubmittedNotificationListener;
use App\Modules\Communication\Application\Listeners\OnAlternativeVendorProposed;
use App\Modules\Communication\Application\Listeners\OnBookingCancelled;
use App\Modules\Communication\Application\Listeners\OnBookingConfirmed;
use App\Modules\Communication\Application\Listeners\OnBookingForceCancelledNotifyListener;
use App\Modules\Communication\Application\Listeners\OnBookingModified;
use App\Modules\Communication\Application\Listeners\OnBookingStalledInbox;
use App\Modules\Communication\Application\Listeners\OnBookingSubmitted;
use App\Modules\Communication\Application\Listeners\OnChargebackOpenedInbox;
use App\Modules\Communication\Application\Listeners\OnChatFlaggedInbox;
use App\Modules\Communication\Application\Listeners\OnCustomerSuspended;
use App\Modules\Communication\Application\Listeners\OnCustomerWelcome;
use App\Modules\Communication\Application\Listeners\OnPaymentCaptured;
use App\Modules\Communication\Application\Listeners\OnPaymentFailedCustomer;
use App\Modules\Communication\Application\Listeners\OnPaymentFailedInbox;
use App\Modules\Communication\Application\Listeners\OnRefundCompleted;
use App\Modules\Communication\Application\Listeners\OnRefundFailed;
use App\Modules\Communication\Application\Listeners\OnReportSubmittedCustomerAck;
use App\Modules\Communication\Application\Listeners\OnReportSubmittedInbox;
use App\Modules\Communication\Application\Listeners\OnReviewSubmitted;
use App\Modules\Communication\Application\Listeners\OnServicePublished;
use App\Modules\Communication\Application\Listeners\OnServiceRejected;
use App\Modules\Communication\Application\Listeners\OnServiceSubmittedForReviewInbox;
use App\Modules\Communication\Application\Listeners\OnVendorApprovedForType;
use App\Modules\Communication\Application\Listeners\OnVendorDocumentApproved;
use App\Modules\Communication\Application\Listeners\OnVendorDocumentRejected;
use App\Modules\Communication\Application\Listeners\OnVendorDocumentUploadedInbox;
use App\Modules\Communication\Application\Listeners\OnVendorModificationProposed;
use App\Modules\Communication\Application\Listeners\OnVendorProfileApproved;
use App\Modules\Communication\Application\Listeners\OnVendorProfileAutoSuspended;
use App\Modules\Communication\Application\Listeners\OnVendorProfileAutoSuspendedInbox;
use App\Modules\Communication\Application\Listeners\OnVendorProfileRejected;
use App\Modules\Communication\Application\Listeners\OnVendorProfileSuspended;
use App\Modules\Communication\Application\Listeners\OnVendorRegisteredInbox;
use App\Modules\Communication\Application\Listeners\OnVendorRejected;
use App\Modules\Communication\Application\Listeners\OnVendorResponseTimedOut;
use App\Modules\Communication\Application\Listeners\OnVendorTypeRevoked;
use App\Modules\Communication\Application\Listeners\OnVendorUnsuspended;
use App\Modules\Communication\Application\Listeners\OnWithdrawalApproved;
use App\Modules\Communication\Application\Listeners\OnWithdrawalPaid;
use App\Modules\Communication\Application\Listeners\OnWithdrawalRejected;
use App\Modules\Communication\Application\Listeners\OnWithdrawalRequestedInbox;
use App\Modules\Communication\Application\Listeners\PushChatFreezeToFirestoreListener;
use App\Modules\Communication\Application\Listeners\WriteChatModerationAuditListener;
use App\Modules\Communication\Application\Services\SegmentResolver;
use App\Modules\Communication\Application\Timeline\ChatThreadTimelineDescriptors;
use App\Modules\Communication\Console\CheckVendorApprovalSlaCommand;
use App\Modules\Communication\Console\DispatchDueCampaignsCommand;
use App\Modules\Communication\Console\RetryFailedDispatchesCommand;
use App\Modules\Communication\Console\SendEventReminderCommand;
use App\Modules\Communication\Console\SendVendorDeadlineReminderCommand;
use App\Modules\Communication\Console\WakeupSnoozedInboxItemsCommand;
use App\Modules\Communication\Domain\Contracts\AdminInboxWriter;
use App\Modules\Communication\Domain\Contracts\ChatTokenMinter;
use App\Modules\Communication\Domain\Contracts\FirestoreChatGateway;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Communication\Domain\Contracts\SmsProviderInterface;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Events\ChatFlagEscalatedToInbox;
use App\Modules\Communication\Domain\Events\ChatMessageFlagged;
use App\Modules\Communication\Domain\Events\ChatModerationFlagResolved;
use App\Modules\Communication\Domain\Events\ChatThreadFrozen;
use App\Modules\Communication\Domain\Events\ChatThreadUnfrozen;
use App\Modules\Communication\Domain\Events\OffPlatformContactMarked;
use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Filament\Vendor\Components\RestrictedChatPanel;
use App\Modules\Communication\Http\Middleware\VerifyCloudFunctionSecret;
use App\Modules\Communication\Infrastructure\Gateways\ChatTokenMinterStub;
use App\Modules\Communication\Infrastructure\Gateways\DatabaseNotificationAdapter;
use App\Modules\Communication\Infrastructure\Gateways\FcmPushAdapter;
use App\Modules\Communication\Infrastructure\Gateways\FirebaseChatTokenMinter;
use App\Modules\Communication\Infrastructure\Gateways\FirebaseFirestoreChatGateway;
use App\Modules\Communication\Infrastructure\Gateways\FirestoreChatGatewayStub;
use App\Modules\Communication\Infrastructure\Gateways\MailchimpEmailAdapter;
use App\Modules\Communication\Infrastructure\Gateways\NullProviderAdapter;
use App\Modules\Communication\Infrastructure\Gateways\SmsMisrAdapter;
use App\Modules\Communication\Infrastructure\Gateways\SmsMisrProvider;
use App\Modules\Communication\Infrastructure\Gateways\WhatsAppStubAdapter;
use App\Modules\Communication\Infrastructure\Repositories\EloquentAdminInboxWriter;
use App\Modules\Identity\Domain\Events\VendorProfileResubmitted;
use App\Modules\Shared\Application\Timeline\TimelineSourceRegistry;
use App\Modules\Shared\Domain\Events\ChangeRequestCreated;
use App\Modules\Shared\Domain\Events\ChangeRequestResubmitted;
use GuzzleHttp\Client;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class CommunicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AdminInboxWriter::class, EloquentAdminInboxWriter::class);
        $this->bindFirestoreChatGateway();
        $this->bindChatTokenMinter();
        $this->app->bind(NotificationDispatcher::class, DispatchNotificationAction::class);
        $this->app->singleton(BookingHistoryReader::class, EloquentBookingHistoryReader::class);
        $this->app->singleton(SegmentResolver::class);

        // in_app is read directly from notification_dispatches — no external delivery.
        $this->app->bind(NotificationChannel::InApp->value.'_adapter', DatabaseNotificationAdapter::class);
        $this->app->bind(SmsProviderInterface::class, SmsMisrProvider::class);

        if ($this->app->environment(['local', 'testing'])) {
            foreach ([NotificationChannel::Push, NotificationChannel::Sms, NotificationChannel::Whatsapp, NotificationChannel::Email] as $channel) {
                $this->app->bind($channel->value.'_adapter', NullProviderAdapter::class);
            }
        } else {
            $this->app->bind(NotificationChannel::Push->value.'_adapter', FcmPushAdapter::class);
            $this->app->bind(NotificationChannel::Sms->value.'_adapter', SmsMisrAdapter::class);
            $this->app->bind(NotificationChannel::Whatsapp->value.'_adapter', WhatsAppStubAdapter::class);
            $this->app->bind(NotificationChannel::Email->value.'_adapter', MailchimpEmailAdapter::class);
        }
    }

    /**
     * Bind the Firebase custom-token minter for the mobile chat identity bridge
     * (no Next.js proxy in the Flutter path — Laravel mints directly via kreait).
     * Same env/credentials gating as the Firestore gateway: logging stub in
     * local/testing/unconfigured environments.
     */
    private function bindChatTokenMinter(): void
    {
        $credentials = (string) config('services.firebase.credentials', '');

        if ($credentials === '' || $this->app->environment(['local', 'testing'])) {
            $this->app->bind(ChatTokenMinter::class, ChatTokenMinterStub::class);

            return;
        }

        $this->app->bind(ChatTokenMinter::class, FirebaseChatTokenMinter::class);
    }

    /**
     * Bind the real Firestore gateway when service-account credentials are present;
     * otherwise (local/testing/unconfigured) keep the logging stub so the SDK/HTTP
     * surface is never exercised in those environments.
     */
    private function bindFirestoreChatGateway(): void
    {
        $credentials = (string) config('services.firebase.credentials', '');
        $projectId = (string) config('services.firebase.project_id', '');

        if ($credentials === '' || $projectId === '' || $this->app->environment(['local', 'testing'])) {
            $this->app->bind(FirestoreChatGateway::class, FirestoreChatGatewayStub::class);

            return;
        }

        $this->app->singleton(FirestoreChatGateway::class, fn ($app) => new FirebaseFirestoreChatGateway(
            new Client,
            $app['cache.store'],
            $projectId,
            $credentials,
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'communication');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'communication');
        $this->commands([
            WakeupSnoozedInboxItemsCommand::class,
            RetryFailedDispatchesCommand::class,
            DispatchDueCampaignsCommand::class,
        ]);

        Livewire::component(
            'communication.vendor.restricted-chat-panel',
            RestrictedChatPanel::class,
        );

        $this->registerRoutes();
        $this->registerListeners();
        $this->registerSchedule();
        $this->registerRouteModelBindings();

        $this->callAfterResolving(TimelineSourceRegistry::class, function (TimelineSourceRegistry $registry): void {
            $registry->register(ChatThread::class, ...ChatThreadTimelineDescriptors::all());
        });
    }

    private function registerRouteModelBindings(): void
    {
        Route::bind('chatMessageLog', fn (string $value) => ChatMessageLog::query()->where('id', $value)->firstOrFail());
        Route::bind('chatModerationFlag', fn (string $value) => ChatModerationFlag::query()->where('id', $value)->firstOrFail());

        // Admin chat moderation routes use shorter param names matching controller signatures.
        Route::bind('log', fn (string $value) => ChatMessageLog::query()->where('id', $value)->firstOrFail());
        Route::bind('flag', fn (string $value) => ChatModerationFlag::query()->where('id', $value)->firstOrFail());
    }

    private function registerRoutes(): void
    {
        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/v1/customer')
            ->group(__DIR__.'/../Routes/customer.php');

        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/v1/vendor')
            ->group(__DIR__.'/../Routes/vendor.php');

        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/v1/admin')
            ->group(__DIR__.'/../Routes/admin.php');

        // Service-to-service endpoint called by the Firestore onMessageCreated Cloud
        // Function. Authenticated by a shared secret (no Sanctum session).
        Route::middleware(['api', VerifyCloudFunctionSecret::class])
            ->prefix('api/v1/internal')
            ->group(__DIR__.'/../Routes/internal.php');
    }

    private function registerListeners(): void
    {
        // Existing notification listeners
        Event::listen('App\Modules\Booking\Domain\Events\BookingSubmittedToVendor', OnBookingSubmitted::class);
        Event::listen('App\Modules\Booking\Domain\Events\CustomerModificationDecided', OnBookingModified::class);
        Event::listen('App\Modules\Booking\Domain\Events\BookingConfirmed', OnBookingConfirmed::class);
        Event::listen('App\Modules\Booking\Domain\Events\BookingConfirmed', CreateChatThreadOnBookingConfirmed::class);
        Event::listen('App\Modules\Payments\Domain\Events\PaymentCaptured', OnPaymentCaptured::class);
        Event::listen('App\Modules\Booking\Domain\Events\BookingForceCancelled', OnBookingForceCancelledNotifyListener::class);
        Event::listen('App\Modules\Booking\Domain\Events\VendorRejected', OnVendorRejected::class);
        Event::listen('App\Modules\Booking\Domain\Events\BookingCancelled', OnBookingCancelled::class);
        Event::listen('App\Modules\Booking\Domain\Events\AlternativeVendorProposed', OnAlternativeVendorProposed::class);
        // OnRentalDeliveryScheduled, OnSalePreparationStarted, OnDigitalDelivered, OnDigitalExpiringSoon
        // are wired in a later phase once those lifecycle events exist in the Booking module.

        // Change request notification listeners
        Event::listen(ChangeRequestCreated::class, DispatchVendorChangesRequestedNotificationListener::class);
        Event::listen(VendorProfileResubmitted::class, DispatchVendorResubmittedNotificationListener::class);
        Event::listen(ChangeRequestCreated::class, DispatchServiceChangesRequestedNotificationListener::class);
        Event::listen(ChangeRequestResubmitted::class, DispatchServiceResubmittedNotificationListener::class);

        // Admin inbox routing listeners
        Event::listen('App\Modules\Identity\Domain\Events\VendorRegistered', OnVendorRegisteredInbox::class);
        Event::listen('App\Modules\Payments\Domain\Events\PaymentFailed', OnPaymentFailedInbox::class);
        Event::listen('App\Modules\Settlement\Domain\Events\WithdrawalRequested', OnWithdrawalRequestedInbox::class);
        Event::listen('App\Modules\Booking\Domain\Events\BookingStalled', OnBookingStalledInbox::class);
        Event::listen('App\Modules\Communication\Domain\Events\ChatFlagged', OnChatFlaggedInbox::class);
        Event::listen('App\Modules\Catalog\Domain\Events\ServiceSubmittedForReview', OnServiceSubmittedForReviewInbox::class);

        // Chat moderation audit listener — writes one audit_logs row per event.
        Event::listen(ChatThreadFrozen::class, WriteChatModerationAuditListener::class);
        Event::listen(ChatThreadUnfrozen::class, WriteChatModerationAuditListener::class);
        Event::listen(ChatMessageFlagged::class, WriteChatModerationAuditListener::class);
        Event::listen(ChatModerationFlagResolved::class, WriteChatModerationAuditListener::class);
        Event::listen(OffPlatformContactMarked::class, WriteChatModerationAuditListener::class);
        Event::listen(ChatFlagEscalatedToInbox::class, WriteChatModerationAuditListener::class);

        // Chat moderation notification listeners — bilingual push + email per audience.
        Event::listen(ChatThreadFrozen::class, DispatchChatThreadFrozenNotificationsListener::class);
        Event::listen(ChatThreadUnfrozen::class, DispatchChatThreadUnfrozenNotificationsListener::class);

        // Propagate freeze/unfreeze to the Firestore thread (transport-side enforcement).
        Event::listen(ChatThreadFrozen::class, PushChatFreezeToFirestoreListener::class);
        Event::listen(ChatThreadUnfrozen::class, PushChatFreezeToFirestoreListener::class);
        Event::listen(ChatFlagEscalatedToInbox::class, DispatchChatFlagEscalatedNotificationsListener::class);

        // Review notifications — vendor gets in_app + push when a customer submits a review.
        Event::listen('App\Modules\Reviews\Domain\Events\ReviewSubmitted', OnReviewSubmitted::class);

        // Identity — vendor lifecycle
        Event::listen('App\Modules\Identity\Domain\Events\VendorApproved', OnVendorProfileApproved::class);
        Event::listen('App\Modules\Identity\Domain\Events\VendorRejected', OnVendorProfileRejected::class);
        Event::listen('App\Modules\Identity\Domain\Events\VendorDocumentApproved', OnVendorDocumentApproved::class);
        Event::listen('App\Modules\Identity\Domain\Events\VendorDocumentRejected', OnVendorDocumentRejected::class);
        Event::listen('App\Modules\Identity\Domain\Events\VendorSuspended', OnVendorProfileSuspended::class);
        Event::listen('App\Modules\Identity\Domain\Events\VendorAutoSuspended', OnVendorProfileAutoSuspended::class);
        Event::listen('App\Modules\Identity\Domain\Events\VendorAutoSuspended', OnVendorProfileAutoSuspendedInbox::class);
        Event::listen('App\Modules\Identity\Domain\Events\VendorApprovedForType', OnVendorApprovedForType::class);
        Event::listen('App\Modules\Identity\Domain\Events\VendorTypeRevoked', OnVendorTypeRevoked::class);
        Event::listen('App\Modules\Identity\Domain\Events\CustomerRegistered', OnCustomerWelcome::class);
        Event::listen('App\Modules\Identity\Domain\Events\CustomerSuspended', OnCustomerSuspended::class);
        Event::listen('App\Modules\Identity\Domain\Events\VendorUnsuspended', OnVendorUnsuspended::class);
        Event::listen('App\Modules\Identity\Domain\Events\VendorDocumentUploaded', OnVendorDocumentUploadedInbox::class);

        // Booking — modification and timeout
        Event::listen('App\Modules\Booking\Domain\Events\VendorModificationProposed', OnVendorModificationProposed::class);
        Event::listen('App\Modules\Booking\Domain\Events\VendorResponseTimedOut', OnVendorResponseTimedOut::class);

        // Payments — customer-facing failures and refunds
        Event::listen('App\Modules\Payments\Domain\Events\PaymentFailed', OnPaymentFailedCustomer::class);
        Event::listen('App\Modules\Payments\Domain\Events\RefundCompleted', OnRefundCompleted::class);
        Event::listen('App\Modules\Payments\Domain\Events\RefundFailed', OnRefundFailed::class);
        Event::listen('App\Modules\Payments\Domain\Events\ChargebackOpened', OnChargebackOpenedInbox::class);

        // Settlement — withdrawal status
        Event::listen('App\Modules\Settlement\Domain\Events\WithdrawalApproved', OnWithdrawalApproved::class);
        Event::listen('App\Modules\Settlement\Domain\Events\WithdrawalPaid', OnWithdrawalPaid::class);
        Event::listen('App\Modules\Settlement\Domain\Events\WithdrawalRejected', OnWithdrawalRejected::class);

        // TrustSafety — report acknowledgement
        Event::listen('App\Modules\TrustSafety\Domain\Events\ReportSubmitted', [OnReportSubmittedInbox::class, OnReportSubmittedCustomerAck::class]);

        // Catalog — service moderation outcome
        Event::listen('App\Modules\Catalog\Domain\Events\ServicePublished', OnServicePublished::class);
        Event::listen('App\Modules\Catalog\Domain\Events\ServiceRejected', OnServiceRejected::class);
    }

    private function registerSchedule(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            $schedule->command(WakeupSnoozedInboxItemsCommand::class)
                ->everyFiveMinutes()
                ->withoutOverlapping();

            $schedule->command(RetryFailedDispatchesCommand::class)
                ->everyFiveMinutes()
                ->withoutOverlapping();

            $schedule->command(DispatchDueCampaignsCommand::class)
                ->everyMinute()
                ->withoutOverlapping();

            $schedule->command(CheckVendorApprovalSlaCommand::class)
                ->everyThirtyMinutes()
                ->withoutOverlapping();

            $schedule->command(SendEventReminderCommand::class)
                ->dailyAt('08:00')
                ->withoutOverlapping();

            $schedule->command(SendVendorDeadlineReminderCommand::class)
                ->everyThirtyMinutes()
                ->withoutOverlapping();
        });
    }
}
