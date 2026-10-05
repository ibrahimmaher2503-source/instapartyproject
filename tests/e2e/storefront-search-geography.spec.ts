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

async function filterByCairo(page: Page, locale: 'en' | 'ar'): Promise<void> {
    await page.goto(`/${locale}/search`);
    await openFilters(page);

    const governorate = page.locator('[data-search-governorate]');
    const city = page.locator('[data-search-city]');
    await expect(city).toBeDisabled();

    await governorate.selectOption({ label: locale === 'ar' ? 'القاهرة' : 'Cairo' });
    await expect(city).toBeEnabled();
    await expect(city.locator('option').filter({ hasText: locale === 'ar' ? 'مدينة نصر' : 'Nasr City' })).toBeAttached();
    await expect(city.locator('option').filter({ hasText: locale === 'ar' ? 'مدينة الجيزة' : 'Giza City' })).toHaveCount(0);
    await city.selectOption({ label: locale === 'ar' ? 'مدينة نصر' : 'Nasr City' });

    const previousCity = await city.inputValue();
    await governorate.selectOption({ label: locale === 'ar' ? 'الجيزة' : 'Giza' });
    await expect(city).toHaveValue('');
    await expect(city.locator(`option[value="${previousCity}"]`)).toHaveCount(0);

    await governorate.selectOption({ label: locale === 'ar' ? 'القاهرة' : 'Cairo' });
    await expect(city.locator('option').filter({ hasText: locale === 'ar' ? 'مدينة نصر' : 'Nasr City' })).toBeAttached();
    await city.selectOption({ label: locale === 'ar' ? 'مدينة نصر' : 'Nasr City' });
    await page.getByRole('button', { name: /Apply Filters|تطبيق الفلاتر/i }).click();

    await expect(page).toHaveURL(/governorate_public_id=.+&city_public_id=.+/);
    await expect(page.locator('body')).toContainText(locale === 'ar' ? 'خدمة بحث القاهرة' : 'Cairo Search Service');
    await expect(page.locator('body')).not.toContainText(locale === 'ar' ? 'خدمة بحث الجيزة' : 'Giza Search Service');
}

test('search geography dependency works in English desktop and Arabic mobile', async ({ page }) => {
    test.setTimeout(120_000);
    execFileSync('php', [
        'artisan',
        'tinker',
        `--execute=$cairo=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->where('code','EG-CAI')->firstOrFail(); $nasr=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('governorate_id',$cairo->id)->where('name->en','Nasr City')->firstOrFail(); $giza=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->where('code','EG-GIZ')->firstOrFail(); $gizaCity=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('governorate_id',$giza->id)->where('name->en','Giza City')->firstOrFail(); $cairoVendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create(['primary_governorate_id'=>$cairo->id,'primary_city_id'=>$nasr->id]); $gizaVendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create(['primary_governorate_id'=>$giza->id,'primary_city_id'=>$gizaCity->id]); App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->published()->create(['vendor_profile_id'=>$cairoVendor->id,'name'=>['en'=>'Cairo Search Service','ar'=>'خدمة بحث القاهرة']]); App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->published()->create(['vendor_profile_id'=>$gizaVendor->id,'name'=>['en'=>'Giza Search Service','ar'=>'خدمة بحث الجيزة']]);`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });

    await page.setViewportSize({ width: 1440, height: 1000 });
    await filterByCairo(page, 'en');

    await page.setViewportSize({ width: 390, height: 844 });
    await filterByCairo(page, 'ar');
});
