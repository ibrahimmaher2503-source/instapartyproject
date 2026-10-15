import { test, expect, Page } from '@playwright/test';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const evidence = path.resolve('storage/app/qa/vendor-document-types-2026-10-14/screenshots');
let fixtureDirectory: string;
let pdf: string;
const fixtures = () => JSON.parse(fs.readFileSync('storage/app/vendor-document-types-qa-fixtures.json', 'utf8'));
const urls = () => JSON.parse(fs.readFileSync('storage/app/vendor-document-types-qa-urls.json', 'utf8'));
test.describe.configure({ mode: 'serial' });

test.beforeAll(() => {
    fixtureDirectory = fs.mkdtempSync(path.join(os.tmpdir(), 'instaparty-vendor-document-types-'));
    pdf = path.join(fixtureDirectory, 'vendor-document.pdf');
    fs.writeFileSync(pdf, '%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 1 1]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF');
});

test.afterAll(() => {
    if (fixtureDirectory) fs.rmSync(fixtureDirectory, { recursive: true, force: true });
});

async function login(page: Page, panel: 'admin' | 'vendor', account: string) {
    const panelPath = panel === 'vendor' ? 'vendor-portal' : 'admin';
    await page.goto(`/${panelPath}/login?lang=en`);
    await page.locator('[wire\\:model="data.email"]').fill(`documents-${account}@example.test`);
    await page.locator('[wire\\:model="data.password"]').fill('DocumentTypes123!');
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(new RegExp(`/${panelPath}(?:\\?|$)`), { timeout: 60_000 });
}

async function editRequired(page: Page, name: string, required: boolean) {
    await page.goto('/admin/vendor-document-types?lang=en');
    await page.getByRole('row').filter({ hasText: name }).getByRole('button', { name: 'Edit', exact: true }).click();
    const dialog = page.getByRole('dialog').filter({ has: page.getByLabel('Document name (English)') });
    for (const businessType of ['Individual / No company', 'Company', 'Establishment']) {
        await dialog.getByLabel(businessType, { exact: true }).uncheck();
    }
    if (required) await dialog.getByLabel('Individual / No company', { exact: true }).check();
    await dialog.getByRole('button', { name: 'Save changes', exact: true }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByRole('row').filter({ hasText: name })).toContainText(required ? 'Individual / No company' : 'Optional for everyone');
}

async function upload(page: Page, name: string) {
    await page.goto(urls().documents + '?lang=en');
    await page.getByRole('button', { name: 'Upload Document', exact: true }).click();
    const dialog = page.getByRole('dialog').filter({ has: page.locator('input[type="file"]') });
    await dialog.getByRole('combobox').selectOption({ label: name });
    await dialog.locator('input[type="file"]').setInputFiles(pdf);
    await expect(dialog.locator('.filepond--item')).toHaveAttribute('data-filepond-item-state', 'processing-complete');
    await dialog.getByRole('button', { name: 'Submit', exact: true }).click();
    await expect(page.getByRole('table')).toContainText(name, { timeout: 15_000 });
    await expect(dialog).toBeHidden();
}

async function approveProfile(page: Page, id: string) {
    await page.goto(urls().approval.replace(fixtures().one, id) + '?lang=en');
    const approve = page.getByRole('button', { name: 'Approve Profile', exact: true });
    await expect(approve).toBeEnabled();
    await approve.click();
    await page.getByRole('dialog').getByRole('button', { name: 'Confirm', exact: true }).click();
    await expect(page).toHaveURL(/\/vendor-approval-queue(?:\?lang=en)?$/);
    await page.goto(urls().profile.replace(fixtures().one, id) + '?lang=en');
    for (const label of ['Approve for Rental', 'Approve for Sale', 'Approve for Digital']) {
        await page.getByRole('button', { name: label, exact: true }).click();
        await page.getByRole('dialog').getByRole('button', { name: 'Confirm', exact: true }).click();
        await expect(page.getByRole('button', { name: label, exact: true })).toBeHidden();
    }
}

