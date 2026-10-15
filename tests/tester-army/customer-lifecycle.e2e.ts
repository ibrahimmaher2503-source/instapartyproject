import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { apiClient, fixtures } from './api-client';

test('SC-Discovery-01fcf4441a / SC-Discovery-db85346a80 wishlist add, retry, ownership and removal', async ({ app }) => {
  const call = await apiClient(app.baseUrl!, 'customer');
  const other = await apiClient(app.baseUrl!, 'other_customer');
  const vendor = await apiClient(app.baseUrl!, 'vendor');
  const id = fixtures().services.sale.id;
  const add = await call('POST', '/api/v1/customer/wishlist/items', { service_id: id });
  await expect(add.status).toBe(201);
  const repeated = await call('POST', '/api/v1/customer/wishlist/items', { service_id: id });
  await expect(repeated.status).toBe(201);
  await expect(repeated.body.meta.wishlist_count).toBe(1);
  await expect((await other('GET', '/api/v1/customer/wishlist')).body.meta.total).toBe(0);
  await expect((await vendor('POST', '/api/v1/customer/wishlist/items', { service_id: id })).status).toBe(403);
  await expect((await call('DELETE', `/api/v1/customer/wishlist/items/${id}`)).status).toBe(204);
  await expect((await call('GET', '/api/v1/customer/wishlist')).body.meta.total).toBe(0);
});

test('SC-Booking-d23fdddb0e draft validation, ownership and discard', async ({ app }) => {
  const call = await apiClient(app.baseUrl!, 'customer');
  const other = await apiClient(app.baseUrl!, 'other_customer');
  await expect((await call('POST', '/api/v1/customer/bookings', {})).status).toBe(422);
  const f = fixtures();
  const result = await call('POST', '/api/v1/customer/bookings', {
    occasion_id: f.occasion, event_starts_at: new Date(Date.now() + 14 * 86400000).toISOString(),
    guest_count: 2, address: { city_id: f.city, address_line: 'Test street', recipient_name: 'Fixture Customer', recipient_phone_e164: '+201000000001' },
  });
  await expect(result.status).toBe(201);
  await expect(result.body.data.lifecycle_status).toBe('draft');
  const path = `/api/v1/customer/bookings/${result.body.data.public_id}`;
  await expect((await other('GET', path)).status).toBe(404);
  await expect((await other('DELETE', path)).status).toBe(404);
  await expect((await call('DELETE', path)).status).toBe(200);
});

test('connected sale draft → review → vendor acceptance → idempotent fake payment', async ({ app }) => {
  const call = await apiClient(app.baseUrl!, 'customer');
  const vendor = await apiClient(app.baseUrl!, 'vendor');
  const f = fixtures();
  const draft = await call('POST', '/api/v1/customer/bookings', {
    occasion_id: f.occasion, event_starts_at: new Date(Date.now() + 16 * 86400000).toISOString(),
    guest_count: 2, address: { city_id: f.city, address_line: 'Test purchase street', recipient_name: 'Fixture Customer', recipient_phone_e164: '+201000000001' },
  });
  await expect(draft.status).toBe(201);
  const path = `/api/v1/customer/bookings/${draft.body.data.public_id}`;
  const item = await call('POST', `${path}/items`, { service_id: f.services.sale.id, quantity: 1 });
  await expect(item.status).toBe(201);
  const review = await call('POST', `${path}/checkout-review`, {});
  await expect(review.status).toBe(200);
  await expect(review.body.data.ready_to_submit).toBe(true);
  await expect(review.body.data.total_minor).toBe(12500);
  await expect(review.body.data.currency).toBe('EGP');
  const submitted = await call('POST', `${path}/submit`, {}, { 'Idempotency-Key': '0152a4d2-5210-4d22-9dd2-4258dba9b101' });
  await expect(submitted.status).toBe(200);
  await expect(submitted.body.data.lifecycle_status).toBe('vendor_review');
  const bookingVendor = submitted.body.data.vendors[0].public_id;
  const accepted = await vendor('POST', `/api/v1/vendor/booking-vendors/${bookingVendor}/accept`, {});
  await expect(accepted.status).toBe(200);
  await expect(accepted.body.data.sub_status).toBe('accepted');
  await expect((await call('GET', path)).body.data.lifecycle_status).toBe('confirmed');
  const headers = { 'Idempotency-Key': 'tester-army-sale-payment-001' };
  const payment = await call('POST', `${path}/payments`, { method: 'card' }, headers);
  await expect(payment.status).toBe(201);
  await expect(payment.body.data.status).toBe('pending');
  await expect(payment.body.data.redirect_url).toBe(`https://testing.invalid/payments/${draft.body.data.public_id}`);
  const retried = await call('POST', `${path}/payments`, { method: 'card' }, headers);
  await expect(retried.status).toBe(201);
  await expect(retried.body.data.payment_public_id).toBe(payment.body.data.payment_public_id);
  await expect((await call('GET', path)).body.data.payment_status).toBe('unpaid');
});
