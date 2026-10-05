import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const environment = {
    ...process.env,
    APP_ENV: 'testing',
    APP_DEBUG: 'true',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.resolve('storage/e2e.sqlite'),
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync',
};

test('matching package cards open a working next step in English and Arabic', async ({ page }) => {
    test.setTimeout(120_000);
    execFileSync('php', [
        'artisan',
        'tinker',
        `--execute=$occasion=App\\Modules\\Catalog\\Domain\\Models\\Occasion::factory()->create(['code'=>'e2e-package-occasion','name'=>['en'=>'Package Occasion','ar'=>'مناسبة الباقة']]); $category=App\\Modules\\Catalog\\Domain\\Models\\Category::factory()->create(['code'=>'e2e-package-category','name'=>['en'=>'Package Category','ar'=>'فئة الباقة']]); $category->occasions()->attach($occasion); $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->firstOrFail(); $vendor=Database\\Factories\\VendorProfileFactory::new()->approved()->create(['primary_governorate_id'=>$city->governorate_id,'primary_city_id'=>$city->id]); App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->published()->create(['vendor_profile_id'=>$vendor->id,'category_id'=>$category->id]); App\\Modules\\Discovery\\Domain\\Models\\PackageRecommendation::query()->create(['public_id'=>(string)Illuminate\\Support\\Str::ulid(),'slug'=>'e2e-package-results','name'=>['en'=>'Search Results Package','ar'=>'باقة نتائج البحث'],'description'=>['en'=>'Shown with matching search results','ar'=>'تظهر مع نتائج البحث المطابقة'],'occasion_id'=>$occasion->id,'min_budget_minor'=>150000,'max_budget_minor'=>150000,'budget_currency'=>'EGP','is_published'=>true,'display_order'=>1]);`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });

    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto('/en/search?category_slug=e2e-package-category');
    const results = page.locator('.sf-search-results');
    await expect(results.locator('[data-search-package-results]')).toBeVisible();
    await expect(results.locator('[data-search-package-card]')).toContainText('Search Results Package');
    await expect(results.locator('[data-package-price-kind="fixed"]')).toBeVisible();
    await expect(results.locator('[data-package-price-kind="fixed"]')).not.toContainText(' to ');
    await expect(page.locator('.sf-search-hero [data-search-package-results]')).toHaveCount(0);
    await results.locator('[data-search-package-card]').click();
    await expect(page).toHaveURL(/\/en\/packages\/[0-9A-HJKMNP-TV-Z]{26}$/);
    await expect(page.locator('[data-package-details]')).toContainText('Shown with matching search results');
    await expect(page.locator('[data-package-budget][data-package-price-kind="fixed"]')).toBeVisible();
    await expect(page.locator('[data-package-next-step]')).toHaveAttribute('href', /\/en\/wizard\?occasion=e2e-package-occasion$/);

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/ar/search?category_slug=e2e-package-category');
    const arabicCard = page.locator('.sf-search-results [data-search-package-card]');
    await expect(arabicCard).toContainText('باقة نتائج البحث');
    await arabicCard.click();
    await expect(page.locator('[data-package-details]')).toContainText('تظهر مع نتائج البحث المطابقة');
    await expect(page.locator('[data-package-budget][data-package-price-kind="fixed"]')).not.toContainText('إلى');
    await expect(page.locator('[data-package-next-step]')).toHaveAttribute('href', /\/ar\/wizard\?occasion=e2e-package-occasion$/);
});

test('package filter follows the selected occasion and clears a stale package', async ({ page }) => {
    test.setTimeout(120_000);
    execFileSync('php', [
        'artisan',
        'tinker',
        `--execute=$first=App\\Modules\\Catalog\\Domain\\Models\\Occasion::factory()->create(['code'=>'e2e-filter-occasion-one','name'=>['en'=>'First Occasion','ar'=>'المناسبة الأولى']]); $second=App\\Modules\\Catalog\\Domain\\Models\\Occasion::factory()->create(['code'=>'e2e-filter-occasion-two','name'=>['en'=>'Second Occasion','ar'=>'المناسبة الثانية']]); App\\Modules\\Discovery\\Domain\\Models\\PackageRecommendation::query()->create(['public_id'=>(string)Illuminate\\Support\\Str::ulid(),'slug'=>'e2e-filter-package-one','name'=>['en'=>'First Occasion Package','ar'=>'باقة المناسبة الأولى'],'description'=>['en'=>'First package','ar'=>'الباقة الأولى'],'occasion_id'=>$first->id,'budget_currency'=>'EGP','is_published'=>true,'display_order'=>1]); App\\Modules\\Discovery\\Domain\\Models\\PackageRecommendation::query()->create(['public_id'=>(string)Illuminate\\Support\\Str::ulid(),'slug'=>'e2e-filter-package-two','name'=>['en'=>'Second Occasion Package','ar'=>'باقة المناسبة الثانية'],'description'=>['en'=>'Second package','ar'=>'الباقة الثانية'],'occasion_id'=>$second->id,'budget_currency'=>'EGP','is_published'=>true,'display_order'=>2]);`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });

    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto('/en/search');
    const occasion = page.locator('[data-search-occasion]');
    const packageFilter = page.locator('[data-search-package]');
    await expect(packageFilter).toBeDisabled();

    await occasion.selectOption('e2e-filter-occasion-one');
    await expect(packageFilter).toBeEnabled();
    await expect(packageFilter.locator('option')).toContainText(['All packages', 'First Occasion Package']);
    await expect(packageFilter.locator('option', { hasText: 'Second Occasion Package' })).toHaveCount(0);
    await packageFilter.selectOption({ label: 'First Occasion Package' });

    await occasion.selectOption('e2e-filter-occasion-two');
    await expect(packageFilter).toHaveValue('');
    await expect(packageFilter.locator('option')).toContainText(['All packages', 'Second Occasion Package']);
    await expect(packageFilter.locator('option', { hasText: 'First Occasion Package' })).toHaveCount(0);

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/ar/search?occasion=e2e-filter-occasion-one');
    await expect(page.locator('[data-search-package]')).toContainText('باقة المناسبة الأولى');
    await expect(page.locator('[data-search-package]')).not.toContainText('باقة المناسبة الثانية');
});

