import { expect, test } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const paths = [
    '/admin',
    '/admin/occasions/create',
    '/admin/categories/create',
    '/admin/loyalty-programs/create',
    '/admin/rental-services/create',
    '/admin/package-recommendations/create',
    '/admin/payments',
    '/admin/booking-modifications',
    '/admin/reconciliation-runs',
    '/admin/campaigns',
    '/admin/notification-dispatches',
    '/admin/review-moderation-page',
];

test('admin shell uses the compact persistent sidebar and responsive drawer', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/admin/rental-services');

    const sidebar = page.locator('.fi-sidebar');
    await expect(sidebar).toBeVisible();
    expect(Math.round((await sidebar.boundingBox())?.width ?? 0)).toBe(264);

    const activeItem = page.locator('.fi-sidebar-item-active').filter({ has: page.locator('a[href*="/admin/rental-services"]') }).first();
    await expect(activeItem).toBeVisible();
    await expect(activeItem.locator('a, button').first()).toHaveAttribute('aria-current', 'page');

    await page.getByRole('button', { name: /collapse sidebar/i }).click();
    await expect.poll(async () => Math.round((await sidebar.boundingBox())?.width ?? 0)).toBe(72);
    await page.reload();
    await expect.poll(async () => Math.round((await sidebar.boundingBox())?.width ?? 0)).toBe(72);

    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload();
    await expect(sidebar).not.toHaveClass(/fi-sidebar-open/);
    await expect(page.locator('.fi-sidebar-close-overlay')).toBeHidden();
    await page.locator('.fi-topbar-open-sidebar-btn').click();
    await expect(sidebar).toHaveClass(/fi-sidebar-open/);
    expect(Math.round((await sidebar.boundingBox())?.width ?? 0)).toBeLessThanOrEqual(320);
});

for (const locale of ['en', 'ar'] as const) {
    test(`${locale} admin shell mirrors cleanly across target widths`, async ({ page }) => {
        const output = path.resolve('docs/qa/screenshots/admin-second-pass-2026-09-17');
        await fs.mkdir(output, { recursive: true });

        for (const width of [1440, 1280, 1024, 768, 390]) {
            await page.setViewportSize({ width, height: width === 390 ? 844 : 900 });
            await page.goto(`/admin/rental-services?lang=${locale}`, { waitUntil: 'networkidle' });
            await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);

            if (width >= 1024) {
                const box = await page.locator('.fi-sidebar').boundingBox();
                expect(locale === 'ar' ? box?.x : Math.round(box?.x ?? -1)).toBe(locale === 'ar' ? (width - (box?.width ?? 0)) : 0);
            }

            await page.screenshot({ path: path.join(output, `${locale}-${width}.png`), fullPage: true });
        }
    });
}

test('admin acceptance screens render without raw translations or server errors', async ({ page }) => {
    const browserErrors: string[] = [];
    page.on('pageerror', (error) => browserErrors.push(error.message));
    page.on('console', (message) => message.type() === 'error' && browserErrors.push(message.text()));

    for (const path of paths) {
        const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
        const body = await page.locator('body').innerText();

        expect(response?.status(), path).toBe(200);
        expect(body).not.toMatch(/\b(?:admin|booking|catalog|loyalty|payments|settlement)\.[a-z0-9_.]+\b/i);
        expect(body).not.toMatch(/\?{4,}|Internal Server Error|Method Not Allowed/i);
    }

    expect(browserErrors).toEqual([]);
});

test('moderation queue exposes waiting time and oldest-first context', async ({ page }) => {
    await page.goto('/admin/review-moderation-page');
    const body = await page.locator('body').innerText();

    expect(body).toMatch(/Waiting Time|مدة الانتظار/);
    expect(body).toMatch(/Service|الخدمة/);
    expect(body.indexOf('E2E oldest pending review')).toBeLessThan(body.indexOf('E2E newer pending review'));
});

