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
    test(`customer account preserves legacy gender and offers only male/female in ${locale}`, async ({ page }) => {
        test.setTimeout(90_000);
        const email = `account-handoff-${locale}@example.test`;
        const password = 'CustomerUat123!';
        execFileSync('php', ['artisan', 'tinker',
            `--execute=$user=App\\Modules\\Identity\\Domain\\Models\\User::factory()->phoneVerified()->asCustomer()->create(['email'=>'${email}','password'=>'${password}']); App\\Modules\\Identity\\Domain\\Models\\CustomerProfile::query()->updateOrCreate(['user_id'=>$user->id],['gender'=>'prefer_not_to_say']);`,
        ], { env: environment, stdio: 'inherit', timeout: 30_000 });

        await page.goto('/en/auth/login');
        await page.locator('[name="login"]').fill(email);
        await page.locator('[name="password"]').fill(password);
        await page.getByRole('button', { name: /sign in/i }).click();
        await expect(page).toHaveURL(/\/en$/);
        await page.setViewportSize(locale === 'ar' ? { width: 390, height: 844 } : { width: 1440, height: 1000 });
        await page.goto(`/${locale}/account/profile`);
        await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
        await expect(page.locator('.sf-account-nav a[href*="/wizard"]')).toHaveCount(0);
        const gender = page.locator('select[name="gender"]');
        await expect(gender.locator('option')).toHaveCount(3);
        await expect(gender.locator('option[value="prefer_not_to_say"]')).toHaveCount(0);
        await expect(gender).toHaveValue('');
        await page.locator('[name="name"]').fill('Updated Browser Customer');
        const save = page.getByRole('button', { name: /save changes|حفظ التغييرات/i });
        await save.click();
        await expect(page.locator('[role="status"]')).toContainText(locale === 'ar' ? 'تحديث' : 'updated');
        execFileSync('php', ['artisan', 'tinker',
            `--execute=$user=App\\Modules\\Identity\\Domain\\Models\\User::query()->where('email','${email}')->firstOrFail(); if ($user->name !== 'Updated Browser Customer' || $user->customerProfile->gender !== 'prefer_not_to_say') { throw new RuntimeException('Unrelated save lost legacy gender'); }`,
        ], { env: environment, stdio: 'inherit', timeout: 30_000 });

        for (const value of ['male', 'female']) {
            await gender.selectOption(value);
            await save.click();
            await expect(page.locator('[role="status"]')).toBeVisible();
            await page.reload();
            await expect(gender).toHaveValue(value);
        }
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        await page.goto(`/${locale}/wizard`);
        await expect(page.locator('[data-planner-panel="0"]')).toBeVisible();
    });
}
