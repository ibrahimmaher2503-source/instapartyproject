import { expect, test } from '@playwright/test';

test('occasion FilePond initializes before and after create-to-edit navigation', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const code = `qa-icon-${Date.now()}`;
    await page.goto('/admin/occasions/create?lang=en');
    const uploader = page.locator('.fi-fo-file-upload');
    await expect(uploader.locator('.filepond--root')).toBeVisible();
    await page.locator('input[id="data.code"]').fill(code);
    await page.locator('input[id="data.name.en"]').fill('QA uploader occasion');
    await page.getByRole('tab', { name: 'العربية', exact: true }).click();
    await page.locator('input[id="data.name.ar"]').fill('مناسبة اختبار رفع الصور');
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/occasions\/[A-Za-z0-9]+\/edit/);
    await expect(uploader.locator('.filepond--root')).toBeVisible();
    await page.reload();
    await expect(uploader.locator('.filepond--root')).toBeVisible();
    expect(errors).toEqual([]);
    await page.getByRole('button', { name: 'Delete', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/occasions(?:\?.*)?$/);
});
