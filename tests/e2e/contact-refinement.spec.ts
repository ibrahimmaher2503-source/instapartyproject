import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs/promises';
import path from 'node:path';

const evidenceDir = path.resolve('docs/qa/screenshots/contact-refinement-2026-09-16');

test.use({ storageState: { cookies: [], origins: [] } });

test.beforeAll(() => {
    execFileSync('php', ['artisan', 'db:seed', '--class=FrontendAppearanceSeeder', '--force'], {
        env: {
            ...process.env,
            APP_ENV: 'testing',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: path.resolve('storage/e2e.sqlite'),
            CACHE_STORE: 'array',
        },
    });
});

test('contact page keeps the real support flow usable across locales and widths', async ({ page }) => {
    const browserErrors: string[] = [];
    page.on('pageerror', (error) => browserErrors.push(error.message));
    page.on('console', (message) => message.type() === 'error' && browserErrors.push(message.text()));

    await page.goto('/ar/p/contact', { waitUntil: 'networkidle' });
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('main aside a[href="mailto:support@instaparty.eg"]')).toBeVisible();
    await expect(page.locator('main aside').getByText('بنها، القليوبية، مصر')).toBeVisible();
    await expect(page.locator('a[href^="tel:"]')).toHaveCount(0);
    await expect(page.locator('#contact-email')).toHaveAttribute('dir', 'ltr');
    expect(await page.locator('#contact-email').evaluate((field) => getComputedStyle(field).borderStyle)).toBe('solid');
    expect(browserErrors).toEqual([]);

    await page.locator('[data-contact-submit]').click();
    await expect(page.locator('#contact-email')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator('#contact-email-error')).toHaveText('أدخل بريدًا إلكترونيًا صحيحًا.');

    await page.locator('#contact-email').fill('qa-contact@example.test');
    await page.locator('#contact-subject').fill('اختبار حالة الخادم');
    await page.locator('#contact-body').fill('رسالة اختبار للتحقق من معالجة الخطأ.');
    await page.route('**/api/v1/customer/support/tickets', async (route) => {
        await route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ errors: { subject: ['raw validation detail'] } }) });
    }, { times: 1 });
    await page.locator('[data-contact-submit]').click();
    await expect(page.locator('#contact-subject')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator('#contact-subject-error')).toHaveText('راجع هذا الحقل وحاول مرة أخرى.');
    await expect(page.locator('[data-contact-status]')).not.toContainText('raw validation detail');
    await page.locator('#contact-subject').fill('اختبار حالة الخادم');

    await page.route('**/api/v1/customer/support/tickets', async (route) => {
        await route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ errors: { message: 'internal detail' } }) });
    }, { times: 1 });
    await page.locator('[data-contact-submit]').click();
    await expect(page.locator('[data-contact-status]')).toContainText('تعذر إرسال الرسالة');
    await expect(page.locator('[data-contact-status]')).not.toContainText('internal detail');
    browserErrors.length = 0;

    await page.route('**/api/v1/customer/support/tickets', async (route) => {
        await new Promise((resolve) => setTimeout(resolve, 250));
        await route.continue();
    }, { times: 1 });
    const responsePromise = page.waitForResponse((response) => response.url().endsWith('/api/v1/customer/support/tickets') && response.request().method() === 'POST');
    await page.locator('[data-contact-submit]').click();
    await expect(page.locator('[data-contact-submit]')).toBeDisabled();
    const response = await responsePromise;
    expect([200, 201]).toContain(response.status());
    await expect(page.locator('[data-contact-status]')).toContainText('تم استلام طلب الدعم');
    await expect(page.locator('[data-contact-status]')).toContainText('TK-');

    await page.getByRole('link', { name: 'عرض الأسئلة الشائعة' }).click();
    await expect(page).toHaveURL(/\/ar\/p\/faq$/);
    await page.goto('/ar/p/contact', { waitUntil: 'networkidle' });
    await page.locator('section[aria-labelledby="contact-seller-title"] a').click();
    await expect(page).toHaveURL(/\/vendor-portal\/register/);

    await fs.mkdir(evidenceDir, { recursive: true });
    for (const width of [1440, 1280, 1024, 768, 390]) {
        await page.setViewportSize({ width, height: width === 390 ? 844 : 900 });
        await page.goto('/ar/p/contact', { waitUntil: 'networkidle' });
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        const heroHeight = await page.locator('.sf-contact-hero').evaluate((hero) => hero.getBoundingClientRect().height);
        expect(heroHeight).toBeLessThanOrEqual(220);
        if (width <= 1024) {
            const form = await page.locator('[aria-labelledby="contact-support-title"]').boundingBox();
            const sidebar = await page.locator('main aside').boundingBox();
            expect(sidebar?.y).toBeGreaterThan((form?.y ?? 0) + (form?.height ?? 0) - 2);
        }
        await page.screenshot({ path: path.join(evidenceDir, `ar-${width}.png`), fullPage: true });
    }

    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: width === 390 ? 844 : 900 });
        await page.goto('/en/p/contact', { waitUntil: 'networkidle' });
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    }

    expect(browserErrors).toEqual([]);
});
