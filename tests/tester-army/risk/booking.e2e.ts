import { test } from '@e2e-dev/web';
import { credentials, expect } from 'e2e';
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { apiClient, fixtures } from '../api-client';

const state = (id: string) => JSON.parse(execFileSync('python3', ['tests/tester-army/risk/state.py', id], { encoding: 'utf8' }));
const record = (id: string, data: unknown) => {
  mkdirSync('.e2e/risk-program/browser-evidence', { recursive: true });
  writeFileSync(`.e2e/risk-program/browser-evidence/${id}.json`, JSON.stringify(data, null, 2));
};
const payload = () => ({ occasion_id: fixtures().occasion, event_starts_at: new Date(Date.now() + 16 * 86400000).toISOString(), guest_count: 2, notes: 'Risk pilot isolated event note', address: { city_id: fixtures().city, address_line: 'Disposable test street', recipient_name: 'Fixture Customer', recipient_phone_e164: '+201000000001' } });

test('connected sale booking: draft → held item → vendor review → exact retry → final vendor acceptance', async ({ app, browser }) => {
  const call = await apiClient(app.baseUrl!, 'customer'); const vendor = await apiClient(app.baseUrl!, 'vendor');
  const draft = await call('POST', '/api/v1/customer/bookings', payload());
  await expect(draft.status).toBe(201);
  const id = draft.body.data.public_id; const path = `/api/v1/customer/bookings/${id}`;
  const created = state(id);
  record('SC-Booking-d23fdddb0e', { response: draft, persisted: created, expected: 'Owned draft, optional note persisted once' });
  await expect(created.booking.lifecycle_status).toBe('draft');
  await expect(created.notes).toEqual([{ body: 'Risk pilot isolated event note' }]);
  await expect(created.booking.total_currency).toBe('EGP');
  const owner = await call('GET', '/api/v1/customer/profile');
  await expect(owner.status).toBe(200);
  const item = await call('POST', `${path}/items`, { service_id: fixtures().services.sale.id, quantity: 1 });
  const added = state(id);
  record('SC-Booking-159b4bcd53', { response: item, persisted: added, expected: 'Exact sale snapshot, one unexpired inventory hold' });
  await expect(item.status).toBe(201);
  await expect(added.items.length).toBe(1);
  await expect(added.items[0].unit_price_minor).toBe(12500);
  await expect(added.items[0].line_total_minor).toBe(12500);
  await expect(added.items[0].quantity).toBe(1);
  // The original add-item contract stores identity in service_id and names in name_snapshot.
  await expect(added.items[0].service_public_id).toBe(fixtures().services.sale.id);
  await expect(JSON.parse(added.items[0].name_snapshot)).toEqual(fixtures().services.sale.names);
  await expect(added.holds.length).toBe(1);
  await expect(added.holds[0].status).toBe('held');
  await expect(Date.parse(added.holds[0].expires_at + 'Z') > Date.now()).toBe(true);
  const headers = { 'Idempotency-Key': '5d6c516e-c31c-4436-b0a4-f8f2637cc020' };
  const submittedAt = Date.now();
  const submit = await call('POST', `${path}/submit`, {}, headers);
  const submitted = state(id);
  record('SC-Booking-96dc5a0c0e', { response: submit, persisted: submitted, submitted_at_ms: submittedAt, expected: 'Vendor review, VAT snapshot, transition and 24h response deadline' });
  await expect(submit.status).toBe(200);
  await expect(submitted.booking.lifecycle_status).toBe('vendor_review');
  await expect(submitted.vendors[0].sub_status).toBe('pending');
  await expect(submitted.items[0].vat_rate_bps).toBe(0);
  await expect(submitted.items[0].vat_amount_minor).toBe(0);
  await expect(submitted.transitions.filter((t: any) => t.to_state === 'vendor_review').length).toBe(1);
  await expect(Math.abs(Date.parse(submitted.vendors[0].response_deadline + 'Z') - submittedAt - 86400000) < 10000).toBe(true);
  const retry = await call('POST', `${path}/submit`, {}, headers); const retried = state(id);
  record('SC-Booking-ff6a2c0c84', { response: retry, before: submitted, after: retried, expected: 'Same booking, no additional transition/deadline/notification/idempotency effect' });
  await expect(retry.status).toBe(200);
  await expect(retry.body.data.public_id).toBe(id);
  await expect(retried).toEqual(submitted);
  const accepted = await vendor('POST', `/api/v1/vendor/booking-vendors/${submitted.vendors[0].public_id}/accept`, {});
  const confirmed = state(id);
  record('SC-Booking-1806e15c31', { response: accepted, persisted: confirmed, expected: 'Last pending vendor accepts and parent becomes confirmed' });
  await expect(accepted.status).toBe(200);
  await expect(confirmed.vendors[0].sub_status).toBe('accepted');
  await expect(confirmed.booking.lifecycle_status).toBe('confirmed');
  await expect(confirmed.booking.total_minor).toBe(12500);
  await expect(confirmed.booking.payment_status).toBe('unpaid');
  const account = credentials.user('customer');
  await app.open('/en/auth/login');
  await browser.locator('input[name="login"]').fill(account.username);
  await browser.locator('input[name="password"]').fill(account.password);
  await browser.locator('form button[type="submit"]').click();
  await expect(browser).toHaveURL('/en', { timeout: 30000 });
  await app.open(`/en/account/bookings/${id}`);
  await expect(browser.locator('.sf-account-status')).toHaveText('Confirmed');
  await expect(browser.locator('.sf-account-row h3')).toHaveText(fixtures().services.sale.names.en);
  await expect(browser.locator('[data-event-notes] p')).toHaveText('Risk pilot isolated event note');
  await expect(browser.locator('.sf-account-details')).toContainText('125');
  record('connected-browser', { public_id: id, status: await browser.locator('.sf-account-status').textContent(), displayed_service: await browser.locator('.sf-account-row h3').textContent(), persisted: state(id), limitation: 'API mutations followed by browser readback, not a UI checkout or capture.' });
});

