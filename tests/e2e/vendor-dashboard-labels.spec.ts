import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const environment = {
    ...process.env, APP_ENV: 'testing', APP_DEBUG: 'true', DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.resolve('storage/e2e.sqlite'), CACHE_STORE: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'sync',
};
test.use({ storageState: { cookies: [], origins: [] } });
const digitalEnabled = process.env.CATALOG_DIGITAL_SERVICES_ENABLED !== 'false';

test('vendor dashboard cards and recent booking statuses are human-readable in both locales', async ({ page }) => {
    test.setTimeout(120_000);
    execFileSync('php', ['artisan', 'tinker',
        "--execute=$user=App\\Modules\\Identity\\Domain\\Models\\User::factory()->phoneVerified()->asVendor()->create(['email'=>'e2e-dashboard-labels@example.test','password'=>'VendorUat123!','email_verified_at'=>now()]); $governorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->where('code','EG-CAI')->firstOrFail(); $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('governorate_id',$governorate->id)->firstOrFail(); $profile=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create(['user_id'=>$user->id,'primary_governorate_id'=>$governorate->id,'primary_city_id'=>$city->id]); foreach(App\\Modules\\Catalog\\Domain\\Enums\\ProductType::cases() as $type){App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->create(['vendor_profile_id'=>$profile->id,'product_type'=>$type]);} $booking=App\\Modules\\Booking\\Domain\\Models\\Booking::factory()->create(['reference_no'=>'BK-E2E-DASH-LABEL']); App\\Modules\\Booking\\Domain\\Models\\BookingVendor::factory()->create(['booking_id'=>$booking->id,'vendor_profile_id'=>$profile->id,'sub_status'=>'timed_out']);",
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });
    await page.goto('/vendor-portal/login');
    await page.locator('[wire\\:model="data.email"]').fill('e2e-dashboard-labels@example.test');
    await page.locator('[wire\\:model="data.password"]').fill('VendorUat123!');
    await page.getByRole('button', { name: /sign in|تسجيل الدخول/i }).click();
    await expect(page).toHaveURL(/\/vendor-portal\/?$/);
    for (const locale of ['en', 'ar']) {
        await page.setExtraHTTPHeaders({ 'Accept-Language': locale });
        await page.setViewportSize(locale === 'ar' ? { width: 390, height: 844 } : { width: 1440, height: 1000 });
        const response = await page.goto(`/vendor-portal?locale=${locale}&lang=${locale}`);
        expect(response?.status()).toBe(200);
        await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
        const labels = locale === 'ar' ? ['خدمات التأجير', 'خدمات البيع', 'الخدمات الرقمية'] : ['Rental Services', 'Sale Services', 'Digital Services'];
        if (!digitalEnabled) {
            const digitalLabel = labels.pop()!;
            await expect(page.locator('.fi-wi-stats-overview-stat').filter({ hasText: digitalLabel })).toHaveCount(0);
            await expect(page.locator('.fi-sidebar a[href*="vendor-digital-services"]')).toHaveCount(0);
        }
        for (const label of labels) {
            const card = page.locator('.fi-wi-stats-overview-stat').filter({ hasText: label });
            await card.scrollIntoViewIfNeeded();
            await expect(card.locator('.fi-wi-stats-overview-stat-label')).toHaveText(label);
            await expect(card.locator('.fi-wi-stats-overview-stat-value')).toHaveText('1');
        }
        const row = page.locator('tr').filter({ hasText: 'BK-E2E-DASH-LABEL' });
        await row.scrollIntoViewIfNeeded();
        await expect(row).toContainText(locale === 'ar' ? 'انتهت المهلة' : 'Timed Out');
        expect(await page.locator('main').innerText()).not.toMatch(/vendor-portal\.|identity::|booking\.|App\\Modules\\|timed_out|in_progress/);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        await page.screenshot({ path: `tests/e2e/.results/vendor-dashboard-labels-${locale}.png`, fullPage: true });
    }
});
