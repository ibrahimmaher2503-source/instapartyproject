import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const vendorPath = (locale: string, key: string) => `/${locale}/vendors/${JSON.parse(fs.readFileSync('storage/app/client-requirements-fixtures.json', 'utf8')).slugs[key]}`;
const widths = [390, 768, 1024, 1440, 1920];
const evidence = path.resolve('docs/qa/screenshots/client-requirements-2026-10-12');
test.beforeAll(() => fs.mkdirSync(evidence, { recursive: true }));
test.beforeEach(async ({ page }, testInfo) => {
    const errors: string[] = [];
    const failures: { url: string; status: number }[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('response', (response) => {
        if (response.status() >= 400) failures.push({ url: response.url(), status: response.status() });
    });
    testInfo['clientEvidence'] = { errors, failures };
});
test.afterEach(async ({}, testInfo) => {
    const observed = testInfo['clientEvidence'];
    await testInfo.attach('console-network', { body: JSON.stringify(observed, null, 2), contentType: 'application/json' });
    expect(observed.errors).toEqual([]);
    const unexpected = observed.failures.filter((failure) => !(testInfo.title.startsWith('zero single') && failure.url.endsWith('/client-qa.svg') && failure.status === 404));
    expect(unexpected).toEqual([]);
});

for (const locale of ['en', 'ar']) {
    for (const width of widths) {
        test(`${locale} ${width}: gallery filters names locations and responsive grid`, async ({ page }) => {
            await page.setViewportSize({ width, height: 1000 });
            const errors: string[] = [];
            page.on('pageerror', (error) => errors.push(error.message));
            await page.goto(vendorPath(locale, 'many'));
            await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
            await expect(page.locator('[data-vendor-services-count]')).toHaveCount(0);
            await expect(page.locator('[data-vendor-types-count]')).toHaveCount(0);
            await expect(page.locator('[data-vendor-services-grid] .sf-catalog-card__meta')).toHaveCount(0);
            await expect(page.locator('[data-vendor-service-names] details')).toHaveCount(0);
            await expect(page.locator('[data-vendor-service-names]')).toContainText(locale === 'ar' ? 'خدمة rental 1' : 'Rental offering 1');
            expect((await page.locator('[data-vendor-service-names]').boundingBox())!.height).toBeLessThan(500);
            await expect(page.locator('[data-vendor-service-type="rental"]')).not.toContainText(/\d/);
            await expect(page.locator('h1').locator('..')).toContainText(locale === 'ar' ? 'القاهرة · الجيزة' : 'Cairo · Giza');
            await expect(page.locator('[data-vendor-services-grid] .sf-catalog-card')).toHaveCount(15);
            const columns = await page.locator('[data-vendor-services-grid]').evaluate((grid) => getComputedStyle(grid).gridTemplateColumns.split(' ').length);
            expect(columns).toBe(width >= 1280 ? 5 : width >= 1024 ? 3 : width >= 640 ? 2 : 1);
            const main = page.locator('[data-service-gallery-main]');
            await expect(main).toBeVisible();
            await expect.poll(() => main.evaluate((img: HTMLImageElement) => img.naturalWidth)).toBeGreaterThan(0);
            const first = await main.getAttribute('src');
            await page.locator('[data-gallery-step="1"]').click();
            expect(await main.getAttribute('src')).not.toBe(first);
            await page.locator('[data-gallery-step="-1"]').click();
            await expect(main).toHaveAttribute('src', first!);
            await page.locator('[data-service-gallery-image]').nth(2).click();
            await expect(page.locator('[data-service-gallery-image]').nth(2)).toHaveAttribute('aria-pressed', 'true');
            await page.screenshot({ path: path.join(evidence, `vendor-${locale}-${width}.png`), fullPage: true });
            await page.locator('[data-vendor-filter-type="rental"]').click();
            await expect(page).toHaveURL(/type=rental/);
            await expect(page.locator('[data-vendor-services-grid]')).not.toContainText('Foreign offering');
            const next = page.locator('a[rel=next]');
            await expect(next).toHaveAttribute('href', /type=rental/);
            await next.click();
            await expect(page.locator('[data-vendor-services-grid] .sf-catalog-card')).toHaveCount(1);
            await page.reload();
            await expect(page.locator('[data-vendor-filter-type="rental"]')).toHaveAttribute('aria-current', 'page');
            await page.locator('[data-vendor-filter-type=""]').click();
            await expect(page.locator('[data-vendor-filter-type=""]')).toHaveAttribute('aria-current', 'page');
            for (const type of ['sale', 'digital']) {
                await page.locator(`[data-vendor-filter-type="${type}"]`).click();
                await expect(page.locator('[data-vendor-services-grid] .sf-catalog-card')).toHaveCount(2);
            }
            await page.locator('[data-vendor-services-grid] img').evaluateAll((images) => images.forEach((image) => image.setAttribute('loading', 'eager')));
            await expect.poll(() => page.locator('img:visible').evaluateAll((images: HTMLImageElement[]) => images.filter((img) => img.complete && img.naturalWidth === 0).length)).toBe(0);
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
            expect(errors).toEqual([]);
        });
        test(`${locale} ${width}: dependent subcategory combined search and reset`, async ({ page }) => {
            await page.setViewportSize({ width, height: 1000 });
            await page.goto(`/${locale}/search?category_slug=qa-events&subcategory=qa-decor&product_type=rental`);
            if (width < 1024) await page.locator('.sf-mobile-filter-button').click();
            await expect(page.locator('#subcategory')).toBeVisible();
            await expect(page.locator('#subcategory')).toHaveValue('qa-decor');
            await expect(page.locator('#subcategory option')).toHaveCount(3);
            await page.locator('#subcategory').selectOption('qa-light');
            await page.locator('[data-search-geography] button[type=submit]').click();
            await expect(page).toHaveURL(/subcategory=qa-light/);
            await expect(page.locator('.sf-catalog-card')).toHaveCount(1);
            await page.reload();
            if (width < 1024) await page.locator('.sf-mobile-filter-button').click();
            await expect(page.locator('#subcategory')).toHaveValue('qa-light');
            await page.locator('#category_slug').selectOption('qa-empty');
            await expect(page.locator('#subcategory')).toHaveValue('');
            await expect(page.locator('#subcategory')).toBeDisabled();
            await page.locator('#category_slug').selectOption('qa-events');
            await expect(page.locator('#subcategory')).toBeEnabled();
            await page.locator('#subcategory').selectOption('qa-decor');
            const fixture = JSON.parse(fs.readFileSync('storage/app/client-requirements-fixtures.json', 'utf8'));
            await page.locator('#vendor').selectOption(fixture.vendor);
            await page.locator('[data-search-geography] button[type=submit]').click();
            await expect(page.locator('.sf-catalog-card')).toHaveCount(12);
            await expect(page.locator('a[rel=next]')).toHaveAttribute('href', /subcategory=qa-decor/);
            await page.locator('a[rel=next]').click();
            await expect(page.locator('.sf-catalog-card')).toHaveCount(3);
            await page.screenshot({ path: path.join(evidence, `search-${locale}-${width}.png`), fullPage: true });
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
        });
    }
}

test('zero single and failed gallery media have deliberate states', async ({ page }) => {
    await page.goto(vendorPath('en', 'zero'));
    await expect(page.locator('[data-vendor-gallery-empty]')).toBeVisible();
    await expect(page.locator('[data-gallery-step]')).toHaveCount(0);
    await page.goto(vendorPath('en', 'one'));
    await expect(page.locator('[data-service-gallery-main]')).toBeVisible();
    await expect(page.locator('[data-gallery-step]')).toHaveCount(0);
    await page.route('**/storage/910000/client-qa.svg', (route) => route.fulfill({ status: 404, body: '' }));
    await page.goto(vendorPath('en', 'many'));
    await expect(page.locator('[data-service-gallery-main]')).toBeVisible();
    await expect.poll(() => page.locator('[data-service-gallery-main]').evaluate((image: HTMLImageElement) => image.naturalWidth)).toBeGreaterThan(0);
    await expect(page.locator('[data-service-gallery-image]')).toHaveCount(2);
    await page.unroute('**/storage/910000/client-qa.svg');
    await page.route('**/storage/**/client-qa.svg', (route) => route.fulfill({ status: 404, body: '' }));
    await page.goto(vendorPath('en', 'one'));
    await expect(page.locator('[data-vendor-gallery-empty]')).toBeVisible();
    await expect(page.locator('[data-vendor-gallery]')).toBeHidden();
    await page.goto(vendorPath('en', 'many'));
    await expect(page.locator('[data-vendor-gallery-empty]')).toBeVisible();
    await expect(page.locator('[data-vendor-gallery]')).toBeHidden();
});

for (const locale of ['en', 'ar']) for (const width of [390, 706, 1440]) {
    test(`${locale} ${width}: typed vendor search combines type pagination and clear`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(vendorPath(locale, 'many'));
        const input = page.locator('#vendor-service-search');
        await input.fill('Rental offering');
        await input.press('Enter');
        await expect(page).toHaveURL(/q=Rental/);
        await expect(input).toHaveValue('Rental offering');
        await expect(page.locator('[data-vendor-services-grid] article')).toHaveCount(15);
        await expect(page.locator('[data-vendor-filter-type="rental"]')).toHaveAttribute('href', /q=Rental/);
        await page.locator('[data-vendor-filter-type="rental"]').click();
        await expect(page.locator('[data-vendor-text-search] input[name="type"]')).toHaveValue('rental');
        await page.locator('a[rel=next]').click();
        await expect(page.locator('[data-vendor-services-grid] article')).toHaveCount(1);
        await page.reload();
        await expect(input).toHaveValue('Rental offering');
        await page.locator('[data-vendor-filter-type="sale"]').click();
        await expect(page.locator('[data-vendor-services-grid] article')).toHaveCount(0);
        await page.locator('.sf-vendor-search__clear').click();
        await expect(input).toHaveValue('');
        await expect(page.locator('[data-vendor-services-grid] article')).toHaveCount(15);
        await input.fill('خدمة');
        await page.locator('[data-vendor-text-search] button').click();
        await expect(page.locator('[data-vendor-services-grid] article')).toHaveCount(15);
        await expect(page.locator('[data-vendor-services-grid]')).not.toContainText('Foreign offering');
        await page.locator('.sf-vendor-search__clear').click();
        await expect(page.locator('[data-vendor-service-names] details')).toHaveCount(0);
        await page.screenshot({ path: path.join(evidence, `vendor-text-search-${locale}-${width}.png`), fullPage: true });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    });
}
