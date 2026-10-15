import { readFileSync } from 'node:fs';
import { test } from '@e2e-dev/web';
import { credentials, expect } from 'e2e';

const fixture = () => JSON.parse(readFileSync('.e2e/fixtures.json', 'utf8'));

for (const locale of ['en', 'ar']) {
  for (const route of ['', '/search', '/vendors', '/join-us', '/auth/login', '/auth/register', '/wizard', '/cart']) {
    test(`public surface ${locale}${route || '/'} renders`, async ({ app, browser }) => {
      const url = `/${locale}${route}`;
      await app.open(url);
      await expect(browser).toHaveURL(url);
      await expect(browser.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
      await expect(browser.locator('h1')).toBeVisible();
      await expect(browser.locator('body')).not.toContainText('Server Error');
    });
  }
  for (const type of ['rental', 'sale', 'digital']) {
    test(`${type} service details ${locale} preserve identity and localized title`, async ({ app, browser }) => {
      const service = fixture().services[type];
      const url = `/${locale}/services/${service.id}`;
      await app.open(url);
      await expect(browser).toHaveURL(url);
      await expect(browser.locator('h1')).toHaveText(service.names[locale]);
      await expect(browser.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
    });
  }
  test(`customer signs in and opens all account sections ${locale}`, { timeout: 180_000 }, async ({ app, browser, agent }) => {
    const customer = credentials.user('customer');
    await app.open(`/${locale}/auth/login`);
    await browser.locator('input[name="login"]').fill(customer.username);
    await browser.locator('input[name="password"]').fill(customer.password);
    await browser.locator('form[data-auth-form] button[type="submit"]').click();
    await expect(browser).toHaveURL(`/${locale}`);
    await agent.act('Open My account (حسابي) from the navigation.');
    await expect(browser).toHaveURL(`/${locale}/account`);
    for (const section of ['bookings', 'payments', 'plans', 'favorites', 'notifications', 'reviews', 'profile', 'security']) {
      const url = `/${locale}/account/${section}`;
      await app.open(url);
      await expect(browser).toHaveURL(url);
      await expect(browser.locator('h1')).toBeVisible();
      await expect(browser.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
      await expect(browser.locator('body')).not.toContainText('Server Error');
    }
  });
}

for (const role of ['admin', 'vendor']) {
  const path = role === 'admin' ? '/admin' : '/vendor-portal';
  test(`${role} authenticates into its panel`, async ({ app, browser }) => {
    const account = credentials.user(role);
    await app.open(`${path}/login`);
    await browser.locator('input[id="data.email"]').fill(account.username);
    await browser.locator('input[id="data.password"]').fill(account.password);
    await browser.locator('form button[type="submit"]').click();
    await expect(browser).toHaveURL(path, { timeout: 30_000 });
    await expect(browser.locator('h1')).toBeVisible();
    await expect(browser.locator('body')).not.toContainText('Server Error');
  });
}

for (const route of ['/api/v1/customer/governorates', '/api/v1/customer/cities', '/api/v1/customer/now',
  '/api/v1/customer/services', '/api/v1/customer/vendors', '/api/v1/theme/branding',
  '/api/v1/theme/tokens', '/api/v1/theme/menus?slot=header', '/api/v1/feature-flags/public', '/api/v1/cms/homepage',
  '/api/v1/customer/faq/categories', '/api/v1/customer/faq/search?q=party']) {
  test(`public API ${route} returns a JSON contract`, async ({ app }) => {
    const response = await fetch(new URL(route, app.baseUrl), { headers: { Accept: 'application/json' } });
    await expect(response.status).toBe(200);
    await expect(response.headers.get('content-type')?.includes('application/json')).toBe(true);
    const body = await response.json();
    await expect(typeof body).toBe('object');
    await expect(body !== null).toBe(true);
  });
}

for (const route of ['/api/v1/customer/profile', '/api/v1/customer/bookings', '/api/v1/customer/wishlist',
  '/api/v1/customer/addresses']) {
  test(`guest is rejected by ${route}`, async ({ app }) => {
    const response = await fetch(new URL(route, app.baseUrl), { headers: { Accept: 'application/json' } });
    await expect(response.status).toBe(401);
  });
}