test('SC-Booking-32b824ae4d foreign customer cannot submit an owned nonempty draft', async ({ app }) => {
  const owner = await apiClient(app.baseUrl!, 'customer'); const attacker = await apiClient(app.baseUrl!, 'other_customer');
  const draft = await owner('POST', '/api/v1/customer/bookings', payload()); await expect(draft.status).toBe(201);
  const id = draft.body.data.public_id; const path = `/api/v1/customer/bookings/${id}`;
  await expect((await owner('POST', `${path}/items`, { service_id: fixtures().services.sale.id, quantity: 1 })).status).toBe(201);
  const before = state(id);
  const response = await attacker('POST', `${path}/submit`, {}, { 'Idempotency-Key': '581ad54c-bbf3-432b-88a1-5e731a4cc875' }); const after = state(id);
  record('SC-Booking-32b824ae4d', { response, before, after, expected: '404 and no foreign booking mutation' });
  await expect(response.status).toBe(404); await expect(after).toEqual(before);
});

test('SC-Booking-e8e7c96608 empty draft cannot enter vendor review', async ({ app }) => {
  const call = await apiClient(app.baseUrl!, 'customer');
  const draft = await call('POST', '/api/v1/customer/bookings', payload()); await expect(draft.status).toBe(201);
  const id = draft.body.data.public_id; const before = state(id);
  const response = await call('POST', `/api/v1/customer/bookings/${id}/submit`, {}, { 'Idempotency-Key': '33cc9e5e-0e35-4213-a373-14c5f11c87ea' }); const after = state(id);
  record('SC-Booking-e8e7c96608', { response, before, after, expected: '422, draft retained, no transition/notification/idempotency mutation' });
  await expect(response.status).toBe(422); await expect(after).toEqual(before);
});
