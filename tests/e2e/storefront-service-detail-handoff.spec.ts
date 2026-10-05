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
const services = {
    rental: '01K8W0E2E0EEEEEEEEEEEEEEEE',
    sale: '01K8W0E2E0FFFFFFFFFFFFFFFF',
    digital: '01K8W0E2E0GGGGGGGGGGGGGGGG',
};
const soldOut = '01K8W0E2E0HHHHHHHHHHHHHHHH';

test('customer detail hides removed properties across three product types in EN/AR', async ({ page }) => {
    test.setTimeout(150_000);
    execFileSync('php', ['artisan', 'tinker',
        `--execute=$governorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->where('code','EG-CAI')->firstOrFail(); $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('governorate_id',$governorate->id)->firstOrFail(); $vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create(['primary_governorate_id'=>$governorate->id,'primary_city_id'=>$city->id]); foreach (['rental'=>'${services.rental}','sale'=>'${services.sale}','digital'=>'${services.digital}'] as $type=>$id) { $service=App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->{$type}()->published()->create(['public_id'=>$id,'vendor_profile_id'=>$vendor->id,'name'=>['en'=>'Detail '.$type,'ar'=>'تفاصيل '.$type]]); match ($type) { 'rental'=>App\\Modules\\Catalog\\Domain\\Models\\ServiceRentalDetail::factory()->create(['service_id'=>$service->id,'setup_time_minutes'=>30]), 'sale'=>App\\Modules\\Catalog\\Domain\\Models\\ServiceSaleDetail::factory()->create(['service_id'=>$service->id,'is_perishable'=>true,'is_made_to_order'=>true,'stock_quantity'=>87]), 'digital'=>App\\Modules\\Catalog\\Domain\\Models\\ServiceDigitalDetail::factory()->create(['service_id'=>$service->id,'delivery_method'=>'email']) }; } $service=App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->sale()->published()->create(['public_id'=>'${soldOut}','vendor_profile_id'=>$vendor->id]); App\\Modules\\Catalog\\Domain\\Models\\ServiceSaleDetail::factory()->create(['service_id'=>$service->id,'lead_time_hours'=>0,'stock_quantity'=>0]);`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });

    for (const locale of ['en', 'ar']) {
        await page.setViewportSize(locale === 'ar' ? { width: 390, height: 844 } : { width: 1440, height: 1000 });
        for (const [type, id] of Object.entries(services)) {
            const response = await page.goto(`/${locale}/services/${id}`);
            expect(response?.status()).toBe(200);
            await expect(page.locator('h1')).toHaveText(`${locale === 'ar' ? 'تفاصيل' : 'Detail'} ${type}`);
            await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
            const labels = await page.locator('.sf-service-page dt').allTextContents();
            for (const label of ['Location', 'Made to order', 'Perishable', 'Stock', 'الموقع', 'مصنوع حسب الطلب', 'قابل للتلف', 'المخزون']) {
                expect(labels).not.toContain(label);
            }
            await expect(page.locator('.sf-service-attributes')).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        }
        await page.goto(`/${locale}/services/${soldOut}`);
        await expect(page.locator('.sf-service-attributes')).toHaveCount(0);
        await expect(page.locator('.sf-service-page aside dl')).toHaveCount(0);
        await expect(page.getByRole('button', { name: /out of stock|نف[دذ] المخزون/i })).toBeDisabled();
    }
});