test('admin manages types, vendor uploads a custom type, and history survives removal', async ({ page, browser }) => {
    fs.mkdirSync(evidence, { recursive: true });
    await login(page, 'admin', 'admin');
    await page.goto('/admin/vendor-document-types?lang=en');
    await page.getByRole('button', { name: 'New document type', exact: false }).click();
    const dialog = page.getByRole('dialog').filter({ has: page.getByLabel('Document name (English)') });
    await dialog.getByLabel('Document name (English)').fill('Business licence');
    await dialog.getByLabel('Document name (Arabic)').fill('رخصة النشاط');
    await dialog.getByRole('button', { name: 'Create', exact: true }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByRole('table')).toContainText('Business licence');
    await page.screenshot({ path: path.join(evidence, 'admin-document-types-en.png'), fullPage: true });
    await page.goto('/admin/vendor-document-types?lang=ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.getByRole('table')).toContainText('رخصة النشاط');
    await page.screenshot({ path: path.join(evidence, 'admin-document-types-ar.png'), fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    const sidebarOverlay = page.locator('.fi-sidebar-close-overlay');
    if (await sidebarOverlay.isVisible()) await sidebarOverlay.click();
    await expect(sidebarOverlay).toBeHidden();
    await page.screenshot({ path: path.join(evidence, 'admin-document-types-ar-mobile.png'), fullPage: true });
    await page.setViewportSize({ width: 1440, height: 1000 });

    const context = await browser.newContext();
    const vendorPage = await context.newPage();
    await login(vendorPage, 'vendor', 'custom');
    await upload(vendorPage, 'Business licence');
    await vendorPage.screenshot({ path: path.join(evidence, 'vendor-custom-upload.png'), fullPage: true });
    await page.goto(urls().admin_documents + '?lang=en');
    const row = page.getByRole('row').filter({ hasText: 'Documents custom' });
    await row.getByRole('button', { name: 'Approve', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Confirm', exact: true }).click();
    await expect(row).toContainText('Approved');
    await page.goto('/admin/vendor-document-types?lang=en');
    await page.getByRole('row').filter({ hasText: 'Business licence' }).getByRole('button', { name: 'Delete', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(page.getByRole('table')).not.toContainText('Business licence');
    await vendorPage.reload();
    await expect(vendorPage.getByRole('table')).toContainText('Business licence');
    await vendorPage.getByRole('button', { name: 'Upload Document', exact: true }).click();
    await expect(vendorPage.getByRole('combobox').getByRole('option', { name: 'Business licence', exact: true })).toHaveCount(0);
    await context.close();
});

test('one required uploaded document enables approval and all three service grants', async ({ page, browser }) => {
    await login(page, 'admin', 'admin');
    for (const name of ['Commercial Register', 'Tax Card', 'National ID', 'IBAN Proof', 'Other']) await editRequired(page, name, name === 'National ID');
    const context = await browser.newContext();
    const vendorPage = await context.newPage();
    await login(vendorPage, 'vendor', 'one');
    await upload(vendorPage, 'National ID');
    await page.goto(urls().admin_documents + '?lang=en');
    const row = page.getByRole('row').filter({ hasText: 'Documents one' });
    await row.getByRole('button', { name: 'Approve', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Confirm', exact: true }).click();
    await expect(row).toContainText('Approved');
    await approveProfile(page, fixtures().one);
    await page.screenshot({ path: path.join(evidence, 'approved-one-document.png'), fullPage: true });
    const servicesResponse = await vendorPage.goto(urls().services + '?lang=en');
    expect(servicesResponse?.status()).toBe(200);
    await expect(vendorPage).not.toHaveURL(/login/);
    await expect(vendorPage.locator('body')).not.toContainText('403');
    await context.close();
});

test('zero required documents enables approval without uploads', async ({ page }) => {
    await login(page, 'admin', 'admin');
    await editRequired(page, 'National ID', false);
    await approveProfile(page, fixtures().zero);
    await page.screenshot({ path: path.join(evidence, 'approved-no-documents.png'), fullPage: true });
});
