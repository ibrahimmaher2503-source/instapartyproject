import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { apiClient } from '../api-client';

test('unknown chat report target returns a client error rather than a server error', { tags: ['bugbash'] }, async ({ app }) => {
  const call = await apiClient(app.baseUrl!, 'customer');
  const result = await call('POST', '/api/v1/customer/reports', {
    reportable_type: 'chat_thread', reportable_id: '01ARZ3NDEKTSV4RRFFQ69G5FAV', reason: 'other', details: 'Local test target does not exist.',
  });
  await expect(result.status).toBeGreaterThanOrEqual(400);
  await expect(result.status).toBeLessThan(500);
});
