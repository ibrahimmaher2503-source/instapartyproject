<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Filament\Vendor\Pages\VendorServicesListPage;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource;
use App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource;
use App\Modules\Identity\Filament\Resources\VendorProfileResource;
use App\Modules\Identity\Filament\Vendor\Pages\VendorDocumentsPage;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use Illuminate\Contracts\Console\Kernel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || realpath((string) config('database.connections.sqlite.database')) !== realpath(storage_path('vendor-document-types-qa.sqlite'))) {
    throw new RuntimeException('Only the disposable document types database may be seeded.');
}

foreach (['vendor', 'super_admin', 'admin', 'customer'] as $role) {
    Role::findOrCreate($role, 'web');
}
$password = 'DocumentTypes123!';
$admin = User::factory()->superAdmin()->create(['email' => 'documents-admin@example.test', 'password' => $password]);
foreach (['review_vendor_documents', 'approve_vendor_profile', 'approve_vendor_for_type', 'view_any_vendor::profile', 'view_vendor::profile', 'view_any_vendor::document::filament', 'view_vendor::document::filament'] as $permission) {
    $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
}
foreach (ProductType::cases() as $type) {
    foreach (['create', 'update', 'delete', 'publish'] as $operation) {
        Permission::findOrCreate("service.{$operation}.{$type->value}.own", 'web');
    }
}
SubscriptionPlan::factory()->free()->create();
$governorate = Governorate::factory()->create();
$city = City::factory()->create(['governorate_id' => $governorate->id]);
$vendors = [];
foreach (['custom', 'one', 'zero'] as $name) {
    $user = User::factory()->asVendor()->phoneVerified()->create([
        'email' => "documents-{$name}@example.test", 'password' => $password, 'email_verified_at' => now(),
    ]);
    $vendor = VendorProfile::factory()->pending()->create([
        'user_id' => $user->id, 'business_type' => 'individual',
        'business_name' => ['en' => "Documents {$name}", 'ar' => "مستندات {$name}"],
        'address_line' => ['en' => 'QA address', 'ar' => 'عنوان الاختبار'], 'national_id' => '29901011234567',
        'primary_governorate_id' => $governorate->id, 'primary_city_id' => $city->id,
        'bank_name' => 'QA Bank', 'bank_account_holder' => $user->name, 'bank_iban' => 'EG380019000500000000263180002',
    ]);
    VendorBusinessHour::factory()->create(['vendor_profile_id' => $vendor->id]);
    VendorCoverageArea::factory()->create(['vendor_profile_id' => $vendor->id, 'city_id' => $city->id]);
    $vendors[$name] = $vendor->public_id;
}
file_put_contents(storage_path('app/vendor-document-types-qa-fixtures.json'), json_encode($vendors, JSON_THROW_ON_ERROR));
file_put_contents(storage_path('app/vendor-document-types-qa-urls.json'), json_encode([
    'documents' => VendorDocumentsPage::getUrl(panel: 'vendor'),
    'services' => VendorServicesListPage::getUrl(panel: 'vendor'),
    'admin_documents' => VendorDocumentFilamentResource::getUrl(panel: 'admin'),
    'approval' => VendorApprovalQueueResource::getUrl('view', ['record' => $vendors['one']], panel: 'admin'),
    'profile' => VendorProfileResource::getUrl('view', ['record' => $vendors['one']], panel: 'admin'),
], JSON_THROW_ON_ERROR));
