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

const password = 'VendorUat123!';
const users = {
    pending: 'e2e-approval-pending@example.test',
    rejected: 'e2e-approval-rejected@example.test',
    approved: 'e2e-approval-approved@example.test',
    approved_unverified: 'e2e-approval-approved-unverified@example.test',
};

test.use({ storageState: { cookies: [], origins: [] } });

test('enforces vendor approval on service operations while preserving onboarding access', async ({ page }) => {
    test.setTimeout(180_000);

    execFileSync('php', [
        'artisan',
        'tinker',
        `--execute=$governorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->where('code','EG-CAI')->firstOrFail(); $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('governorate_id',$governorate->id)->firstOrFail(); foreach (${JSON.stringify(Object.entries(users))} as $row) { [$status,$email]=$row; $factory=App\\Modules\\Identity\\Domain\\Models\\User::factory(); if ($status !== 'approved_unverified') { $factory=$factory->phoneVerified(); } $user=$factory->asVendor()->create(['email'=>$email,'password'=>'${password}','email_verified_at'=>now()]); App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->create(['user_id'=>$user->id,'approval_status'=>$status === 'approved_unverified' ? 'approved' : $status,'primary_governorate_id'=>$governorate->id,'primary_city_id'=>$city->id]); }`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });

    for (const locale of ['en', 'ar']) {
        await page.setViewportSize(locale === 'ar' ? { width: 390, height: 844 } : { width: 1440, height: 1000 });

        for (const [status, email] of Object.entries(users)) {
            await page.goto('/vendor-portal/login');
            await page.locator('[wire\\:model="data.email"]').fill(email);
            await page.locator('[wire\\:model="data.password"]').fill(password);
            await page.getByRole('button', { name: /sign in|تسجيل الدخول/i }).click();
            await expect(page).toHaveURL(/\/vendor-portal\/?$/);

            const services = await page.goto(`/vendor-portal/vendor-services-list-page?locale=${locale}&lang=${locale}`);
            const categories = await page.goto(`/vendor-portal/vendor-categories-page?locale=${locale}&lang=${locale}`);

            if (status === 'approved') {
                expect(services?.status()).toBe(200);
                expect(categories?.status()).toBe(200);
                await expect(page.locator('body')).toContainText(/categories|التصنيفات/i);
            } else if (status === 'approved_unverified') {
                expect(services?.status()).toBe(403);
                expect(categories?.status()).toBe(403);
                const phone = await page.goto(`/vendor-portal/vendor-phone-verification-page?locale=${locale}&lang=${locale}`);
                expect(phone?.status()).toBe(200);
            } else {
                expect(services?.status()).toBe(403);
                expect(categories?.status()).toBe(403);
                const profile = await page.goto(`/vendor-portal/vendor-profile-page?locale=${locale}&lang=${locale}`);
                expect(profile?.status()).toBe(200);
            }

            await page.context().clearCookies();
        }
    }
});