test('moderation and dispute screens localize financial states', async ({ page }) => {
    const reviewResponse = await page.goto('/admin/review-moderation-page?lang=ar');
    const reviewBody = await page.locator('body').innerText();

    expect(reviewResponse?.status()).toBe(200);
    expect(reviewBody).toMatch(/إشراف التقييمات/);
    expect(reviewBody).toMatch(/قيد المراجعة/);
    expect(reviewBody.split('\n').map((line) => line.trim())).not.toContain('pending');

    const disputeResponse = await page.goto('/admin/dispute-oversight-page?lang=ar');
    const disputeBody = await page.locator('body').innerText();

    expect(disputeResponse?.status()).toBe(200);
    expect(disputeBody).toMatch(/مراقبة النزاعات/);
    expect(disputeBody).toMatch(/قيد الانتظار/);
    expect(disputeBody).toMatch(/طلب العميل/);
    expect(disputeBody).toContain('طلب استرداد للاختبار');
    expect(disputeBody.split('\n').map((line) => line.trim())).not.toContain('customer_request');
});

test('chat moderation queue exposes waiting time and escalation context', async ({ page }) => {
    const response = await page.goto('/admin/chat-moderation-flags');
    const body = await page.locator('body').innerText();

    expect(response?.status()).toBe(200);
    expect(body).toMatch(/Waiting Time|مدة الانتظار/);
});

test('subscription and advertising analytics render measurable states', async ({ page }) => {
    for (const [path, metric] of [
        ['/admin/subscription-analytics-page', /Revenue by Month|الإيرادات شهريًا/],
        ['/admin/advertising-analytics-page', /Ad Revenue by Month|إيرادات الإعلانات شهريًا/],
    ] as const) {
        const response = await page.goto(path);
        expect(response?.status(), path).toBe(200);
        await expect(page.getByText(metric).first()).toBeVisible();
    }
});

test('locale and responsive layout remain usable', async ({ page }) => {
    await page.goto('/admin/rental-services/create');
    const arabicSwitch = page.locator('button[wire\\:click*="changeLocale"]').filter({ hasText: 'العربية' }).first();
    await arabicSwitch.evaluate((button) => button.click());
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');

    for (const width of [1440, 1280, 1024, 768, 390]) {
        await page.setViewportSize({ width, height: 844 });
        const response = await page.goto('/admin/rental-services');

        expect(response?.status(), `${width}px`).toBe(200);
        expect(await page.evaluate(() => document.documentElement.scrollWidth), `${width}px`).toBeLessThanOrEqual(width);
    }
});

test('notification template editor renders its live preview', async ({ page }) => {
    const response = await page.goto('/admin/notification-templates/create');

    expect(response?.status()).toBe(200);
    await expect(page.getByText(/Preview|المعاينة/).first()).toBeVisible();

    const body = page.locator('textarea').nth(1);
    await body.fill('Booking {{booking_id}}');
    await body.blur();

    await expect(page.getByText('Booking {{booking_id}}', { exact: true })).toBeVisible();
});

test('search telemetry labels filter-only activity and formats saved filters', async ({ page }) => {
    await page.goto('/admin/search-logs');
    await expect(page.getByText(/Filters only|عوامل تصفية فقط/).first()).toBeVisible();

    await page.goto('/admin/saved-searches');
    const body = await page.locator('body').innerText();

    expect(body).toMatch(/Rental|تأجير/);
    expect(body).toMatch(/Maximum price|الحد الأقصى للسعر/);
    expect(body).not.toContain('["rental"]');
});

test('vendor role editor hydrates its saved permissions', async ({ page }) => {
    const response = await page.goto('/admin/shield/roles/vendor/edit');

    expect(response?.status()).toBe(200);
    await expect(page.locator('input[type="checkbox"]:checked')).toHaveCount(2);
    await page.getByRole('button', { name: /save|حفظ/i }).click();
    await page.reload();
    await expect(page.locator('input[type="checkbox"]:checked')).toHaveCount(2);

    const numericResponse = await page.goto('/admin/shield/roles/5/edit');
    expect(numericResponse?.status()).toBe(404);
});

