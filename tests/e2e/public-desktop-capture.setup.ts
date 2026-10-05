import { execFileSync } from 'node:child_process';
import path from 'node:path';

const database = path.resolve('storage/e2e.sqlite');

/**
 * Keep the public screenshot run focused on curated, deterministic catalog data.
 * The database is isolated by the Playwright config, so this never changes a
 * developer or production database.
 */
export default function globalSetup(): void {
  const environment = {
    ...process.env,
    APP_ENV: 'testing',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: database,
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync',
  };

  const script = String.raw`
Illuminate\Support\Facades\Http::fake(['*' => Illuminate\Support\Facades\Http::response('', 503)]);
App\Modules\Identity\Domain\Models\User::factory()->create(['email' => 'admin@instaparty.local']);
app(App\Modules\Identity\Database\Seeders\IdentityDevelopmentSeeder::class)->run();
app(App\Modules\Catalog\Database\Seeders\CatalogDevelopmentSeeder::class)->run();
app(App\Modules\Discovery\Database\Seeders\DiscoveryDevelopmentSeeder::class)->run();
$occasionNames = [
    'birthday' => ['en' => 'Birthday', 'ar' => 'عيد ميلاد'],
    'baby-shower' => ['en' => 'Baby Shower', 'ar' => 'حفلة استقبال المولود'],
    'graduation' => ['en' => 'Graduation', 'ar' => 'تخرج'],
    'family-gathering' => ['en' => 'Family Gathering', 'ar' => 'تجمع عائلي'],
    'corporate' => ['en' => 'Corporate Event', 'ar' => 'فعالية شركات'],
    'wedding' => ['en' => 'Wedding', 'ar' => 'زفاف'],
    'engagement' => ['en' => 'Engagement', 'ar' => 'خطوبة'],
];
$occasionClass = 'App\\Modules\\Catalog\\Domain\\Models\\Occasion';
$categoryClass = 'App\\Modules\\Catalog\\Domain\\Models\\Category';
$serviceClass = 'App\\Modules\\Catalog\\Domain\\Models\\Service';
$vendorClass = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';
$homeBlockClass = 'App\\Modules\\Shared\\Domain\\Models\\HomeBlock';
$designTokenClass = 'App\\Modules\\Shared\\Domain\\Models\\DesignToken';
$designTokenSchemaClass = 'App\\Modules\\Shared\\Domain\\Schemas\\DesignTokenSchema';

foreach ($occasionNames as $code => $name) {
    $occasion = $occasionClass::query()->where('code', $code)->firstOrFail();
    $occasion->forceFill(['name' => $name, 'is_active' => true])->save();
}
$occasionClass::query()->whereNotIn('code', array_keys($occasionNames))->update(['is_active' => false]);

$categoryCodes = [
    'birthday-general', 'e2e-birthday-services', 'wedding-general', 'engagement-general',
    'cakes', 'venues', 'balloons-decoration', 'catering', 'photography',
    'entertainment', 'gifts', 'rentals', 'lighting-effects', 'rental-bouncy-castles',
    'rental-party-equipment', 'sale-cakes', 'digital-invitations',
];
$categoryClass::query()->whereNotIn('code', $categoryCodes)->update(['is_active' => false]);
app(Database\Seeders\FrontendAppearanceSeeder::class)->run();

$occasion = $occasionClass::query()->where('code', 'birthday')->firstOrFail();
$birthdayCategory = $categoryClass::query()->where('code', 'birthday-general')->firstOrFail();
$birthdayChild = $categoryClass::query()->where('code', 'e2e-birthday-services')->firstOrFail();
$services = $serviceClass::query()->whereIn('slug', [
    'joy-castle-10x10', 'deluxe-birthday-cake', 'animated-birthday-invite',
])->get()->keyBy('slug');
if ($services->count() !== 3 || $services->pluck('product_type')->unique()->count() !== 3) {
    throw new RuntimeException('Public capture fixtures require one rental, sale, and digital service.');
}

$curatedVendorIds = $services->pluck('vendor_profile_id')->unique()->values();
$vendorClass::query()->whereNotIn('id', $curatedVendorIds)->update(['approval_status' => 'pending']);
$vendorClass::query()->whereIn('id', $curatedVendorIds)->update(['approval_status' => 'approved', 'logo_path' => null, 'cover_path' => null]);
$serviceClass::query()->whereNotIn('slug', $services->keys())->update(['status' => 'draft']);
$serviceClass::query()->whereIn('id', $services->pluck('id'))->update(['status' => 'published']);
Illuminate\Support\Facades\DB::table('media')
    ->where('model_type', $services->first()->getMorphClass())
    ->whereIn('model_id', $services->pluck('id'))
    ->delete();

$featured = $homeBlockClass::query()->where('block_type', 'featured_services')->first();
if ($featured) {
    $payload = $featured->payload;
    $payload['service_public_ids'] = $services->pluck('public_id')->values()->all();
    $featured->forceFill(['payload' => $payload, 'is_visible' => true])->save();
}

$designTokenClass::query()->where('is_active', true)->update(['tokens' => $designTokenSchemaClass::default()]);

$birthdayCategoryIds = $services->pluck('category_id')->push($birthdayCategory->id)->push($birthdayChild->id)->unique()->mapWithKeys(
    fn ($categoryId): array => [$categoryId => ['sort_order' => 1]],
)->all();
$occasion->categories()->syncWithoutDetaching($birthdayCategoryIds);

$serviceClass::query()->where('slug', 'joy-castle-10x10')->update(['category_id' => $birthdayChild->id]);

echo 'Public desktop fixtures ready: '.$occasion->getTranslation('name', 'en').' / '.$occasion->getTranslation('name', 'ar').'; '.$services->count().' typed services.'.PHP_EOL;
`.trim().replace(/\r?\n/g, ' ');

  execFileSync('php', ['artisan', 'tinker', '--execute', script], {
    env: environment,
    stdio: 'inherit',
    timeout: 30_000,
  });
}
