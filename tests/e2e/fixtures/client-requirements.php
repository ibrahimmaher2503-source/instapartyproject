<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || realpath((string) config('database.connections.sqlite.database')) !== realpath(storage_path('client-requirements.sqlite'))) {
    throw new RuntimeException('Only the disposable browser database may be seeded.');
}

foreach (['vendor', 'customer', 'admin', 'super_admin'] as $role) {
    Role::findOrCreate($role, 'web');
}
$primary = Governorate::factory()->create(['name' => ['en' => 'Cairo', 'ar' => 'القاهرة']]);
$other = Governorate::factory()->create(['name' => ['en' => 'Giza', 'ar' => 'الجيزة']]);
$city = City::factory()->create(['governorate_id' => $primary->id, 'name' => ['en' => 'City must not appear', 'ar' => 'مدينة لا تظهر']]);
$coverage = City::factory()->create(['governorate_id' => $other->id]);
$parent = Category::factory()->create(['code' => 'qa-events', 'name' => ['en' => 'Events', 'ar' => 'المناسبات']]);
$child = Category::factory()->create(['code' => 'qa-decor', 'parent_id' => $parent->id, 'name' => ['en' => 'Decoration', 'ar' => 'الديكور']]);
$sibling = Category::factory()->create(['code' => 'qa-light', 'parent_id' => $parent->id, 'name' => ['en' => 'Lighting', 'ar' => 'الإضاءة']]);
Category::factory()->create(['code' => 'qa-empty', 'name' => ['en' => 'No children', 'ar' => 'بدون فئات فرعية']]);
$vendors = [];
foreach (['many', 'one', 'zero', 'foreign'] as $slug) {
    $vendors[$slug] = VendorProfile::factory()->approved()->create([
        'slug' => 'qa-'.$slug, 'primary_city_id' => $city->id, 'primary_governorate_id' => $primary->id,
        'business_name' => ['en' => 'QA '.$slug.' vendor', 'ar' => 'مورد اختبار '.$slug],
    ]);
    $vendors[$slug]->coverageAreas()->create(['city_id' => $coverage->id]);
}
$services = [];
foreach (['rental', 'sale', 'digital'] as $type) {
    for ($index = 1; $index <= ($type === 'rental' ? 16 : 2); $index++) {
        $services[] = Service::factory()->{$type}()->published()->create([
            'vendor_profile_id' => $vendors['many']->id, 'category_id' => $index === 16 ? $sibling->id : $child->id,
            'name' => ['en' => ucfirst($type).' offering '.$index, 'ar' => 'خدمة '.$type.' '.$index],
            'short_description' => ['en' => 'Browser QA fixture', 'ar' => 'بيانات اختبار المتصفح'],
        ]);
    }
}
Service::factory()->rental()->published()->create(['vendor_profile_id' => $vendors['foreign']->id, 'category_id' => $child->id, 'name' => ['en' => 'Foreign offering', 'ar' => 'خدمة مورد آخر']]);
$mediaId = 910000;
$addImage = function ($owner, string $collection, string $color) use (&$mediaId): void {
    $media = $owner->media()->create([
        'id' => $mediaId++, 'collection_name' => $collection, 'name' => 'Client QA fixture', 'file_name' => 'client-qa.svg',
        'mime_type' => 'image/svg+xml', 'disk' => 'public', 'size' => 200, 'manipulations' => [],
        'custom_properties' => [], 'generated_conversions' => ['large' => true], 'responsive_images' => [],
    ]);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="675"><rect width="1200" height="675" fill="'.$color.'"/><circle cx="600" cy="337" r="180" fill="#FBF7EE"/></svg>';
    Storage::disk('public')->put($media->getPathRelativeToRoot(), $svg);
    // This checkout has a copied public/storage directory; preserve it and copy only QA assets.
    $publicFile = public_path('storage/'.$media->getPathRelativeToRoot());
    if (! is_dir(dirname($publicFile))) {
        mkdir(dirname($publicFile), 0755, true);
    }
    file_put_contents($publicFile, $svg);
};
$addImage($vendors['many'], 'portfolio', '#111B3A');
$addImage($services[0], 'gallery', '#087F5B');
$addImage($services[1], 'gallery', '#C5A25D');
$addImage($vendors['one'], 'portfolio', '#1C2B54');
file_put_contents(storage_path('app/client-requirements-fixtures.json'), json_encode(['vendor' => $vendors['many']->public_id, 'slugs' => array_map(fn ($vendor) => $vendor->slug, $vendors)], JSON_THROW_ON_ERROR));
echo "Client browser fixtures ready in disposable SQLite.\n";
