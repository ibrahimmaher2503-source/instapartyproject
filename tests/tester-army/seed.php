<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceDigitalDetail;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Domain\Models\Region;
use App\Modules\Identity\Database\Seeders\VendorPortalPermissionsSeeder;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = realpath((string) config('database.connections.sqlite.database'));
$projectRoot = getenv('TESTER_ARMY_PROJECT_ROOT') ?: $root;
$allowedParent = realpath($projectRoot.'/.e2e/app');
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || $database === false || $allowedParent === false
    || ! str_starts_with($database, $allowedParent.DIRECTORY_SEPARATOR.'run-')
    || basename($database) !== 'database.sqlite') {
    throw new RuntimeException('Only the dedicated tester.army SQLite fixture can be seeded.');
}
$artifactDirectory = getenv('TESTER_ARMY_ARTIFACT_DIR') ?: $projectRoot.'/.e2e';
if (! is_dir($artifactDirectory)) {
    mkdir($artifactDirectory, 0700, true);
}

foreach (['vendor', 'customer', 'super_admin', 'admin', 'support', 'finance'] as $role) {
    Role::findOrCreate($role, 'web');
}
foreach (['settlement.view_wallet.own', 'settlement.view_withdrawals.own', 'settlement.request_withdrawal.own'] as $permission) {
    Role::findByName('vendor', 'web')->givePermissionTo(Permission::findOrCreate($permission, 'web'));
}
SubscriptionPlan::query()->create([
    'plan_code' => 'free', 'public_id' => (string) Str::ulid(),
    'name' => ['en' => 'Free', 'ar' => 'مجاني'], 'description' => ['en' => 'Test free tier', 'ar' => 'باقة اختبار مجانية'],
    'monthly_price_minor' => 0, 'monthly_price_currency' => 'EGP', 'yearly_price_minor' => 0, 'yearly_price_currency' => 'EGP',
    'is_default' => true, 'is_published' => true, 'display_order' => 1,
]);
$governorate = Governorate::factory()->create(['name' => ['en' => 'Cairo', 'ar' => 'القاهرة']]);
$region = Region::factory()->create(['governorate_id' => $governorate->id]);
$city = City::factory()->create(['governorate_id' => $governorate->id, 'region_id' => $region->id]);
$occasion = Occasion::factory()->create(['code' => 'tester-army-party', 'name' => ['en' => 'Party', 'ar' => 'حفلة']]);
$vendor = VendorProfile::factory()->approved()->create([
    'business_name' => ['en' => 'E2E Celebration Vendor', 'ar' => 'مورد احتفالات تجريبي'],
    'primary_governorate_id' => $governorate->id, 'primary_city_id' => $city->id,
]);
$category = Category::factory()->create([
    'name' => ['en' => 'Party lighting', 'ar' => 'إضاءة الحفلات'],
    'allowed_product_types' => ['rental'],
]);
$occasion->categories()->attach($category);
foreach (['rental', 'sale', 'digital'] as $type) {
    VendorApprovedProductType::factory()->create(['vendor_profile_id' => $vendor->id, 'product_type' => $type]);
}
$service = Service::factory()->rental()->published()->create([
    'vendor_profile_id' => $vendor->id, 'category_id' => $category->id,
    'name' => ['en' => 'E2E Party Lights', 'ar' => 'إضاءة حفلات تجريبية'],
    'short_description' => ['en' => 'Party lighting rental', 'ar' => 'تأجير إضاءة للحفلات'],
    'base_price_minor' => 12500, 'base_price_currency' => 'EGP',
]);
ServiceRentalDetail::factory()->create(['service_id' => $service->id]);
$services = ['rental' => ['id' => $service->public_id, 'names' => ['en' => 'E2E Party Lights', 'ar' => 'إضاءة حفلات تجريبية']]];
foreach (['sale', 'digital'] as $type) {
    $names = $type === 'sale' ? ['en' => 'E2E Party Decorations', 'ar' => 'زينة حفلات تجريبية'] : ['en' => 'E2E Digital Invitation', 'ar' => 'دعوة رقمية تجريبية'];
    $typed = Service::factory()->{$type}()->published()->create([
        'vendor_profile_id' => $vendor->id, 'name' => $names,
        'base_price_minor' => 12500, 'base_price_currency' => 'EGP',
    ]);
    match ($type) {
        'sale' => ServiceSaleDetail::factory()->create(['service_id' => $typed->id, 'stock_quantity' => 20]),
        'digital' => ServiceDigitalDetail::factory()->create(['service_id' => $typed->id, 'has_expiry' => false]),
    };
    $services[$type] = ['id' => $typed->public_id, 'names' => $names];
}
$accounts = [];
foreach (['customer', 'other_customer', 'admin', 'vendor'] as $role) {
    $password = bin2hex(random_bytes(18)).'Aa1!';
    $user = $role === 'vendor' ? $vendor->user : User::factory()->phoneVerified()->create();
    $user->update(['email' => 'tester-army-'.$role.'@example.test', 'password' => $password, 'preferred_locale' => 'en']);
    $user->syncRoles(match ($role) {
        'admin' => ['super_admin'], 'other_customer' => ['customer'], default => [$role]
    });
    $accounts[$role] = ['username' => $user->email, 'password' => $password];
}
file_put_contents($artifactDirectory.'/credentials.json', json_encode($accounts, JSON_THROW_ON_ERROR));
chmod($artifactDirectory.'/credentials.json', 0600);
(new IdentityRolesSeeder)->run();
(new VendorPortalPermissionsSeeder)->run();
// Admin fixture gets the exact read permissions declared by current policies.
foreach (['view_any_rental::service', 'view_any_sale::service', 'view_any_digital::service', 'chat_moderation.view', 'view_any_activitylog'] as $permission) {
    Role::findByName('super_admin', 'web')->givePermissionTo(Permission::findOrCreate($permission, 'web'));
}
foreach (glob($root.'/app/Modules/*/Domain/Policies/*.php') ?: [] as $policy) {
    preg_match_all('/[\'\"](view_any_[a-zA-Z0-9_:.]+)[\'\"]/', file_get_contents($policy), $matches);
    foreach ($matches[1] as $permission) {
        Role::findByName('super_admin', 'web')->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
}
$reviewPublicId = null;
if (getenv('TESTER_ARMY_ADMIN_REVIEW_FIXTURES') === '1') {
    $adminFormPermissions = [
        'create_advertisement::package',
        'create_tax::rate',
        'view_tax::rate',
        'update_tax::rate',
    ];
    $superAdmin = Role::findByName('super_admin', 'web');
    foreach ($adminFormPermissions as $permission) {
        $superAdmin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $customer = User::query()->where('email', 'tester-army-customer@example.test')->firstOrFail();
    $reviewPublicId = ServiceReview::factory()->approved()->create([
        'service_id' => $service->id,
        'user_id' => $customer->id,
        'body' => 'A dedicated review-response browser fixture.',
    ])->public_id;
}
$panelRoutes = ['admin' => [], 'vendor' => []];
foreach (Route::getRoutes() as $route) {
    foreach (['admin', 'vendor'] as $panel) {
        if (str_starts_with((string) $route->getName(), 'filament.'.$panel.'.resources.')
            && str_ends_with((string) $route->getName(), '.index') && ! str_contains($route->uri(), '{')) {
            $panelRoutes[$panel][] = '/'.$route->uri();
        }
    }
}
file_put_contents($artifactDirectory.'/fixtures.json', json_encode([
    'service' => $service->public_id,
    'names' => ['en' => 'E2E Party Lights', 'ar' => 'إضاءة حفلات تجريبية'],
    'services' => $services,
    'vendor' => $vendor->public_id,
    'vendor_slug' => $vendor->slug,
    'governorate' => $governorate->public_id,
    'city' => $city->public_id,
    'occasion' => $occasion->public_id,
    'occasion_code' => $occasion->code,
    ...($reviewPublicId !== null ? ['review_public_id' => $reviewPublicId] : []),
    'panel_routes' => $panelRoutes,
], JSON_THROW_ON_ERROR));
if (getenv('TESTER_ARMY_BENCHMARK') === '1') {
    require __DIR__.'/benchmark/seed.php';
}
