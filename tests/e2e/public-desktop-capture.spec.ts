import { expect, test } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const base = 'http://127.0.0.1:8139';
const out = path.resolve('docs/qa/screenshots/public-desktop-2026-09-15');
const serviceIds = JSON.parse(execFileSync('php', ['artisan', 'tinker', '--execute=echo App\\Modules\\Catalog\\Domain\\Models\\Service::query()->whereIn("slug",["joy-castle-10x10","deluxe-birthday-cake","animated-birthday-invite"])->pluck("public_id","slug")->toJson();'], { env: { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: path.resolve('storage/e2e.sqlite') } }).toString());
const vendorId = execFileSync('php', ['artisan', 'tinker', '--execute=echo App\\Modules\\Identity\\Domain\\Models\\VendorProfile::query()->where("slug","joy-rentals-cairo")->value("public_id");'], { env: { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: path.resolve('storage/e2e.sqlite') } }).toString().trim();
const requiredServiceSlugs = ['joy-castle-10x10', 'deluxe-birthday-cake', 'animated-birthday-invite'];
const missingServiceSlugs = requiredServiceSlugs.filter(slug => !serviceIds[slug]);
if (missingServiceSlugs.length > 0) throw new Error(`Missing public service IDs in isolated SQLite: ${missingServiceSlugs.join(', ')}`);
if (!vendorId) throw new Error('Missing public vendor ID in isolated SQLite: joy-rentals-cairo');
const routes = [
  ['home', '/'], ['join-us', '/join-us'], ['faq', '/p/faq'], ['terms', '/p/terms'],
  ['privacy', '/p/privacy'], ['about', '/p/about'], ['contact', '/p/contact'],
  ['search-populated', '/search?q=birthday'], ['search-empty', '/search?q=zzzz-no-match'],
  ['wizard', '/wizard'], ['service-rental', `/services/${serviceIds['joy-castle-10x10']}`],
  ['service-sale', `/services/${serviceIds['deluxe-birthday-cake']}`], ['service-digital', `/services/${serviceIds['animated-birthday-invite']}`],
  ['category-birthday', '/c/birthday-general'], ['occasion-birthday', '/o/birthday'],
  ['vendors', '/vendors'], ['vendor-rental', `/vendors/${vendorId}`], ['cart', '/cart'],
  ['auth-login', '/auth/login'], ['auth-register', '/auth/register'], ['auth-forgot', '/auth/forgot'],
  ['auth-reset', '/auth/reset'], ['auth-verify', '/auth/verify'],
] as const;
const recordedRedirects = new Set(['category-birthday', 'occasion-birthday']);

test.describe.configure({ mode: 'serial' });
test.use({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1, storageState: { cookies: [], origins: [] } });

