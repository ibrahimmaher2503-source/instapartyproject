<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Discovery\Database\Seeders\HomepagePackageReferenceSeeder;
use App\Modules\Discovery\Domain\Contracts\PackageRecommendationReader;
use App\Modules\Discovery\Domain\Models\PackageRecommendation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;

it('loads ten published homepage packages without dropping the second row', function (): void {
    app()->setLocale('en');
    $occasion = Occasion::factory()->create(['code' => 'birthday']);
    foreach (range(1, 10) as $index) {
        PackageRecommendation::create([
            'name' => ['en' => 'Birthday package '.$index, 'ar' => 'باقة عيد ميلاد '.$index],
            'description' => ['en' => 'Cake and balloons', 'ar' => 'كيك وبالونات'],
            'slug' => 'birthday-package-'.$index,
            'occasion_id' => $occasion->id,
            'min_budget_minor' => 250000,
            'budget_currency' => 'EGP',
            'is_published' => true,
            'display_order' => $index,
        ]);
    }
    $packages = app(PackageRecommendationReader::class)->publishedForHomepage('en');
    expect($packages)->toHaveCount(10);
    $html = Blade::render('<x-home.custom-packages :packages="$packages" :occasions="$occasions" />', [
        'packages' => $packages, 'occasions' => collect([$occasion]),
    ]);
    expect($html)->toContain('Birthday package 10', '/en/packages/'.$packages[9]['public_id'])
        ->toContain('data-package-occasion="birthday"', 'Showing 10 packages');
});

it('keeps homepage packages third in the customer journey', function (): void {
    $occasion = Occasion::factory()->create(['code' => 'birthday']);
    Category::factory()->create(['code' => 'cakes']);
    PackageRecommendation::create([
        'name' => ['en' => 'Birthday package', 'ar' => 'باقة عيد ميلاد'],
        'description' => ['en' => 'Cake and balloons', 'ar' => 'كيك وبالونات'],
        'slug' => 'birthday-package', 'occasion_id' => $occasion->id,
        'is_published' => true, 'display_order' => 1,
    ]);
    $this->get('/en')->assertOk()->assertSeeInOrder([
        'sf-hero-region', 'sf-occasion-section', 'sf-packages-section', 'sf-category-section',
    ], false);
});

it('seeds the approved demo cards idempotently with localized reference metadata', function (): void {
    Storage::fake('public');
    Occasion::factory()->create(['code' => 'birthday']);
    app(HomepagePackageReferenceSeeder::class)->run();
    app(HomepagePackageReferenceSeeder::class)->run();
    expect(PackageRecommendation::count())->toBe(10);
    $packages = app(PackageRecommendationReader::class)->publishedForHomepage('en');
    expect($packages[0]['name'])->toBe('Classic Birthday Package')
        ->and($packages[0]['reference_card']['vendor'])->toBe('Joy Events Cairo')
        ->and($packages[0]['reference_card']['rating'])->toBe('4.9')
        ->and($packages[9]['name'])->toBe('Full Birthday Experience');
    $arabic = app(PackageRecommendationReader::class)->publishedForHomepage('ar');
    expect($arabic[0]['reference_card']['tags'])->toBe(['كيك', 'بالونات', 'ديكور']);
    $package = PackageRecommendation::where('slug', 'homepage-reference-birthday-1')->firstOrFail();
    expect($package->min_budget_minor)->toBe(250000);
    Storage::disk('public')->assertExists($package->getFirstMedia('hero')->id.'/package-reference.png');
});

it('refuses to create reference cards outside local and test environments', function (): void {
    app()->instance('env', 'production');
    expect(fn () => app(HomepagePackageReferenceSeeder::class)->run())->toThrow(RuntimeException::class);
    expect(PackageRecommendation::count())->toBe(0);
});

function homepagePricePackage(array $overrides = []): array
{
    return array_merge([
        'public_id' => '01M4XCVZ0H90REENFXH3X9VTGP', 'name' => 'Price package',
        'description' => 'Cake and balloons', 'occasion_code' => 'birthday',
        'hero_url' => null, 'min_budget' => 'EGP 2,500.00',
        'max_budget' => null, 'fixed_budget' => null,
    ], $overrides);
}

it('preserves budget ranges and never labels fixed prices as starting prices', function (): void {
    app()->setLocale('en');
    $range = Blade::render('<x-home.custom-packages :packages="$packages" />', [
        'packages' => [homepagePricePackage(['max_budget' => 'EGP 4,500.00'])],
    ]);
    expect($range)->toContain('data-package-price-kind="range"', 'EGP 2,500.00 to EGP 4,500.00');
    $fixed = Blade::render('<x-home.custom-packages :packages="$packages" />', [
        'packages' => [homepagePricePackage(['max_budget' => 'EGP 2,500.00', 'fixed_budget' => 'EGP 2,500.00'])],
    ]);
    expect($fixed)->toContain('data-package-price-kind="fixed"')->not->toContain('>From</span>');
});

it('keeps packages with unavailable occasion tabs reachable through the all filter', function (): void {
    $birthday = Occasion::factory()->create(['code' => 'birthday']);
    $html = Blade::render('<x-home.custom-packages :packages="$packages" :occasions="$occasions" />', [
        'packages' => [homepagePricePackage(), homepagePricePackage(['occasion_code' => 'inactive-corporate'])],
        'occasions' => collect([$birthday]),
    ]);
    expect($html)->toContain('data-package-filter="*"', 'data-package-occasion="inactive-corporate"');
});

it('shows at most ten cards before JavaScript initializes the all filter', function (): void {
    $html = Blade::render('<x-home.custom-packages :packages="$packages" />', [
        'packages' => array_map(fn ($index) => homepagePricePackage(['occasion_code' => 'occasion-'.$index]), range(1, 12)),
    ]);
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $visible = (new DOMXPath($document))->query('//a[@data-package-occasion and not(@hidden)]');
    expect($visible->length)->toBe(10);
});
