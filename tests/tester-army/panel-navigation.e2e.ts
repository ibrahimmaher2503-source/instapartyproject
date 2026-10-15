import { test } from '@e2e-dev/web';
import { credentials, expect } from 'e2e';
import { fixtures } from './api-client';

for (const role of ['admin', 'vendor']) {
  test(`${role} resource indexes render with explicit fixture read permissions`, { timeout: 600_000 }, async ({ app, browser }) => {
    const base = role === 'admin' ? '/admin' : '/vendor-portal';
    const account = credentials.user(role);
    await app.open(`${base}/login`);
    await browser.locator('input[id="data.email"]').fill(account.username);
    await browser.locator('input[id="data.password"]').fill(account.password);
    await browser.locator('form button[type="submit"]').click();
    await expect(browser).toHaveURL(base, { timeout: 30_000 });
    const routes = fixtures().panel_routes[role];
    await expect(routes.length > 0).toBe(true);
    const failures: string[] = [];
    for (const route of routes) {
      try {
      await app.open(route);
      await expect(browser).toHaveURL(route);
      // This resource explicitly disables listing, including for administrators.
      if (route === '/admin/settlement-runs') {
        await expect(browser.locator('h1')).toContainText('403');
        continue;
      }
      await expect(browser.locator('h1')).toBeVisible();
      await expect(browser.locator('body')).not.toContainText('Server Error');
      await expect(browser.locator('.fi-ta')).toBeVisible();
      } catch (error) {
        failures.push(`${route}: ${error instanceof Error ? error.message : String(error)}`);
      }
    }
    await expect(failures).toEqual([]);
  });
}
