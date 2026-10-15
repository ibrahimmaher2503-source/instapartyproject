import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { fixtures } from './api-client';

for (const locale of ['en', 'ar']) {
  test(`${locale} planner rejects invalid dates and loads the selected governorate city`, async ({ app, browser }) => {
    const fixture = fixtures();
    await app.open(`/${locale}/wizard`);
    await browser.locator(`[data-planner-occasion-option="${fixture.occasion_code}"]`).click();
    const start = browser.locator('[name="event_starts_at"]');
    const end = browser.locator('[name="event_ends_at"]');
    const next = browser.locator('[data-planner-panel="0"] [data-planner-next]');
    await start.fill('2020-01-01T12:00');
    await end.fill('2020-01-01T13:00');
    await next.click();
    await expect(browser.locator('[data-planner-error]')).toBeVisible();
    await expect(browser.locator('[data-planner-panel="1"]')).not.toBeVisible();
    const date = new Date(Date.now() + 7 * 86_400_000).toISOString().slice(0, 10);
    await start.fill(`${date}T14:00`);
    await end.fill(`${date}T13:00`);
    await next.click();
    await expect(browser.locator('[data-planner-error]')).toBeVisible();
    await expect(browser.locator('[data-planner-panel="1"]')).not.toBeVisible();
    await end.fill(`${date}T16:00`);
    await next.click();
    await expect(browser.locator('[data-planner-panel="1"]')).toBeVisible();
    await browser.locator('[data-planner-governorate]').selectOption({ value: fixture.governorate });
    await expect(browser.locator(`[data-planner-city-select] option[value="${fixture.city}"]`)).toHaveCount(1);
    await browser.locator('[data-planner-city-select]').selectOption({ value: fixture.city });
    await browser.locator('[data-planner-panel="1"] [data-planner-next]').click();
    await expect(browser.locator('[data-planner-panel="2"]')).toBeVisible();
  });

  test(`${locale} sale search excludes prices above its maximum`, async ({ app, browser }) => {
    const name = fixtures().services.sale.names[locale];
    const query = `product_type=sale&q=${encodeURIComponent(name)}&price_min=100`;
    await app.open(`/${locale}/search?${query}`);
    await expect(browser.locator('.sf-catalog-card')).toHaveCount(1);
    await expect(browser.locator('.sf-catalog-card')).toContainText(name);
    await app.open(`/${locale}/search?${query}&price_max=120`);
    await expect(browser.locator('.sf-catalog-card')).toHaveCount(0);
  });
}
