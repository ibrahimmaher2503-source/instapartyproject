<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Domain\Contracts\PackageRecommendationReader;
use App\Modules\Discovery\Domain\Models\PackageRecommendation;
use Brick\Money\Money;
use Illuminate\Support\Str;

it('formats equal package bounds as one fixed EGP amount on every public surface', function (): void {
    $category = Category::factory()->active()->create(['code' => 'fixed-package-category']);
    $occasion = Occasion::factory()->active()->create(['code' => 'fixed-package-occasion']);
    $category->occasions()->attach($occasion->id, ['sort_order' => 1]);
    Service::factory()->published()->create(['category_id' => $category->id]);
    $package = PackageRecommendation::query()->create([
        'public_id' => (string) Str::ulid(),
        'slug' => 'fixed-price-package',
        'name' => ['en' => 'Fixed price package', 'ar' => 'باقة بسعر ثابت'],
        'description' => ['en' => 'One exact price', 'ar' => 'سعر واحد محدد'],
        'occasion_id' => $occasion->id,
        'min_budget_minor' => 125000,
        'max_budget_minor' => 125000,
        'budget_currency' => 'EGP',
        'is_published' => true,
        'display_order' => 1,
    ]);
    $expected = Money::ofMinor(125000, 'EGP')->formatTo('en');

    $mapped = app(PackageRecommendationReader::class)->findPublishedByPublicId($package->public_id, 'en');
    expect($mapped)
        ->not->toBeNull()
        ->and($mapped['fixed_budget'])->toBe($expected)
        ->and($mapped['min_budget'])->toBe($expected)
        ->and($mapped['max_budget'])->toBe($expected);

    foreach (['/en', '/en/search?category_slug='.$category->code, '/en/packages/'.$package->public_id] as $url) {
        $response = $this->get($url);
        $response
            ->assertOk()
            ->assertSee('data-package-price-kind="fixed"', false)
            ->assertSee($expected);

        expect(substr_count($response->getContent(), e($expected)))->toBe(1);
    }
});
