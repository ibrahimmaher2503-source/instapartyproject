import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const email = 'e2e-sale-vendor@example.test';
const password = 'VendorUat123!';
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

test.use({ storageState: { cookies: [], origins: [] } });

test('an approved sale vendor can open the sale-service create page', async ({ page }) => {
    test.setTimeout(120_000);
    execFileSync('php', [
        'artisan',
        'tinker',
        `--execute=$user=App\\Modules\\Identity\\Domain\\Models\\User::factory()->phoneVerified()->asVendor()->create(['name'=>'E2E Sale Vendor','email'=>'${email}','password'=>'${password}','email_verified_at'=>now()]); $governorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->where('code','EG-CAI')->firstOrFail(); $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('governorate_id',$governorate->id)->firstOrFail(); $vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create(['user_id'=>$user->id,'business_name'=>['en'=>'E2E Sale Vendor','ar'=>'مورد بيع للاختبار'],'primary_governorate_id'=>$governorate->id,'primary_city_id'=>$city->id]); App\\Modules\\Identity\\Domain\\Models\\VendorApprovedProductType::factory()->forType(App\\Modules\\Catalog\\Domain\\Enums\\ProductType::Sale)->create(['vendor_profile_id'=>$vendor->id]);`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });

    await page.goto('/vendor-portal/login');
    await page.locator('[wire\\:model="data.email"]').fill(email);
    await page.locator('[wire\\:model="data.password"]').fill(password);
    await page.getByRole('button', { name: /sign in|تسجيل الدخول/i }).click();
    await expect(page).toHaveURL(/\/vendor-portal\/?$/);

    for (const locale of ['en', 'ar']) {
        await page.setViewportSize(locale === 'ar' ? { width: 390, height: 844 } : { width: 1440, height: 1000 });
        await page.goto(`/vendor-portal/vendor-profile-page?locale=${locale}&lang=${locale}`);
        await expect(page.locator('a[href*="vendor-loyalty-program-page"]')).toHaveCount(0);
        const blocked = await page.goto(`/vendor-portal/vendor-loyalty-program-page?locale=${locale}&lang=${locale}`);
        expect(blocked?.status()).toBe(403);
    }

    const response = await page.goto('/vendor-portal/vendor-sale-services/create?locale=en&lang=en');

    expect(response?.status()).toBe(200);
    await expect(page).toHaveURL(/\/vendor-portal\/vendor-sale-services\/create\?locale=en&lang=en$/);
    await expect(page.locator('[wire\\:model="data.name.en"]')).toBeVisible();
    await expect(page.locator('[wire\\:model="data.name.ar"]')).toBeAttached();
});
