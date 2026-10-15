import { expect, test } from '@playwright/test';

test.use({ storageState: { cookies: [], origins: [] } });

test('refreshes the disposable admin session without resetting fixtures', async ({ page }) => {
    await page.goto('/admin/login');
    await page.getByLabel('Email address', { exact: false }).fill('uat-admin@example.test');
    await page.locator('[wire\\:model="data.password"]').fill('UatAdminTest123!');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await page.waitForURL(/\/admin\/?$/, { waitUntil: 'domcontentloaded', timeout: 20_000 });
    await expect(page.locator('body')).not.toContainText('Authenticator or recovery code');
    await page.context().storageState({ path: 'tests/e2e/auth/.auth-state.json' });
});
