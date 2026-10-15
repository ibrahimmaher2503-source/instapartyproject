import { execFileSync } from 'node:child_process';
import { mkdirSync, existsSync, readFileSync, unlinkSync } from 'node:fs';
import path from 'node:path';
import { expect, test, type Page } from '@playwright/test';
import { releaseReadinessEnvironment } from './release-readiness.setup';

const appOrigin = 'http://127.0.0.1:8214';
const runtime = path.resolve('storage/release-readiness');
const screenshotDir = path.join(runtime, 'screenshots');
let uploadedIconPath: string | undefined;

test.afterEach(() => {
    if (uploadedIconPath && existsSync(uploadedIconPath)) unlinkSync(uploadedIconPath);
    uploadedIconPath = undefined;
});
async function blockExternalRequests(page: Page): Promise<void> {
    await page.route('**/*', (route) => {
        const url = route.request().url();
        return url.startsWith(appOrigin) || /^(data|blob):/.test(url) ? route.continue() : route.abort();
    });
}

async function saveScreenshot(page: Page, name: string): Promise<void> {
    mkdirSync(screenshotDir, { recursive: true });
    await page.screenshot({ path: path.join(screenshotDir, name), fullPage: true });
}

function storedOccasionIcon(code: string): string {
    const output = execFileSync('php', [
        'artisan',
        'tinker',
        `--execute=echo 'RR_ICON:'.(App\\Modules\\Catalog\\Domain\\Models\\Occasion::query()->where('code','${code}')->value('icon_path') ?? '');`,
    ], { env: releaseReadinessEnvironment, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], timeout: 30_000 });
    const icon = output.match(/RR_ICON:([^\r\n]+)/)?.[1];
    expect(icon, 'the uploaded occasion icon path should persist').toBeTruthy();

    return path.resolve('storage/app/public', icon!);
}

test('front page renders in English and Arabic and fits a 390px mobile viewport', async ({ page }) => {
    await blockExternalRequests(page);
    await page.setViewportSize({ width: 1280, height: 900 });

    for (const locale of ['en', 'ar'] as const) {
        const response = await page.goto(`/${locale}`);
        expect(response?.status()).toBe(200);
        await expect(page.locator('html')).toHaveAttribute('lang', locale);
        await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
        await expect(page.locator('.sf-hero')).toBeVisible();
        await expect(page.locator('.sf-party-builder__form')).toBeVisible();
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        await saveScreenshot(page, `home-${locale}-desktop.png`);
    }

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/ar');
    await expect(page.locator('.sf-hero')).toBeVisible();
    const dimensions = await page.evaluate(() => ({
        viewport: window.innerWidth,
        document: document.documentElement.scrollWidth,
    }));
    expect(dimensions.viewport).toBe(390);
    expect(dimensions.document, 'Arabic homepage should not overflow horizontally at 390px').toBeLessThanOrEqual(390);
    await saveScreenshot(page, 'home-ar-mobile-390.png');
});

