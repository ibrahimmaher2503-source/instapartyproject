import { test } from '@e2e-dev/web';
import { credentials, expect } from 'e2e';
import { readFileSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { apiClient, fixtures } from '../api-client';

const fixture = () => JSON.parse(readFileSync('.e2e/system-audit/benchmark-fixture.json', 'utf8'));
const state = () => JSON.parse(execFileSync('python3', ['tests/tester-army/benchmark/state.py'], { encoding: 'utf8' }));
const evidence = (id: string, data: unknown) => writeFileSync(`.e2e/system-audit/benchmark/${id}.json`, JSON.stringify(data, null, 2));

async function signIn(app: any, browser: any, role: string) {
  const account = credentials.user(role);
  const admin = role === 'admin';
  await app.open(admin ? '/admin/login' : '/en/auth/login');
  await browser.locator(admin ? 'input[id="data.email"]' : 'input[name="login"]').fill(account.username);
  await browser.locator(admin ? 'input[id="data.password"]' : 'input[name="password"]').fill(account.password);
  await browser.locator('form button[type="submit"]').click();
  await expect(browser).toHaveURL(admin ? '/admin' : '/en', { timeout: 30000 });
}

test('B01 TA-SYS-001 published wishlist availability matches its persisted service', { tags: ['known-defect-benchmark'] }, async ({ app, browser }) => {
  await signIn(app, browser, 'customer');
  const f = fixtures();
  const call = await apiClient(app.baseUrl!, 'customer');
  const added = await call('POST', '/api/v1/customer/wishlist/items', { service_id: f.services.sale.id });
  await app.open('/en/account/favorites');
  await expect(browser.locator('article.sf-account-panel h2')).toHaveText(f.services.sale.names.en);
  await app.open(`/en/services/${f.services.sale.id}`);
  await expect(browser.locator('h1')).toHaveText(f.services.sale.names.en);
  const persisted = state();
  evidence('B01', { status: added.status, response: added.body, persisted });
  await expect(persisted.wishlist_count).toBe(1);
  await expect(persisted.saved_service.status).toBe('published');
  await expect(added.status).toBe(201);
  await expect(added.body.data.is_available).toBe(true);
});

test('B02 TA-SYS-002 unknown chat report must fail with a client error and no report row', { tags: ['known-defect-benchmark'] }, async ({ app }) => {
  const before = state();
  const call = await apiClient(app.baseUrl!, 'customer');
  const result = await call('POST', '/api/v1/customer/reports', { reportable_type: 'chat_thread', reportable_id: '01ARZ3NDEKTSV4RRFFQ69G5FAV', reason: 'other', details: 'Disposable benchmark nonexistent target.' });
  const after = state();
  evidence('B02', { status: result.status, report_count_before: before.report_count, report_count_after: after.report_count });
  await expect(after.report_count).toBe(before.report_count);
  await expect(result.status).toBeGreaterThanOrEqual(400);
  await expect(result.status).toBeLessThan(500);
});

test('B03 VTSE-001 foreign service resubmit rejects ownership changes without persistence or lifecycle effects', { tags: ['known-defect-benchmark'] }, async ({ app }) => {
  const f = fixture();
  const before = state();
  const call = await apiClient(app.baseUrl!, 'vendor');
  await expect((await call('GET', '/api/v1/vendor/subscription')).status).toBe(200);
  const result = await call('POST', `/api/v1/vendor/services/${f.foreign_service}/resubmit`, { changed_fields: { vendor_profile_id: f.attacker_vendor_id, base_price_minor: 1 } }, { 'Idempotency-Key': 'benchmark-foreign-resubmit' });
  const after = state();
  evidence('B03', { status: result.status, before, after });
  await expect(result.status).toBe(404);
  await expect(after.foreign_service).toEqual(before.foreign_service);
  await expect(after.foreign_service.vendor_profile_id).toBe(f.foreign_owner_id);
  await expect(after.change_status).toBe('open');
  await expect(after.foreign_change_items).toEqual(before.foreign_change_items);
  await expect(after.attack_idempotency_count).toBe(0);
});

test('B05 BUG-VENDOR-BANK-DATA-001 admin edit masks fake bank data without changing stored values', { tags: ['known-defect-benchmark'] }, async ({ app, browser }) => {
  const before = state();
  await signIn(app, browser, 'admin');
  await app.open(fixture().admin_edit_url);
  await expect(browser.locator('input[id="data.bank_iban"]')).toBeVisible();
  const input = await browser.evaluate(() => {
    const field = document.querySelector('input[id="data.bank_iban"]');
    return field instanceof HTMLInputElement ? field.value : null;
  });
  const exposed = await browser.evaluate(() => document.documentElement.outerHTML.includes('EG000000000000000000000000001'));
  const after = state();
  evidence('B05', { input, raw_value_present_in_html: exposed, bank_unchanged: before.bank_iban === after.bank_iban });
  await expect(after.bank_iban).toBe(before.bank_iban);
  await expect(exposed).toBe(false);
  await expect(input).toBe('EG00••••001');
  await expect(browser.locator('input[id="data.bank_iban"]')).not.toHaveValue(before.bank_iban);
  await expect(browser.locator('input[id="data.bank_iban"]')).not.toHaveValue('');
});
