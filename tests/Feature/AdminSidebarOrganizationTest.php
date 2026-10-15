<?php

declare(strict_types=1);

use App\Modules\Booking\Filament\Resources\BookingModificationResource;
use App\Modules\Booking\Filament\Resources\BookingResource;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource;
use App\Modules\Identity\Filament\Resources\VendorProfileResource;
use App\Modules\Support\Filament\Resources\FaqItemResource;
use App\Modules\Support\Filament\Resources\SupportTicketResource;
use Filament\Facades\Filament;

it('keeps technical tools in the last collapsed group in both languages', function (string $locale): void {
    app()->setLocale($locale);
    $panel = Filament::getPanel('admin');
    Filament::setCurrentPanel($panel);

    $groups = collect($panel->getNavigationGroups());
    expect($groups->last()->getLabel())->toBe(__('admin.nav.groups.technical'))
        ->and($groups->last()->isCollapsed())->toBeTrue();

    foreach ([
        'Communication\\Filament\\Pages\\CommunicationProviderHealthPage',
        'Communication\\Filament\\Pages\\ManageProviderSettings',
        'Communication\\Filament\\Resources\\NotificationDispatchResource',
        'Communication\\Filament\\Resources\\ChatMessageLogResource',
        'Communication\\Filament\\Resources\\InboxRoutingRulesResource',
        'Payments\\Filament\\Resources\\PaymentAttemptResource',
        'Payments\\Filament\\Resources\\GatewayWebhookLogResource',
        'Payments\\Filament\\Resources\\IdempotencyKeyResource',
        'Shared\\Filament\\Resources\\FeatureFlagResource',
        'Shared\\Filament\\Resources\\DesignTokenResource',
        'Catalog\\Filament\\Resources\\CategoryFieldSchemaResource',
        'Discovery\\Filament\\Resources\\SearchLogResource',
        'Booking\\Filament\\Resources\\BookingStateTransitionResource',
        'Reviews\\Filament\\Resources\\ReviewModerationLogResource',
        'Subscriptions\\Filament\\Resources\\SubscriptionAuditEntryResource',
        'Settlement\\Filament\\Resources\\LedgerTransactionGroupResource',
        'Settlement\\Filament\\Resources\\ReconciliationRunResource',
        'Settlement\\Filament\\Resources\\ReconciliationFindingResource',
    ] as $suffix) {
        $class = 'App\\Modules\\'.$suffix;
        expect($class::getNavigationGroup())->toBe(__('admin.nav.groups.technical'));
    }
})->with(['en', 'ar']);

it('puts the main business screens first and groups related screens together', function (string $locale): void {
    app()->setLocale($locale);
    $panel = Filament::getPanel('admin');
    Filament::setCurrentPanel($panel);
    $labels = collect($panel->getNavigationGroups())->map(fn ($group) => $group->getLabel())->values()->all();
    expect($labels)->toBe(array_map(fn ($key) => __('admin.nav.groups.'.$key), [
        'operations', 'vendor_management', 'services', 'packages_occasions', 'customers', 'payments',
        'support', 'content', 'growth', 'communication', 'settings', 'security', 'technical',
    ]));

    foreach ([
        'Booking\\Filament\\Resources\\BookingResource' => 'operations',
        'Identity\\Filament\\Resources\\VendorApprovalQueueResource' => 'vendor_management',
        'Identity\\Filament\\Resources\\VendorProfileResource' => 'vendor_management',
        'Shared\\Filament\\Resources\\CmsPageResource' => 'content',
        'Shared\\Filament\\Resources\\HomeBlockResource' => 'content',
        'Discovery\\Filament\\Resources\\PackageRecommendationResource' => 'packages_occasions',
        'Catalog\\Filament\\Resources\\OccasionResource' => 'packages_occasions',
        'Advertising\\Filament\\Resources\\AdvertisementPackageResource' => 'growth',
        'Communication\\Filament\\Resources\\AdminInboxResource' => 'support',
        'Reviews\\Filament\\Resources\\ServiceReviewResource' => 'support',
    ] as $suffix => $group) {
        $class = 'App\\Modules\\'.$suffix;
        expect($class::getNavigationGroup())->toBe(__('admin.nav.groups.'.$group));
    }
    expect(BookingResource::getNavigationSort())
        ->toBeLessThan(BookingModificationResource::getNavigationSort());
    expect(VendorApprovalQueueResource::getNavigationSort())
        ->toBeLessThan(VendorProfileResource::getNavigationSort());
    expect(SupportTicketResource::getNavigationSort())
        ->toBeLessThan(FaqItemResource::getNavigationSort());
})->with(['en', 'ar']);

it('shows package and occasion management together without expanding a group', function (string $locale): void {
    app()->setLocale($locale);
    $group = collect(Filament::getPanel('admin')->getNavigationGroups())
        ->first(fn ($group) => $group->getLabel() === __('admin.nav.groups.packages_occasions'));
    expect($group)->not->toBeNull()
        ->and($group->isCollapsed())->toBeFalse();
})->with(['en', 'ar']);

it('retains every authorized navigation destination and keeps unauthorized screens absent', function (string $role): void {
    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user, 'web');
    $panel = Filament::getPanel('admin');
    Filament::setCurrentPanel($panel);
    $expected = [];
    foreach (array_merge($panel->getPages(), $panel->getResources()) as $class) {
        if ($class::shouldRegisterNavigation() && $class::canAccess() && ! $class::getCluster()) {
            foreach ($class::getNavigationItems() as $item) {
                if ($item->isVisible()) {
                    $expected[] = $item->getUrl();
                }
            }
        }
    }
    $actual = collect(Filament::getNavigation())->flatMap(fn ($group) => $group->getItems())
        ->map(fn ($item) => $item->getUrl())->all();
    sort($expected);
    sort($actual);
    expect($actual)->toBe($expected)->not->toBeEmpty();
})->with(['super_admin', 'support']);
