import { expect, test } from '@playwright/test';

for (const type of ['rental', 'sale', 'digital']) {
    test(`${type} import blocks submission while the selected file is uploading`, async ({ page }) => {
        let releaseUpload!: () => void;
        let markUploadStarted!: () => void;
        const uploadStarted = new Promise<void>((resolve) => { markUploadStarted = resolve; });
        const holdUpload = new Promise<void>((resolve) => { releaseUpload = resolve; });
        await page.route('**/livewire/upload-file**', async (route) => {
            markUploadStarted();
            await holdUpload;
            await route.continue();
        });
        await page.goto(`/admin/import-${type}-services-page?lang=en`);
        const form = page.locator('#catalog-import-form');
        const submit = form.locator('button[type="submit"]');
        try {
            await form.locator('input[type="file"]').setInputFiles({
                name: 'upload-progress.csv', mimeType: 'text/csv',
                buffer: Buffer.from('name_en,name_ar\nCake,كيك\n'),
            });
            await uploadStarted;
            await expect(submit).toBeDisabled();
            await expect(form).not.toContainText('The file field is required');
        } finally {
            releaseUpload();
        }
        await expect(submit).toBeEnabled();
        await expect(form).toContainText('upload-progress.csv');
    });
}
