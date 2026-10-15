import { test } from '@e2e-dev/web';
import { expect } from 'e2e';

test('390px RTL menu closes and permits search without horizontal page overflow', async ({ app, browser }) => {
  await app.open('/ar');
  await expect(await browser.evaluate(() => window.innerWidth)).toBe(390);
  await expect(browser.locator('html')).toHaveAttribute('dir', 'rtl');
  await browser.locator('[data-storefront-nav-open]').click();
  await expect(browser.locator('[data-storefront-nav-dialog]')).toBeVisible();
  await browser.locator('[data-storefront-nav-close]').click();
  await expect(browser.locator('[data-storefront-nav-dialog]')).not.toBeVisible();
  await browser.locator('header a[aria-label="بحث"]').click();
  await expect(browser).toHaveURL('/ar/search');
  await expect(await browser.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  await browser.locator('[data-storefront-nav-open]').click();
  await browser.locator('[data-storefront-nav-dialog] a[hreflang="en"]').click();
  await expect(browser).toHaveURL('/en/search');
  await expect(browser.locator('html')).toHaveAttribute('dir', 'ltr');
  await expect(await browser.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});
