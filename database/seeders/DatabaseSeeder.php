<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Advertising\Database\Seeders\AdvertisementPackageDevelopmentSeeder;
use App\Modules\Booking\Database\Seeders\BookingDevelopmentSeeder;
use App\Modules\Booking\Database\Seeders\BookingPermissionsSeeder;
use App\Modules\Catalog\Database\Seeders\CatalogDevelopmentSeeder;
use App\Modules\Communication\Database\Seeders\ChangeRequestNotificationTemplateSeeder;
use App\Modules\Communication\Database\Seeders\ChatModerationPermissionsSeeder;
use App\Modules\Communication\Database\Seeders\CommunicationDevelopmentSeeder;
use App\Modules\Communication\Database\Seeders\CommunicationProviderHealthPermissionsSeeder;
use App\Modules\Communication\Database\Seeders\GapNotificationTemplateSeeder;
use App\Modules\Communication\Database\Seeders\InboxRoutingRuleDevelopmentSeeder;
use App\Modules\Communication\Database\Seeders\NotificationProviderSettingsPermissionsSeeder;
use App\Modules\Communication\Database\Seeders\UnifiedLifecycleNotificationTemplateSeeder;
use App\Modules\Discovery\Database\Seeders\DiscoveryDevelopmentSeeder;
use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Database\Seeders\IdentityDevelopmentSeeder;
use App\Modules\Identity\Database\Seeders\VendorPortalPermissionsSeeder;
use App\Modules\Loyalty\Database\Seeders\LoyaltyDevelopmentSeeder;
use App\Modules\Payments\Database\Seeders\PaymentsDevelopmentSeeder;
use App\Modules\Reviews\Database\Seeders\ReviewsDevelopmentSeeder;
use App\Modules\Settlement\Database\Seeders\DefaultCommissionRatesSeeder;
use App\Modules\Settlement\Database\Seeders\ReconciliationRunDevelopmentSeeder;
use App\Modules\Settlement\Database\Seeders\SettlementDevelopmentSeeder;
use App\Modules\Settlement\Database\Seeders\SettlementPermissionsSeeder;
use App\Modules\Shared\Database\Seeders\VendorPortalDevelopmentSeeder;
use App\Modules\Subscriptions\Database\Seeders\SubscriptionDevelopmentSeeder;
use App\Modules\Subscriptions\Database\Seeders\SubscriptionPlansSeeder;
use App\Modules\Tax\Database\Seeders\TaxRateDevelopmentSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            IdentityRolesSeeder::class,
        ]);

        Artisan::call(
            'shield:generate',
            ['--all' => true, '--panel' => 'admin', '--ignore-existing-policies' => true],
            $this->command->getOutput(),
        );

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::where('name', 'super_admin')->first()?->syncPermissions(Permission::all());

        $this->call([
            AdminUserSeeder::class,
            EgyptGeographySeeder::class,
            IdentityDevelopmentSeeder::class,
            VendorPortalPermissionsSeeder::class,
            CatalogDevelopmentSeeder::class,
            BookingDevelopmentSeeder::class,
            BookingPermissionsSeeder::class,
            PaymentsDevelopmentSeeder::class,
            DefaultCommissionRatesSeeder::class,
            FinancialSuspenseAccountsSeeder::class,
            SettlementPermissionsSeeder::class,
            SettlementDevelopmentSeeder::class,
            CommunicationDevelopmentSeeder::class,
            ChangeRequestNotificationTemplateSeeder::class,
            GapNotificationTemplateSeeder::class,
            UnifiedLifecycleNotificationTemplateSeeder::class,
            ChatModerationPermissionsSeeder::class,
            CommunicationProviderHealthPermissionsSeeder::class,
            NotificationProviderSettingsPermissionsSeeder::class,
            ReviewsDevelopmentSeeder::class,
            LoyaltyDevelopmentSeeder::class,
            DiscoveryDevelopmentSeeder::class,
            VendorPortalDevelopmentSeeder::class,
            InformationalCmsPagesSeeder::class,
            CmsPagesSeeder::class,
            AppSettingsSeeder::class,
            FeatureFlagsSeeder::class,
            FrontendAppearanceSeeder::class,
            TaxRateDevelopmentSeeder::class,
            AdvertisementPackageDevelopmentSeeder::class,
            InboxRoutingRuleDevelopmentSeeder::class,
            ReconciliationRunDevelopmentSeeder::class,
            SubscriptionPlansSeeder::class,
            SubscriptionDevelopmentSeeder::class,
            DemoPhotosSeeder::class,
        ]);
    }
}