test('inventory reservations show readable identities and expired-hold context', async ({ page }) => {
    const response = await page.goto('/admin/service-inventory-reservations');
    const body = await page.locator('body').innerText();

    expect(response?.status()).toBe(200);
    expect(body).toMatch(/E2E Readable Inventory Service|خدمة مخزون واضحة/);
    expect(body).toContain('E2E Inventory Customer');
    expect(body).toMatch(/Not applicable|غير منطبق/);
    expect(body).toMatch(/Overdue by|متأخر منذ/);
});

test('vendor document review exposes context before editing', async ({ page }) => {
    const listResponse = await page.goto('/admin/vendor-document-filaments');
    expect(listResponse?.status()).toBe(200);

    await page.getByText(/E2E Document Vendor|مورد مستند الاختبار/).first().click();
    await expect(page).toHaveURL(/\/admin\/vendor-document-filaments\/[0-9A-HJKMNP-TV-Z]{26}$/);

    const viewBody = await page.locator('body').innerText();
    expect(viewBody).toMatch(/E2E Document Vendor|مورد مستند الاختبار/);
    expect(viewBody).toContain('هوية-المورد.pdf');
    expect(viewBody).toMatch(/Document context|بيانات المستند والمراجعة/);
    await expect(page.getByRole('button', { name: /Open document|فتح المستند/i })).toBeVisible();
});

test('import detail exposes bilingual failure context', async ({ page }) => {
    const response = await page.goto('/admin/excel-imports');
    const body = await page.locator('body').innerText();

    expect(response?.status()).toBe(200);
    expect(body).toContain('E2E import failure.xlsx');

    await page.getByText('E2E import failure.xlsx', { exact: true }).first().click();
    await expect(page).toHaveURL(/\/admin\/excel-imports\/[0-9A-HJKMNP-TV-Z]{26}$/);

    const viewBody = await page.locator('body').innerText();
    expect(viewBody).toContain('E2E import failure.xlsx');
    expect(viewBody).toMatch(/Error Rows|صفوف الأخطاء/);
    expect(viewBody).toContain('1');
});

test('support ticket detail exposes the existing request context', async ({ page }) => {
    const response = await page.goto('/admin/support-tickets');
    const body = await page.locator('body').innerText();

    expect(response?.status()).toBe(200);
    expect(body).toContain('E2E support ticket');

    await page.getByText('E2E support ticket', { exact: true }).first().click();
    await expect(page).toHaveURL(/\/admin\/support-tickets\/[0-9A-HJKMNP-TV-Z]{26}$/);

    const viewBody = await page.locator('body').innerText();
    expect(viewBody).toContain('E2E support ticket');
    expect(viewBody).toContain('E2E support request context.');
    expect(viewBody).toMatch(/Assignee|Unassigned|المسؤول|غير مسند/);
});

test('webhook log view exposes safe signature and processing states', async ({ page }) => {
    const response = await page.goto('/admin/gateway-webhook-logs');
    const body = await page.locator('body').innerText();

    expect(response?.status()).toBe(200);
    expect(body).toMatch(/Invalid|غير صحيح/);
    expect(body).toMatch(/Duplicate|مكرر/);
    expect(body).not.toContain('4111111111111111');
    expect(body).not.toContain('customer@example.test');
});

test('withdrawal audit detail renders a rejected record safely', async ({ page }) => {
    const response = await page.goto('/admin/settlement-withdrawal-audit');
    expect(response?.status()).toBe(200);

    await page.getByText(/E2E Document Vendor|مورد مستند الاختبار/).first().click();
    await expect(page).toHaveURL(/\/admin\/settlement-withdrawal-audit\/[0-9A-HJKMNP-TV-Z]{26}$/);

    const body = await page.locator('body').innerText();
    expect(body).toMatch(/Rejected|مرفوض/);
    expect(body).not.toContain('EG380019000500000000263180002');
    expect(body).not.toContain('nested value');
});