for (const locale of ['en', 'ar'] as const) {
  for (const [name, route] of routes) {
    test(`${locale}-${name}`, async ({ page }) => {
      const consoleErrors: string[] = [];
      const pageErrors: string[] = [];
      page.on('console', msg => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
      page.on('pageerror', error => pageErrors.push(error.message));
      let response = null;
      let navigationError = '';
      if (name === 'auth-verify') {
        const phone = `+201${String(Date.now()).slice(-8)}${locale === 'en' ? '1' : '2'}`;
        await page.goto(`${base}/${locale}/auth/register`, { waitUntil: 'domcontentloaded' });
        await page.locator('#name').fill(locale === 'en' ? 'Desktop QA Customer' : 'عميل اختبار الواجهة');
        await page.locator('#phone_e164').fill(phone);
        await page.locator('#password').fill('DesktopQa!2026');
        await page.locator('#password_confirmation').fill('DesktopQa!2026');
        await page.locator('input[name="accepted_terms"]').check();
        await page.locator('form[action$="/auth/register"] button[type="submit"]').click();
        await expect(page).toHaveURL(new RegExp(`/${locale}/auth/verify$`));
        await expect(page.locator('[data-otp-digit]')).toHaveCount(6);
      }
      try { response = await page.goto(`${base}/${locale}${route}`, { waitUntil: 'domcontentloaded', timeout: 30000 }); } catch (error) { navigationError = String(error); }
      await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => undefined);
      const finalUrl = page.url();
      const requestedUrl = `${base}/${locale}${route}`;
      const status = response?.status() ?? 0;
      const body = await page.locator('body').innerText().catch(() => '');
      const redirected = finalUrl !== requestedUrl;
      if (name === 'search-populated') {
        expect(body).toMatch(/Joy Castle 10x10|قلعة جوي 10x10/);
      }
      if (name === 'category-birthday') {
        expect(body).toMatch(/Joy Castle 10x10|قلعة جوي 10x10/);
      }
      if (name === 'occasion-birthday') {
        expect(body).toMatch(/Animated Birthday Invite|دعوة عيد ميلاد متحركة/);
        expect(body).toMatch(/Joy Castle 10x10|قلعة جوي 10x10/);
        expect(body).toMatch(/Deluxe Birthday Cake|كيك عيد ميلاد ديلوكس/);
      }
      if (name === 'home' || name === 'vendors') {
        expect(body).not.toMatch(/Runolfsdottir|Harris-Crooks|ipsam neque rerum|ut consequuntur magni/);
      }
      if (name === 'home') {
        await expect(page.locator('.sf-occasion-section .sf-taxonomy-grid > li')).toHaveCount(6);
        await expect(page.locator('.sf-category-section .sf-taxonomy-grid > li')).toHaveCount(8);
        await expect(page.locator('.sf-occasion-section .sf-text-link')).toBeVisible();
        await expect(page.locator('.sf-category-section .sf-text-link')).toBeVisible();
        await expect(page.locator('.sf-party-builder__form')).toBeVisible();
      }
      const failed = status >= 500 || Boolean(navigationError) || /(Whoops|Server Error|exception|stack trace)/i.test(body);
      await fs.mkdir(out, { recursive: true });
      const file = `${locale}-${name}.png`;
      await page.screenshot({ path: path.join(out, file), fullPage: true });
      const manifestPath = path.join(out, 'manifest.json');
      let manifest: any[] = [];
      try { manifest = JSON.parse(await fs.readFile(manifestPath, 'utf8')); } catch {}
      manifest = manifest.filter(item => !(item.name === name && item.locale === locale));
      const redirectRecorded = recordedRedirects.has(name) && redirected && status === 200;
      manifest.push({ name, locale, requestedUrl, finalUrl, redirected, status, title: await page.title(), consoleErrors, pageErrors, navigationError, outcome: redirectRecorded ? 'redirect-recorded' : failed ? 'failed' : 'pass', failed, screenshot: file });
      await fs.writeFile(manifestPath, JSON.stringify(manifest, null, 2));
    });
  }
}

test('ar-home-second-pass-composition', async ({ page }) => {
  await page.goto(`${base}/ar`, { waitUntil: 'networkidle' });

  await expect(page.locator('.sf-hero .sf-party-builder')).toBeVisible();
  await expect(page.locator('.sf-section-header')).toHaveCount(6);
  await expect(page.locator('.sf-occasion-section .sf-taxonomy-grid > li')).toHaveCount(6);
  await expect(page.locator('.sf-category-utility .sf-taxonomy-grid > li')).toHaveCount(8);
  await expect(page.locator('.sf-category-utility .sf-taxonomy-photo')).toHaveCount(0);
  await expect(page.locator('.sf-packages-panel')).toBeVisible();

  const visibleCounts = {
    services: await page.locator('.sf-services-section .sf-service-card').count(),
    packages: await page.locator('.sf-packages-section .sf-package-card').count(),
    vendors: await page.locator('.sf-featured-vendors .sf-vendor-card').count(),
  };
  expect(visibleCounts.services).toBeGreaterThan(0);
  expect(visibleCounts.services).toBeLessThanOrEqual(4);
  expect(visibleCounts.packages).toBeGreaterThan(0);
  expect(visibleCounts.packages).toBeLessThanOrEqual(3);
  expect(visibleCounts.vendors).toBeGreaterThan(0);
  expect(visibleCounts.vendors).toBeLessThanOrEqual(4);

  const packageImagesLoaded = await page.locator('.sf-package-card img').evaluateAll(images => images.every(image => {
    const element = image as HTMLImageElement;

    return element.getAttribute('src') !== '' && element.naturalWidth > 0;
  }));
  expect(packageImagesLoaded).toBe(true);

  const sectionOrder = await page.locator([
    '.sf-occasion-section',
    '.sf-category-section',
    '.sf-services-section',
    '.sf-packages-section',
    '.sf-featured-vendors',
    '.sf-how-section',
    '.sf-vendor-join-section',
  ].join(', ')).evaluateAll(elements => elements.map(element => element.className));

  expect(sectionOrder).toEqual([
    expect.stringContaining('sf-occasion-section'),
    expect.stringContaining('sf-category-section'),
    expect.stringContaining('sf-services-section'),
    expect.stringContaining('sf-packages-section'),
    expect.stringContaining('sf-featured-vendors'),
    expect.stringContaining('sf-how-section'),
    expect.stringContaining('sf-vendor-join-section'),
  ]);
});

