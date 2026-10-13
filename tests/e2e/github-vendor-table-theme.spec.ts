import { expect, test } from '@playwright/test';

test.describe('GitHub #92 and #107 vendor table themes', () => {
    test.skip(!process.env.QA_VENDOR_EMAIL || !process.env.QA_VENDOR_PASSWORD, 'Requires an approved disposable QA vendor and the existing QA server.');

    for (const width of [390, 1440]) {
        test(`wallet statement and recent bookings remain readable at ${width}px`, async ({ page }) => {
            await page.setViewportSize({ width, height: 900 });
            await page.goto('/vendor-portal/login');
            await page.locator('input[type="email"]').fill(process.env.QA_VENDOR_EMAIL!);
            await page.locator('input[type="password"]').fill(process.env.QA_VENDOR_PASSWORD!);
            await page.locator('button[type="submit"]').click();
            await expect(page).toHaveURL(/\/vendor-portal\/?$/);

            for (const path of ['/vendor-portal', '/vendor-portal/vendor-statement-page']) {
                await page.goto(path);
                for (const theme of ['light', 'dark']) {
                    await page.locator('.fi-user-menu button').first().click();
                    await page.locator('.fi-theme-switcher-btn').nth(theme === 'light' ? 0 : 1).click();
                    await expect(page.locator('html')).toHaveClass(theme === 'dark' ? /dark/ : /^(?!.*\bdark\b).*$/);
                    const header = page.locator('.fi-ta-header').first();
                    await expect(header).toBeVisible();
                    const contrast = await header.evaluate(element => {
                        const heading = element.querySelector('.fi-ta-header-heading')!;
                        const context = document.createElement('canvas').getContext('2d')!;
                        const luminance = (color: string) => {
                            context.clearRect(0, 0, 1, 1);
                            context.fillStyle = color;
                            context.fillRect(0, 0, 1, 1);
                            const channels = [...context.getImageData(0, 0, 1, 1).data].slice(0, 3).map(value => {
                                const channel = value / 255;
                                return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
                            });
                            return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
                        };
                        const background = luminance(getComputedStyle(element).backgroundColor);
                        const text = luminance(getComputedStyle(heading).color);
                        return (Math.max(background, text) + 0.05) / (Math.min(background, text) + 0.05);
                    });
                    expect(contrast).toBeGreaterThanOrEqual(4.5);
                }
            }
        });
    }
});
