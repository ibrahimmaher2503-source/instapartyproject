<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Domain\Models\PackageRecommendation;
use Illuminate\Support\Str;

function createSearchPackage(Occasion $occasion, string $slug, string $name, int $order): PackageRecommendation
{
    return PackageRecommendation::query()->create([
        'public_id' => (string) Str::ulid(),
        'slug' => $slug,
        'name' => ['en' => $name, 'ar' => 'باقة '.$name],
        'description' => ['en' => $name.' description', 'ar' => 'وصف '.$name],
        'occasion_id' => $occasion->id,
        'budget_currency' => 'EGP',
        'is_published' => true,
        'display_order' => $order,
    ]);
}

it('places the package filter immediately after occasion with only linked packages', function (): void {
    $occasion = Occasion::factory()->active()->create(['code' => 'filter-package-occasion']);
    $otherOccasion = Occasion::factory()->active()->create(['code' => 'filter-package-other']);
    $matching = createSearchPackage($occasion, 'filter-package-match', 'Matching package', 1);
    createSearchPackage($otherOccasion, 'filter-package-other', 'Other package', 2);

    $response = $this->get('/en/search?occasion='.$occasion->code);

    $response
        ->assertOk()
        ->assertSee('data-search-package', false);

    $html = $response->getContent();
    $packageSelect = substr(
        $html,
        strpos($html, '<select id="package"'),
        strpos($html, '</select>', strpos($html, '<select id="package"')) - strpos($html, '<select id="package"'),
    );
    expect($packageSelect)
        ->toContain('value="'.$matching->public_id.'"')
        ->toContain('Matching package')
        ->not->toContain('Other package');
    expect(strpos($html, 'data-search-package'))
        ->toBeGreaterThan(strpos($html, 'data-search-occasion'))
        ->toBeLessThan(strpos($html, 'data-search-governorate'));
});

it('keeps only a package linked to the selected occasion and filters package cards', function (): void {
    $occasion = Occasion::factory()->active()->create(['code' => 'filter-selected-occasion']);
    $otherOccasion = Occasion::factory()->active()->create(['code' => 'filter-selected-other']);
    $category = Category::factory()->active()->create();
    $category->occasions()->attach($occasion->id, ['sort_order' => 1]);
    Service::factory()->published()->create(['category_id' => $category->id]);
    $selected = createSearchPackage($occasion, 'filter-selected-package', 'Selected package', 1);
    createSearchPackage($occasion, 'filter-unselected-package', 'Unselected package', 2);
    $stale = createSearchPackage($otherOccasion, 'filter-stale-package', 'Stale package', 3);

    $selectedResponse = $this->get('/en/search?occasion='.$occasion->code.'&package='.$selected->public_id);
    $selectedResponse->assertOk();
    $selectedHtml = $selectedResponse->getContent();
    $packageResults = substr(
        $selectedHtml,
        strpos($selectedHtml, '<section class="sf-context-recommendations'),
        strpos($selectedHtml, '</section>', strpos($selectedHtml, '<section class="sf-context-recommendations')) - strpos($selectedHtml, '<section class="sf-context-recommendations'),
    );
    expect($packageResults)
        ->toContain('Selected package')
        ->not->toContain('Unselected package')
        ->not->toContain('Stale package');

    $staleResponse = $this->get('/en/search?occasion='.$occasion->code.'&package='.$stale->public_id);
    $staleResponse->assertOk();
    $staleHtml = $staleResponse->getContent();
    $packageSelect = substr(
        $staleHtml,
        strpos($staleHtml, '<select id="package"'),
        strpos($staleHtml, '</select>', strpos($staleHtml, '<select id="package"')) - strpos($staleHtml, '<select id="package"'),
    );
    expect($packageSelect)
        ->not->toContain($stale->public_id)
        ->not->toContain('Stale package');
});
