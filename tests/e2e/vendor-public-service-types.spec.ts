import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const environment = {
    ...process.env,
    APP_ENV: 'testing',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.resolve('storage/e2e.sqlite'),
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync',
};
const vendorId = '01K8W0E2E0JJJJJJJJJJJJJJJJ';
const digitalEnabled = process.env.CATALOG_DIGITAL_SERVICES_ENABLED !== 'false';

test('public vendor type counts follow real published services, last deletion and empty state', async ({ page }) => {
    test.setTimeout(120_000);
    execFileSync('php', ['artisan', 'tinker',
        `--execute=$governorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->where('code','EG-CAI')->firstOrFail(); $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('governorate_id',$governorate->id)->firstOrFail(); $vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create(['primary_governorate_id'=>$governorate->id,'primary_city_id'=>$city->id,'public_id'=>'${vendorId}','business_name'=>['en'=>'Type Count Vendor','ar'=>'مورد اختبار أنواع الخدمات']]); foreach (App\\Modules\\Catalog\\Domain\\Enums\\ProductType::cases() as $type) { App\\Modules\\Identity\\Domain\\Models\\VendorApprovedProductType::factory()->forType($type)->create(['vendor_profile_id'=>$vendor->id]); App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->{$type->value}()->create(['vendor_profile_id'=>$vendor->id]); } App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->rental()->published()->count(13)->create(['vendor_profile_id'=>$vendor->id]); App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->sale()->published()->create(['vendor_profile_id'=>$vendor->id]); App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->digital()->published()->create(['vendor_profile_id'=>$vendor->id]);`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });

    for (const locale of ['en', 'ar']) {
        await page.setViewportSize(locale === 'ar' ? { width: 390, height: 844 } : { width: 1440, height: 1000 });
        await page.goto(`/${locale}/vendors/${vendorId}`);
        await expect(page.locator('[data-vendor-types-count] p').last()).toHaveText(digitalEnabled ? '3' : '2');
        await expect(page.locator('[data-vendor-services-count] p').last()).toHaveText(digitalEnabled ? '15' : '14');
        await expect(page.locator('[data-vendor-service-type="rental"]')).toContainText('13');
        await expect(page.locator('[data-vendor-service-type="sale"]')).toBeVisible();
        await expect(page.locator('[data-vendor-service-type="digital"]')).toHaveCount(digitalEnabled ? 1 : 0);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    }

    execFileSync('php', ['artisan', 'tinker',
        `--execute=$vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::query()->where('public_id','${vendorId}')->firstOrFail(); $vendor->services()->where('product_type','sale')->published()->get()->each->delete();`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });
    await page.reload();
    await expect(page.locator('[data-vendor-service-type="sale"]')).toHaveCount(0);
    await expect(page.locator('[data-vendor-types-count] p').last()).toHaveText(digitalEnabled ? '2' : '1');
    await expect(page.locator('[data-vendor-services-count] p').last()).toHaveText(digitalEnabled ? '14' : '13');

    execFileSync('php', ['artisan', 'tinker',
        `--execute=$vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::query()->where('public_id','${vendorId}')->firstOrFail(); $vendor->services()->published()->get()->each->delete();`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });
    await page.reload();
    await expect(page.locator('[data-vendor-types-count]')).toHaveCount(0);
    await expect(page.locator('[data-vendor-services-count] p').last()).toHaveText('0');
});