test('package results follow geography and exact service availability', async ({ page }) => {
    test.setTimeout(120_000);
    const startsAt = new Date(Date.now() + 3 * 86_400_000);
    startsAt.setUTCMinutes(0, 0, 0);
    const startsAtValue = startsAt.toISOString().slice(0, 16);
    execFileSync('php', [
        'artisan',
        'tinker',
        `--execute=$country=App\\Modules\\Geography\\Domain\\Models\\Country::query()->firstOrFail(); $availableGovernorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::factory()->create(['country_id'=>$country->id,'public_id'=>'e2e-gov-available','code'=>'EG-E2E-AVAILABLE','is_active'=>true]); $availableRegion=App\\Modules\\Geography\\Domain\\Models\\Region::factory()->create(['governorate_id'=>$availableGovernorate->id]); $availableCity=App\\Modules\\Geography\\Domain\\Models\\City::factory()->create(['region_id'=>$availableRegion->id,'governorate_id'=>$availableGovernorate->id,'public_id'=>'e2e-city-available','is_active'=>true]); $otherGovernorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::factory()->create(['country_id'=>$country->id,'public_id'=>'e2e-gov-unavailable','code'=>'EG-E2E-UNAVAILABLE','is_active'=>true]); $otherRegion=App\\Modules\\Geography\\Domain\\Models\\Region::factory()->create(['governorate_id'=>$otherGovernorate->id]); App\\Modules\\Geography\\Domain\\Models\\City::factory()->create(['region_id'=>$otherRegion->id,'governorate_id'=>$otherGovernorate->id,'public_id'=>'e2e-city-unavailable','is_active'=>true]); $occasion=App\\Modules\\Catalog\\Domain\\Models\\Occasion::factory()->create(['code'=>'e2e-integrated-occasion']); $category=App\\Modules\\Catalog\\Domain\\Models\\Category::factory()->create(['code'=>'e2e-integrated-category']); $category->occasions()->attach($occasion); $vendor=Database\\Factories\\VendorProfileFactory::new()->approved()->create(['primary_governorate_id'=>$availableGovernorate->id,'primary_city_id'=>$availableCity->id]); App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->published()->create(['slug'=>'e2e-integrated-rental','vendor_profile_id'=>$vendor->id,'category_id'=>$category->id,'product_type'=>App\\Modules\\Catalog\\Domain\\Enums\\ProductType::Rental]); App\\Modules\\Discovery\\Domain\\Models\\PackageRecommendation::query()->create(['public_id'=>'e2e-package-integrated','slug'=>'e2e-package-integrated','name'=>['en'=>'Availability Package','ar'=>'باقة الإتاحة'],'description'=>['en'=>'Availability package','ar'=>'باقة الإتاحة'],'occasion_id'=>$occasion->id,'budget_currency'=>'EGP','is_published'=>true,'display_order'=>1]);`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });

    const query = new URLSearchParams({
        occasion: 'e2e-integrated-occasion',
        governorate_public_id: 'e2e-gov-available',
        city_public_id: 'e2e-city-available',
        event_starts_at: startsAtValue,
    });
    await page.goto(`/en/search?${query}`);
    await expect(page.locator('[data-search-package-results]')).toContainText('Availability Package');

    query.set('governorate_public_id', 'e2e-gov-unavailable');
    query.set('city_public_id', 'e2e-city-unavailable');
    await page.goto(`/en/search?${query}`);
    await expect(page.locator('[data-search-package-results]')).toHaveCount(0);

    execFileSync('php', [
        'artisan',
        'tinker',
        `--execute=$service=App\\Modules\\Catalog\\Domain\\Models\\Service::query()->where('slug','e2e-integrated-rental')->firstOrFail(); $startsAt=Illuminate\\Support\\Carbon::parse('${startsAtValue}','UTC'); App\\Modules\\Catalog\\Domain\\Models\\ServiceAvailabilityBlock::query()->create(['public_id'=>(string)Illuminate\\Support\\Str::ulid(),'service_id'=>$service->id,'starts_at'=>$startsAt->copy()->subMinutes(30),'ends_at'=>$startsAt->copy()->addMinutes(30),'reason'=>['en'=>'Reserved','ar'=>'محجوز']]);`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });

    query.set('governorate_public_id', 'e2e-gov-available');
    query.set('city_public_id', 'e2e-city-available');
    await page.goto(`/en/search?${query}`);
    await expect(page.locator('[data-search-package-results]')).toHaveCount(0);

    query.set('event_starts_at', new Date(startsAt.getTime() + 2 * 3_600_000).toISOString().slice(0, 16));
    await page.goto(`/en/search?${query}`);
    await expect(page.locator('[data-search-package-results]')).toContainText('Availability Package');
});