test('rental quantity and browser timezone carry from estimator to wizard', async ({ page }) => {
    await blockExternalRequests(page);
    const { servicePublicId, customerEmail } = JSON.parse(readFileSync(path.join(runtime, 'fixtures.json'), 'utf8')) as { servicePublicId: string; customerEmail: string };
    await page.goto('/en/auth/login');
    await page.getByRole('textbox', { name: 'Phone or email' }).fill(customerEmail);
    await page.locator('input[type="password"]').fill('ReleaseCheck123!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/\/en(?:\?.*)?$/);

    await page.goto(`/en/services/${servicePublicId}`);
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');

    const form = page.locator('[data-service-estimate]');
    const start = page.locator('[data-service-estimate-start]');
    const duration = page.locator('[data-service-estimate-duration]');
    const quantity = page.locator('[data-service-estimate-quantity]');
    await expect(form).toBeVisible();
    await expect(page.locator('[data-browser-timezone]')).toHaveValue('America/Los_Angeles');

    const futureStart = await page.evaluate(() => {
        const date = new Date(Date.now() + 48 * 60 * 60 * 1000);
        const part = (type: Intl.DateTimeFormatPartTypes) => new Intl.DateTimeFormat('en-CA', {
            timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
        }).formatToParts(date).find((item) => item.type === type)?.value ?? '';
        return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`;
    });
    await start.fill(futureStart);
    await duration.fill('6');
    await form.locator('button[type="submit"]').click();

    await expect(page).toHaveURL(/quantity=1/);
    // Rental quantity is intentionally represented by a hidden, single-unit input.
    await expect(quantity).toHaveValue('1');
    const wizardLink = page.locator('[data-service-wizard-link]');
    await expect(wizardLink).toHaveAttribute('href', /timezone=America%2FLos_Angeles/);
    const href = await wizardLink.getAttribute('href');
    const url = new URL(href!, appOrigin);
    expect(url.searchParams.get('event_starts_at')).toBe(futureStart);
    expect(url.searchParams.get('quantity')).toBe('1');
    expect(url.searchParams.get('timezone')).toBe('America/Los_Angeles');

    const expectedEnd = await page.evaluate((value) => {
        const date = new Date(value);
        date.setHours(date.getHours() + 6);
        const part = (type: Intl.DateTimeFormatPartTypes) => new Intl.DateTimeFormat('en-CA', {
            timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
        }).formatToParts(date).find((item) => item.type === type)?.value ?? '';
        return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`;
    }, futureStart);
    expect(url.searchParams.get('event_ends_at')).toBe(expectedEnd);

    await wizardLink.click();
    await expect(page).toHaveURL(/\/en\/wizard\?/);
    await expect(page.locator('[data-planner-form] input[name="quantity"]')).toHaveValue('1');
    await expect(page.locator('[data-planner-form] input[name="timezone"]')).toHaveValue('America/Los_Angeles');
    await expect(page.locator('[data-planner-form] input[name="event_starts_at"]')).toHaveValue(futureStart);
    await expect(page.locator('[data-planner-form] input[name="event_ends_at"]')).toHaveValue(expectedEnd);
});

test('admin dashboard navigation and occasion create, edit, and icon upload', async ({ page }) => {
    await blockExternalRequests(page);
    await page.setViewportSize({ width: 1440, height: 950 });

    await page.goto('/admin/login?lang=en');
    await page.getByRole('textbox', { name: 'Email address' }).fill('release-readiness@example.test');
    await page.locator('input[type="password"]').fill('ReleaseCheck123!');
    const loginUpdate = page.waitForResponse((response) => response.url().endsWith('/livewire/update'));
    await page.locator('form button[type="submit"]').click();
    const livewireResponse = await loginUpdate;
    expect(livewireResponse.status(), 'admin authentication request should succeed').toBe(200);
    await expect(page).toHaveURL(/\/admin\/?(?:\?.*)?$/);
    await expect(page.locator('.fi-sidebar')).toBeVisible();
    await expect(page.locator('main')).toBeVisible();
    await saveScreenshot(page, 'admin-dashboard.png');

    const occasionLink = page.locator('.fi-sidebar a[href$="/admin/occasions"]');
    await expect(occasionLink).toBeVisible();
    await occasionLink.click();
    await expect(page).toHaveURL(/\/admin\/occasions/);
    await page.getByRole('link', { name: /new occasion|create occasion/i }).click();

    const code = `release-readiness-${Date.now()}`;
    await page.getByRole('tab', { name: 'English' }).click();
    await page.locator('input[id="data.name.en"]').fill('Release Readiness Occasion');
    await page.getByRole('tab', { name: 'Arabic' }).click();
    await page.locator('input[id="data.name.ar"]').fill('مناسبة اختبار الجاهزية');
    await page.locator('input[type="file"]').setInputFiles({
        name: 'release-readiness-icon.png',
        mimeType: 'image/png',
        buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jp7sAAAAASUVORK5CYII=', 'base64'),
    });
    await page.locator('input[id="data.code"]').fill(code);
    await expect(page.locator('input[id="data.code"]')).toHaveValue(code);
    await page.getByRole('button', { name: /^Create$/ }).click();
    // Filament sends a successful create to the new record's edit page.
    await expect(page).toHaveURL(/\/admin\/occasions\/[^/]+\/edit/);
    await expect(page.locator('input[id="data.code"]')).toHaveValue(code);
    await expect(page.locator('input[id="data.name.en"]')).toHaveValue('Release Readiness Occasion');
    uploadedIconPath = storedOccasionIcon(code);
    expect(existsSync(uploadedIconPath), 'uploaded icon should be saved on the isolated local public disk').toBe(true);

    await page.locator('input[id="data.name.en"]').fill('Edited Release Occasion');
    await page.getByRole('tab', { name: 'Arabic' }).click();
    await page.locator('input[id="data.name.ar"]').fill('مناسبة الجاهزية المعدلة');
    await page.getByRole('button', { name: /^Save$/ }).click();
    await expect(page).toHaveURL(/\/admin\/occasions(?:\?.*)?$/);
    await expect(page.locator('.fi-ta-row').filter({ hasText: code })).toContainText('Edited Release Occasion');
    await saveScreenshot(page, 'admin-occasion-edited.png');
});
