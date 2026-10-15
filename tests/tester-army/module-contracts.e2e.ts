import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { apiClient, fixtures } from './api-client';

const reads = {
  customer: ['/api/v1/customer/profile', '/api/v1/customer/addresses', '/api/v1/customer/loyalty/balances',
    '/api/v1/customer/reviews', '/api/v1/customer/promotions/active', '/api/v1/customer/notification-preferences',
    '/api/v1/customer/notifications', '/api/v1/customer/notifications/unread-count'],
  vendor: ['/api/v1/vendor/wallet', '/api/v1/vendor/wallet/ledger', '/api/v1/vendor/withdrawals',
    '/api/v1/vendor/wallet/settlements', '/api/v1/vendor/bank-accounts', '/api/v1/vendor/pricing/commission-rates',
    '/api/v1/vendor/subscription', '/api/v1/vendor/booking-vendors', '/api/v1/vendor/reports/bookings',
    '/api/v1/vendor/reports/revenue', '/api/v1/vendor/reports/services-performance'],
};
for (const [role, routes] of Object.entries(reads)) {
  for (const route of routes) {
    test(`${role} reads own module contract ${route}`, async ({ app }) => {
      const call = await apiClient(app.baseUrl!, role);
      const result = await call('GET', route);
      await expect(result.status).toBe(200);
      await expect(result.body.data !== undefined).toBe(true);
    });
  }
}

test('settlement API denies a customer access to vendor wallet', async ({ app }) => {
  const call = await apiClient(app.baseUrl!, 'customer');
  await expect((await call('GET', '/api/v1/vendor/wallet')).status).toBe(403);
});

test('Support ticket validation and same-request deduplication', async ({ app }) => {
  const call = await apiClient(app.baseUrl!, 'customer');
  await expect((await call('POST', '/api/v1/customer/support/tickets', {})).status).toBe(422);
  const payload = { subject: 'Tester army support', body: 'Local fixture request for assistance.', email: 'fixture@example.test' };
  const first = await call('POST', '/api/v1/customer/support/tickets', payload);
  await expect(first.status).toBe(201);
  const retry = await call('POST', '/api/v1/customer/support/tickets', payload);
  await expect(retry.status).toBe(200);
  await expect(retry.body.data).toEqual(first.body.data);
});

test('TrustSafety public escrow, vendor badges and report validation', async ({ app }) => {
  const call = await apiClient(app.baseUrl!, 'customer');
  await expect((await call('GET', '/api/v1/customer/settings/escrow-banner')).status).toBe(200);
  await expect((await call('GET', `/api/v1/customer/vendors/${fixtures().vendor}/badges`)).status).toBe(200);
  await expect((await call('POST', '/api/v1/customer/reports', {})).status).toBe(422);
});

test('Shared CMS missing page and invalid navigation slot reject invalid requests', async ({ app }) => {
  const call = await apiClient(app.baseUrl!, 'customer');
  await expect((await call('GET', '/api/v1/cms/pages/tester-army-missing-page')).status).toBe(404);
  await expect((await call('GET', '/api/v1/theme/menus?slot=invalid')).status).toBe(422);
});

test('Promotions rejects a missing code and notification inbox read is idempotent', async ({ app }) => {
  const call = await apiClient(app.baseUrl!, 'customer');
  await expect((await call('POST', '/api/v1/customer/promo-codes/validate', {})).status).toBe(422);
  const first = await call('POST', '/api/v1/customer/notifications/mark-all-read', {});
  await expect(first.status).toBe(200);
  await expect((await call('POST', '/api/v1/customer/notifications/mark-all-read', {})).status).toBe(200);
  const unread = await call('GET', '/api/v1/customer/notifications/unread-count');
  await expect(unread.status).toBe(200);
});
