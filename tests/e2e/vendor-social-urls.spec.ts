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

test.use({ storageState: { cookies: [], origins: [] } });

for (const locale of ['en', 'ar']) {
    test(`vendor registration and profile retain optional social URLs in ${locale}`, async ({ page }) => {
        test.setTimeout(90_000);
        const email = `e2e-social-${locale}@example.test`;
        await page.setExtraHTTPHeaders({ 'Accept-Language': locale });
        // The panel language switch also reads its explicit locale parameter.
        await page.route('**/livewire/update', async (route) => {
            const url = new URL(route.request().url());
            url.searchParams.set('locale', locale);
            url.searchParams.set('lang', locale);
            await route.continue({ url: url.toString() });
        });
        await page.goto(`/vendor-portal/register?locale=${locale}&lang=${locale}`);
        const input = (field: string) => page.locator(`[id="data.${field}"]`);
        await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
        await expect(page.getByText(locale === 'ar' ? 'رابط الموقع الإلكتروني' : 'Website URL', { exact: true })).toBeVisible();
        await input('name').fill('E2E Social Vendor');
        await input('email').fill(email);
        await input('phone_e164').fill(locale === 'ar' ? '+201000009882' : '+201000009881');
        await input('password').fill('SafePassword123!');
        await input('passwordConfirmation').fill('SafePassword123!');
        await input('business_name_en').fill('E2E Social Events');
        await input('business_name_ar').fill('فعاليات التواصل للاختبار');
        await page.locator('select[id="data.business_type"]').selectOption('individual');
        await expect(page.locator('select[id="data.business_type"] option:checked')).toHaveText(locale === 'ar'
            ? 'فرد / بدون شركة' : 'Individual / No company');
        const governorate = page.locator('select[id="data.primary_governorate_id"]');
        const cairo = governorate.locator('option').filter({ hasText: /Cairo|القاهرة/ }).first();
        await governorate.selectOption((await cairo.getAttribute('value'))!);
        const city = page.locator('select[id="data.primary_city_id"]');
        await expect(city).toHaveCount(0);
        await input('facebook_url').fill('https://www.facebook.com/social-events');
        await input('website_url').fill('https://social-events.example');
        await input('instagram_url').fill('https://www.instagram.com/social-events');
        await input('phone_e164').fill('+001001234567');
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'POST'
                && response.url().includes('/livewire/update'), { timeout: 30_000 }),
            page.getByRole('button', { name: /create account|sign up|إنشاء حساب/i }).click(),
        ]);
        await expect(page.getByText(locale === 'ar'
            ? 'يجب أن يكون رقم الهاتف بصيغة E.164، مثال: +201001234567.'
            : 'Phone number must be in E.164 format, for example: +201001234567.', { exact: true })).toBeVisible();
        await expect(page).toHaveURL(/\/vendor-portal\/register/);
        await input('phone_e164').fill(locale === 'ar' ? '+20 100 000 9882' : '+20 100 000 9881');
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'POST'
                && response.url().includes('/livewire/update'), { timeout: 30_000 }),
            page.getByRole('button', { name: /create account|sign up|إنشاء حساب/i }).click(),
        ]);
        await expect(page).toHaveURL(/email-verification\/prompt/, { timeout: 30_000 });

        // Verify saved values independently, then prepare the approval prerequisite
        // so this case can exercise editing without depending on delivery providers.
        execFileSync('php', ['artisan', 'tinker',
            `--execute=$user=App\\Modules\\Identity\\Domain\\Models\\User::query()->where('email','${email}')->firstOrFail(); $profile=$user->vendorProfile; if ($profile->website_url !== 'https://social-events.example' || $profile->facebook_url !== 'https://www.facebook.com/social-events' || $profile->instagram_url !== 'https://www.instagram.com/social-events') { throw new RuntimeException('Registration URLs not persisted'); } $user->markEmailAsVerified(); $user->update(['phone_verified_at'=>now()]); $profile->update(['approval_status'=>'approved','national_id'=>'29901011234567','address_line'=>['en'=>'10 Test Street','ar'=>'١٠ شارع الاختبار']]);`,
        ], { env: environment, stdio: 'inherit', timeout: 30_000 });

        await page.goto(`/vendor-portal/vendor-profile-page?locale=${locale}&lang=${locale}`);
        await expect(input('website_url')).toHaveValue('https://social-events.example');
        await expect(input('facebook_url')).toHaveValue('https://www.facebook.com/social-events');
        await expect(input('instagram_url')).toHaveValue('https://www.instagram.com/social-events');
        await expect(city).toBeVisible();
        await expect(city).toHaveValue('');
        const nasr = city.locator('option').filter({ hasText: /Nasr City|مدينة نصر/ }).first();
        await expect(nasr).toBeAttached();
        await city.selectOption((await nasr.getAttribute('value'))!);
        const businessType = page.locator('select[id="data.business_type"]');
        await expect(businessType).toHaveValue('individual');
        await expect(input('commercial_register_no')).toHaveCount(0);
        await expect(input('tax_id')).toHaveCount(0);
        await businessType.selectOption('company');
        await expect(input('commercial_register_no')).toBeVisible();
        await expect(input('tax_id')).toBeVisible();
        await expect(input('national_id')).toHaveCount(0);
        await businessType.selectOption('individual');
        await expect(input('national_id')).toBeVisible();
        await expect(input('tax_id')).toHaveCount(0);
        await input('website_url').fill('https://updated-events.example');
        await input('facebook_url').fill('');
        await page.getByRole('button', { name: /save changes|حفظ التغييرات/i }).click();
        await expect(page.getByText(locale === 'ar' ? 'تم حفظ الملف الشخصي.' : 'Profile saved.', { exact: true })).toBeVisible({ timeout: 30_000 });
        await page.reload();
        await expect(input('website_url')).toHaveValue('https://updated-events.example');
        await expect(input('facebook_url')).toHaveValue('');
        await expect(input('instagram_url')).toHaveValue('https://www.instagram.com/social-events');
        await expect(businessType).toHaveValue('individual');
    });
}
