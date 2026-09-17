<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Discovery\Domain\Contracts\PackageRecommendationReader;
use App\Modules\Discovery\Domain\Models\PackageRecommendation;
use Illuminate\Support\Str;

it('returns only the first four published packages related to the selected category', function (): void {
    $category = Category::factory()->active()->create();
    $otherCategory = Category::factory()->active()->create();
    $occasion = Occasion::factory()->active()->create(['code' => 'category-package-occasion']);
    $otherOccasion = Occasion::factory()->active()->create(['code' => 'other-package-occasion']);

    $category->occasions()->attach($occasion->id, ['sort_order' => 1]);
    $otherCategory->occasions()->attach($otherOccasion->id, ['sort_order' => 1]);

    foreach (range(1, 5) as $order) {
        PackageRecommendation::query()->create([
            'public_id' => (string) Str::ulid(),
            'slug' => "related-package-{$order}",
            'name' => ['en' => "Related package {$order}", 'ar' => "باقة مرتبطة {$order}"],
            'description' => ['en' => 'Related package', 'ar' => 'باقة مرتبطة'],
            'occasion_id' => $occasion->id,
            'budget_currency' => 'EGP',
            'is_published' => true,
            'display_order' => $order,
        ]);
    }

    PackageRecommendation::query()->create([
        'public_id' => (string) Str::ulid(),
        'slug' => 'unrelated-package',
        'name' => ['en' => 'Unrelated package', 'ar' => 'باقة غير مرتبطة'],
        'description' => ['en' => 'Unrelated package', 'ar' => 'باقة غير مرتبطة'],
        'occasion_id' => $otherOccasion->id,
        'budget_currency' => 'EGP',
        'is_published' => true,
        'display_order' => 0,
    ]);

    $packages = app(PackageRecommendationReader::class)->publishedForCategories('ar', [$category->id]);

    expect(array_column($packages, 'slug'))
        ->toBe(['related-package-1', 'related-package-2', 'related-package-3', 'related-package-4'])
        ->and(array_column($packages, 'occasion_code'))->each->toBe('category-package-occasion');
});

it('does not mix global or unrelated packages into an occasion hero', function (): void {
    $occasion = Occasion::query()->create([
        'public_id' => (string) Str::ulid(),
        'code' => 'occasion-hero',
        'name' => ['en' => 'Occasion hero', 'ar' => 'واجهة المناسبة'],
        'description' => ['en' => 'Occasion hero', 'ar' => 'واجهة المناسبة'],
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $otherOccasion = Occasion::query()->create([
        'public_id' => (string) Str::ulid(),
        'code' => 'other-occasion',
        'name' => ['en' => 'Other occasion', 'ar' => 'مناسبة أخرى'],
        'description' => ['en' => 'Other occasion', 'ar' => 'مناسبة أخرى'],
        'sort_order' => 2,
        'is_active' => true,
    ]);

    foreach (range(1, 5) as $order) {
        PackageRecommendation::query()->create([
            'public_id' => (string) Str::ulid(),
            'slug' => "occasion-package-{$order}",
            'name' => ['en' => "Occasion package {$order}", 'ar' => "باقة المناسبة {$order}"],
            'description' => ['en' => 'Occasion package', 'ar' => 'باقة المناسبة'],
            'occasion_id' => $occasion->id,
            'budget_currency' => 'EGP',
            'is_published' => true,
            'display_order' => $order,
        ]);
    }

    foreach ([$otherOccasion->id, null] as $index => $occasionId) {
        PackageRecommendation::query()->create([
            'public_id' => (string) Str::ulid(),
            'slug' => "excluded-package-{$index}",
            'name' => ['en' => 'Excluded package', 'ar' => 'باقة مستبعدة'],
            'description' => ['en' => 'Excluded package', 'ar' => 'باقة مستبعدة'],
            'occasion_id' => $occasionId,
            'budget_currency' => 'EGP',
            'is_published' => true,
            'display_order' => 0,
        ]);
    }

    $packages = app(PackageRecommendationReader::class)->publishedForContext('ar', $occasion->code);

    expect(array_column(array_slice($packages, 0, 4), 'slug'))
        ->toBe(['occasion-package-1', 'occasion-package-2', 'occasion-package-3', 'occasion-package-4']);
});
