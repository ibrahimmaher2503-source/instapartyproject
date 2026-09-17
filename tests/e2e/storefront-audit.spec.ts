import { expect, test } from '@playwright/test';

test('Arabic and English storefronts keep direction, localization, and mobile width', async ({ page }) => {
    const browserErrors: string[] = [];
    page.on('pageerror', (error) => browserErrors.push(error.message));
    page.on('console', (message) => message.type() === 'error' && browserErrors.push(message.text()));

    let response = await page.goto('/ar/search');
    expect(response?.status()).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    expect(await page.locator('body').innerText()).not.toMatch(/\bstorefront\.[a-z0-9_.]+\b|Footer primary|Footer secondary/i);

    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);

    response = await page.goto('/en/search');
    expect(response?.status()).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    expect(browserErrors).toEqual([]);
});