test('ar-home-search-keeps-supported-category-filter', async ({ page }) => {
  await page.goto(`${base}/ar`, { waitUntil: 'networkidle' });

  await expect(page.locator('.sf-hero .sf-party-builder select[name="category"]')).toBeVisible();
});

for (const locale of ['en', 'ar'] as const) {
  test(`${locale}-home-second-pass-responsive`, async ({ page }) => {
    const responsiveOut = path.resolve('docs/qa/screenshots/homepage-second-pass-2026-09-17');
    await fs.mkdir(responsiveOut, { recursive: true });

    for (const width of [1440, 1280, 1024, 768, 390]) {
      await page.setViewportSize({ width, height: width === 390 ? 844 : 900 });
      await page.goto(`${base}/${locale}`, { waitUntil: 'networkidle' });
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
      expect(overflow, `${locale} homepage overflows at ${width}px`).toBe(false);
      await expect(page.locator('.sf-hero .sf-party-builder')).toBeVisible();
      await page.screenshot({ path: path.join(responsiveOut, `${locale}-${width}.png`), fullPage: true });
    }
  });
}

for (const locale of ['en', 'ar'] as const) {
  test(`${locale}-faq-responsive-interactions`, async ({ page }) => {
    await page.goto(`${base}/${locale}/p/faq`, { waitUntil: 'networkidle' });
    const items = page.locator('[data-faq-item]');
    await expect(items).toHaveCount(3);
    await items.first().locator('summary').click();
    await expect(items.first()).toHaveAttribute('open', '');

    const input = page.locator('[data-faq-search-input]');
    await input.fill(locale === 'ar' ? 'الدفع' : 'payment');
    await expect(page.locator('[data-faq-item]:visible')).toHaveCount(1);
    await page.locator('[data-faq-search-clear]').click();
    await expect(page.locator('[data-faq-item]:visible')).toHaveCount(3);
    await input.fill('zzzz-no-faq-match');
    await expect(page.locator('[data-faq-empty]')).toBeVisible();
    await page.locator('[data-faq-search-clear]').click();

    const responsiveOut = path.resolve('docs/qa/screenshots/faq-responsive-2026-09-16');
    await fs.mkdir(responsiveOut, { recursive: true });
    for (const width of [1440, 1280, 1024, 768, 390]) {
      await page.setViewportSize({ width, height: width === 390 ? 844 : 900 });
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
      expect(overflow, `${locale} FAQ overflows at ${width}px`).toBe(false);
      await page.screenshot({ path: path.join(responsiveOut, `${locale}-${width}.png`), fullPage: true });
    }
  });
}

test.afterAll(async () => {
  const manifestPath = path.join(out, 'manifest.json');
  try {
    const manifest = await fs.readFile(manifestPath, 'utf8');
    await fs.writeFile(manifestPath, JSON.stringify(JSON.parse(manifest), null, 2));
  } catch { /* no rows were produced */ }
  await fs.writeFile(path.join(out, 'README.md'), `# Public desktop storefront capture\n\nCaptured 2026-09-15 at 1440x1000, device scale factor 1, from isolated SQLite storage/e2e.sqlite on 127.0.0.1:8139.\n\nEach manifest row records final URL, HTTP status, title, console errors, page errors, and screenshot filename.\n`);

  const manifest = JSON.parse(await fs.readFile(manifestPath, 'utf8')) as Array<Record<string, any>>;
  const failures = manifest.filter(item => item.outcome !== 'redirect-recorded' && (
    item.status >= 400 || item.navigationError || item.pageErrors?.length > 0 || item.consoleErrors?.length > 0
  ));
  expect(failures, 'capture manifest contains unexpected page failures').toEqual([]);
});
