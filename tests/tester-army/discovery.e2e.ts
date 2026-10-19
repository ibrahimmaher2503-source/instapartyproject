import { readFileSync } from 'node:fs';
import { test } from '@e2e-dev/web';
import { expect } from 'e2e';

test('a guest discovers a service and reaches the planning sign-in gate in EN and AR', { timeout: 240_000 }, async ({ app, browser, agent }) => {
    const fixture = JSON.parse(readFileSync('.e2e/fixtures.json', 'utf8'));
    for (const locale of ['en', 'ar']) {
        const name = fixture.names[locale];
        await app.open(`/${locale}/search`);
        await expect(browser.locator('#catalog-search')).toBeVisible();
        await browser.locator('#catalog-search').fill(name);
        await browser.locator('#catalog-search').press('Enter');
        await expect(browser.locator('.sf-catalog-card')).toHaveCount(1);
        const detail = `/${locale}/services/${fixture.service}`;
        await agent.act('Open the service details for {name} from the search results.', { params: { name } });
        await expect(browser).toHaveURL(detail);
        await expect(browser.locator('h1')).toHaveText(name);
        await expect(browser.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
        await agent.act('Click the Add to cart button (أضف إلى السلة in Arabic) for this service. Stop when the sign-in page appears; do not sign in.');
        await expect(browser).toHaveURL(`/${locale}/auth/login`);
        await expect(browser.locator('form[data-auth-form] input[name="login"]')).toBeVisible();
        await expect(browser.locator('form[data-auth-form] input[name="password"]')).toBeVisible();
        await expect(browser.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
    }
});
