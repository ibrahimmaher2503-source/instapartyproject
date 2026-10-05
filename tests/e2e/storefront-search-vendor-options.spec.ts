import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test, type Page } from '@playwright/test';

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

async function openFilters(page: Page): Promise<void> {
    const panel = page.locator('#search-filters');
    if (!await panel.isVisible()) {
        await page.getByRole('button', { name: /Filters|الفلاتر/i }).click();
    }
    await expect(panel).toBeVisible();
}

async function assertRatingFilter(page: Page, label: string): Promise<void> {
    const rating = page.getByLabel(label, { exact: true });
    await expect(rating).toBeVisible();
    expect(await page.locator('[data-search-vendor]').evaluate((element) => {
        const groups = [...element.closest('form')!.querySelectorAll('.sf-filter-group')];
        return groups.indexOf(element.closest('.sf-filter-group')!) + 1
            === groups.indexOf(document.querySelector('#min_rating')!.closest('.sf-filter-group')!);
    })).toBe(true);
    await rating.selectOption('4');
    await expect(rating).toHaveValue('4');
    const bounds = await rating.boundingBox();
    const viewport = page.viewportSize()!;
    expect(bounds!.x).toBeGreaterThanOrEqual(0);
    expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(viewport.width);
}

test('vendor choices follow the selected occasion and category in both locales', async ({ page }) => {
    test.setTimeout(120_000);
    execFileSync('php', [
        'artisan',
        'tinker',
        `--execute=$birthday=App\\Modules\\Catalog\\Domain\\Models\\Occasion::factory()->create(['code'=>'e2e-vendor-birthday','name'=>['en'=>'E2E Birthday','ar'=>'عيد ميلاد تجريبي']]); $wedding=App\\Modules\\Catalog\\Domain\\Models\\Occasion::factory()->create(['code'=>'e2e-vendor-wedding','name'=>['en'=>'E2E Wedding','ar'=>'زفاف تجريبي']]); $cakes=App\\Modules\\Catalog\\Domain\\Models\\Category::factory()->create(['code'=>'e2e-vendor-cakes','name'=>['en'=>'E2E Cakes','ar'=>'كيك تجريبي']]); $venues=App\\Modules\\Catalog\\Domain\\Models\\Category::factory()->create(['code'=>'e2e-vendor-venues','name'=>['en'=>'E2E Venues','ar'=>'قاعات تجريبية']]); $cakes->occasions()->attach($birthday); $venues->occasions()->attach($wedding); $cairo=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->where('code','EG-CAI')->firstOrFail(); $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('governorate_id',$cairo->id)->firstOrFail(); $cakeVendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create(['business_name'=>['en'=>'E2E Cake Vendor','ar'=>'بائع الكيك التجريبي'],'primary_governorate_id'=>$cairo->id,'primary_city_id'=>$city->id]); $venueVendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create(['business_name'=>['en'=>'E2E Venue Vendor','ar'=>'بائع القاعات التجريبي'],'primary_governorate_id'=>$cairo->id,'primary_city_id'=>$city->id]); App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->published()->create(['vendor_profile_id'=>$cakeVendor->id,'category_id'=>$cakes->id,'name'=>['en'=>'E2E Cake Result','ar'=>'نتيجة كيك تجريبية']]); App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->published()->create(['vendor_profile_id'=>$venueVendor->id,'category_id'=>$venues->id,'name'=>['en'=>'E2E Venue Result','ar'=>'نتيجة قاعة تجريبية']]);`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });

    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto('/en/search');
    await openFilters(page);
    await assertRatingFilter(page, 'Vendor Rating');
    const category = page.locator('[data-search-category]');
    const occasion = page.locator('[data-search-occasion]');
    const vendor = page.locator('[data-search-vendor]');
    await category.selectOption({ label: 'E2E Cakes' });
    await occasion.selectOption({ label: 'E2E Birthday' });
    const cakeVendorOption = vendor.locator('option').filter({ hasText: 'E2E Cake Vendor' });
    await expect(cakeVendorOption).toBeAttached();
    await expect(vendor.locator('option').filter({ hasText: 'E2E Venue Vendor' })).toHaveCount(0);
    await vendor.selectOption((await cakeVendorOption.getAttribute('value'))!);
    await category.selectOption({ label: 'E2E Venues' });
    await expect(vendor).toHaveValue('');
    await occasion.selectOption({ label: 'E2E Wedding' });
    await expect(vendor.locator('option').filter({ hasText: 'E2E Venue Vendor' })).toBeAttached();

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/ar/search');
    await openFilters(page);
    await assertRatingFilter(page, 'تقييم المورد');
    await page.locator('[data-search-category]').selectOption({ label: 'كيك تجريبي' });
    await page.locator('[data-search-occasion]').selectOption({ label: 'عيد ميلاد تجريبي' });
    await expect(page.locator('[data-search-vendor] option').filter({ hasText: 'بائع الكيك التجريبي' })).toBeAttached();
    await expect(page.locator('[data-search-vendor] option').filter({ hasText: 'بائع القاعات التجريبي' })).toHaveCount(0);
});
