import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs/promises';
import path from 'node:path';

const baseUrl = process.env.SCREENSHOT_BASE_URL ?? 'http://127.0.0.1:8142';
const root = path.resolve(process.env.SCREENSHOT_ROOT ?? 'docs/qa/screenshots/2026-09-17');
const adminOut = path.join(root, 'admin-random');
const userOut = path.join(root, 'user-arabic');

const query = (statement) => execFileSync(
    'php',
    ['artisan', 'tinker', `--execute=${statement}`],
    { env: process.env },
).toString().trim();

const serviceIds = JSON.parse(query('echo App\\Modules\\Catalog\\Domain\\Models\\Service::query()->whereIn("slug",["joy-castle-10x10","deluxe-birthday-cake","animated-birthday-invite"])->pluck("public_id","slug")->toJson();'));
const vendorId = query('echo App\\Modules\\Identity\\Domain\\Models\\VendorProfile::query()->where("slug","joy-rentals-cairo")->value("public_id");');

const adminRoutes = [
    ['dashboard', '/admin'],
    ['withdrawals', '/admin/settlement-withdrawals'],
    ['activity-logs', '/admin/activitylogs'],
    ['imports', '/admin/excel-imports'],
    ['support-tickets', '/admin/support-tickets'],
    ['notification-templates', '/admin/notification-templates'],
    ['chat-moderation', '/admin/chat-moderation-flags'],
    ['roles', '/admin/shield/roles'],
    ['rental-services', '/admin/rental-services'],
];

const userRoutes = [
    ['home', '/ar'],
    ['join-us', '/ar/join-us'],
    ['faq', '/ar/p/faq'],
    ['terms', '/ar/p/terms'],
    ['privacy', '/ar/p/privacy'],
    ['about', '/ar/p/about'],
    ['contact', '/ar/p/contact'],
    ['search-populated', '/ar/search?q=birthday'],
    ['search-empty', '/ar/search?q=zzzz-no-match'],
    ['wizard', '/ar/wizard'],
    ['service-rental', `/ar/services/${serviceIds['joy-castle-10x10']}`],
    ['service-sale', `/ar/services/${serviceIds['deluxe-birthday-cake']}`],
    ['service-digital', `/ar/services/${serviceIds['animated-birthday-invite']}`],
    ['category-birthday', '/ar/c/birthday-general'],
    ['occasion-birthday', '/ar/o/birthday'],
    ['vendors', '/ar/vendors'],
    ['vendor-profile', `/ar/vendors/${vendorId}`],
    ['cart', '/ar/cart'],
    ['auth-login', '/ar/auth/login'],
    ['auth-register', '/ar/auth/register'],
    ['auth-forgot', '/ar/auth/forgot'],
    ['auth-reset', '/ar/auth/reset'],
];

await Promise.all([fs.mkdir(adminOut, { recursive: true }), fs.mkdir(userOut, { recursive: true })]);

const browser = await chromium.launch({ headless: true });
const manifest = [];

const capture = async (page, area, name, route, out) => {
    const errors = [];
    const onPageError = (error) => errors.push(error.message);
    const onConsole = (message) => message.type() === 'error' && errors.push(message.text());
    page.on('pageerror', onPageError);
    page.on('console', onConsole);

    let response;
    let navigationError = '';
    try {
        response = await page.goto(`${baseUrl}${route}`, { waitUntil: 'domcontentloaded', timeout: 45_000 });
        await page.waitForTimeout(700);
    } catch (error) {
        navigationError = String(error);
    }

    const file = `${String(manifest.filter((item) => item.area === area).length + 1).padStart(2, '0')}-${name}.png`;
    await page.screenshot({ path: path.join(out, file), fullPage: true });
    manifest.push({
        area,
        name,
        route,
        finalUrl: page.url(),
        status: response?.status() ?? 0,
        title: await page.title(),
        lang: await page.locator('html').getAttribute('lang'),
        dir: await page.locator('html').getAttribute('dir'),
        errors,
        navigationError,
        screenshot: file,
    });

    page.off('pageerror', onPageError);
    page.off('console', onConsole);
};

const adminContext = await browser.newContext({ viewport: { width: 1440, height: 1000 }, locale: 'en-GB' });
const adminPage = await adminContext.newPage();
await adminPage.goto(`${baseUrl}/admin/login`, { waitUntil: 'domcontentloaded', timeout: 45_000 });
await adminPage.locator('[wire\\:model="data.email"]').fill('admin@instaparty.local');
await adminPage.locator('[wire\\:model="data.password"]').fill('password');
await adminPage.getByRole('button', { name: /sign in/i }).click();
await adminPage.waitForURL(/\/admin$/, { timeout: 30_000 });
const arabicSwitch = adminPage.locator('button[wire\\:click*="changeLocale"]').filter({ hasText: 'العربية' }).first();
if (await arabicSwitch.count()) {
    await arabicSwitch.evaluate((button) => button.click());
    await adminPage.waitForTimeout(500);
}
for (const [name, route] of adminRoutes) {
    await capture(adminPage, 'admin', name, route, adminOut);
}
await adminContext.close();

const userContext = await browser.newContext({ viewport: { width: 1440, height: 1000 }, locale: 'ar-EG' });
const userPage = await userContext.newPage();
for (const [name, route] of userRoutes) {
    await capture(userPage, 'user-arabic', name, route, userOut);
}

const unique = Date.now().toString().slice(-9);
await userPage.goto(`${baseUrl}/ar/auth/register`, { waitUntil: 'domcontentloaded', timeout: 45_000 });
await userPage.locator('#name').fill('عميل معاينة الواجهة');
await userPage.locator('#phone_e164').fill(`+2011${unique.slice(-8)}`);
await userPage.locator('#password').fill('ScreenshotQa!2026');
await userPage.locator('#password_confirmation').fill('ScreenshotQa!2026');
await userPage.locator('input[name="accepted_terms"]').check();
await userPage.locator('form[action$="/auth/register"] button[type="submit"]').click();
await userPage.waitForURL(/\/ar\/auth\/verify$/, { timeout: 30_000 });
await capture(userPage, 'user-arabic', 'auth-verify', '/ar/auth/verify', userOut);
await userContext.close();

await browser.close();
await fs.writeFile(path.join(root, 'manifest.json'), JSON.stringify(manifest, null, 2));

const failures = manifest.filter((item) => item.status >= 400 || item.navigationError || item.errors.length > 0);
console.log(JSON.stringify({ root, adminOut, userOut, captures: manifest.length, failures }, null, 2));
if (failures.length > 0) process.exitCode = 1;
