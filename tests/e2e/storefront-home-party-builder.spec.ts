import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const environment = {
    ...process.env,
    APP_ENV: 'testing',
    APP_DEBUG: 'true',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.resolve('storage/e2e.sqlite'),
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync',
};

test('homepage builder submits dependent geography and date-time without category', async ({ page }) => {
    test.setTimeout(120_000);
    execFileSync('php', [
        'artisan',
        'tinker',
        `--execute=$country=App\\Modules\\Geography\\Domain\\Models\\Country::query()->firstOrFail(); $governorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::factory()->create(['country_id'=>$country->id,'code'=>'EG-E2E-BUILDER','name'=>['en'=>'Builder Governorate','ar'=>'محافظة المخطط'],'is_active'=>true]); $region=App\\Modules\\Geography\\Domain\\Models\\Region::factory()->create(['governorate_id'=>$governorate->id,'name'=>['en'=>'Builder Region','ar'=>'منطقة المخطط'],'is_active'=>true]); App\\Modules\\Geography\\Domain\\Models\\City::factory()->create(['region_id'=>$region->id,'governorate_id'=>$governorate->id,'name'=>['en'=>'Builder City','ar'=>'مدينة المخطط'],'is_active'=>true]); App\\Modules\\Catalog\\Domain\\Models\\Occasion::factory()->create(['code'=>'e2e-home-builder','name'=>['en'=>'Builder Occasion','ar'=>'مناسبة المخطط'],'is_active'=>true]);`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });

    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto('/en');
    const form = page.locator('.sf-party-builder__form');
    await expect(form.locator('[name="category"]')).toHaveCount(0);
    await expect(form.locator('[name="city_public_id"]')).toBeDisabled();
    const fieldOrder = await form.locator('select, input').evaluateAll((fields) => fields.map((field) => field.getAttribute('name')));
    expect(fieldOrder.slice(0, 4)).toEqual(['occasion', 'governorate_public_id', 'city_public_id', 'event_starts_at']);

    await form.locator('[name="occasion"]').selectOption('e2e-home-builder');
    await form.locator('[name="governorate_public_id"]').selectOption({ label: 'Builder Governorate' });
    const city = form.locator('[name="city_public_id"]');
    await expect(city).toBeEnabled();
    await expect(city.locator('option', { hasText: 'Builder City' })).toHaveCount(1);
    await city.selectOption({ label: 'Builder City' });
    const startsAt = new Date(Date.now() + 86_400_000).toISOString().slice(0, 16);
    await form.locator('[name="event_starts_at"]').fill(startsAt);
    await form.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/en\/search\?.*occasion=e2e-home-builder/);
    await expect(page).toHaveURL(/governorate_public_id=/);
    await expect(page).toHaveURL(/city_public_id=/);
    await expect(page).toHaveURL(/event_starts_at=/);

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/ar');
    const arabicForm = page.locator('.sf-party-builder__form');
    await expect(arabicForm.locator('[name="category"]')).toHaveCount(0);
    await arabicForm.locator('[name="governorate_public_id"]').selectOption({ label: 'محافظة المخطط' });
    await expect(arabicForm.locator('[name="city_public_id"] option', { hasText: 'مدينة المخطط' })).toHaveCount(1);
});
