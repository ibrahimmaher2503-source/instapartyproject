import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const environment = {
    ...process.env, APP_ENV: 'testing', APP_DEBUG: 'true', DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.resolve('storage/e2e.sqlite'), CACHE_STORE: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'sync',
};
test.use({ storageState: { cookies: [], origins: [] } });

test('approved vendors reach their document list from navigation and handle a missing file without a page 404', async ({ page }) => {
    test.setTimeout(120_000);
    execFileSync('php', ['artisan', 'tinker',
        "--execute=$user=App\\Modules\\Identity\\Domain\\Models\\User::factory()->phoneVerified()->asVendor()->create(['email'=>'e2e-documents-nav@example.test','password'=>'VendorUat123!','email_verified_at'=>now()]); $governorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->where('code','EG-CAI')->firstOrFail(); $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('governorate_id',$governorate->id)->firstOrFail(); $profile=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create(['user_id'=>$user->id,'primary_governorate_id'=>$governorate->id,'primary_city_id'=>$city->id]); $foreign=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create(['primary_governorate_id'=>$governorate->id,'primary_city_id'=>$city->id]); App\\Modules\\Identity\\Domain\\Models\\VendorDocument::factory()->create(['vendor_profile_id'=>$profile->id,'file_name'=>'owned-unavailable.pdf','file_path'=>'documents/e2e-missing-nav.pdf']); App\\Modules\\Identity\\Domain\\Models\\VendorDocument::factory()->create(['vendor_profile_id'=>$foreign->id,'file_name'=>'foreign-secret.pdf']);",
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });
    await page.goto('/vendor-portal/login');
    await page.locator('[wire\\:model="data.email"]').fill('e2e-documents-nav@example.test');
    await page.locator('[wire\\:model="data.password"]').fill('VendorUat123!');
    await page.getByRole('button', { name: /sign in|تسجيل الدخول/i }).click();
    await expect(page).toHaveURL(/\/vendor-portal\/?$/);
    for (const locale of ['en', 'ar']) {
        await page.setExtraHTTPHeaders({ 'Accept-Language': locale });
        await page.setViewportSize(locale === 'ar' ? { width: 390, height: 844 } : { width: 1440, height: 1000 });
        await page.goto('/vendor-portal');
        if (locale === 'ar') {
            await page.locator('.language-switch-trigger').click();
            await page.locator('button[wire\\:click*="changeLocale"]').filter({ hasText: 'العربية' }).click();
            await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
            // The existing direction hook settles sidebar state after the locale reload.
            await page.waitForLoadState('networkidle');
        }
        if (locale === 'ar') await page.locator('.fi-topbar-open-sidebar-btn').click();
        const link = page.locator('.fi-sidebar a[href*="vendor-documents-page"]').first();
        await expect(link).toBeVisible();
        await link.click();
        await expect(page).toHaveURL(/\/vendor-portal\/vendor-documents-page/);
        await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
        await expect(page.getByText('owned-unavailable.pdf')).toBeVisible();
        await expect(page.getByText('foreign-secret.pdf')).toHaveCount(0);
        await page.getByRole('button', { name: locale === 'ar' ? 'عرض' : 'View', exact: true }).click();
        await expect(page.getByText(locale === 'ar' ? 'ملف المستند غير متاح. يمكنك الاستمرار في إدارة مستنداتك.' : 'This document file is unavailable. You can still manage your documents.')).toBeVisible();
        const response = await page.goto(`/vendor-portal/vendor-documents-page?locale=${locale}&lang=${locale}`);
        expect(response?.status()).toBe(200);
        await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
        await expect(page.getByText('owned-unavailable.pdf')).toBeVisible();
        await page.screenshot({ path: `tests/e2e/.results/vendor-documents-${locale}.png`, fullPage: true });
    }
});
