import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { apiClient, fixtures } from '../api-client';

test('published displayable service remains available when saved to wishlist', { tags: ['bugbash'] }, async ({ app }) => {
  const call = await apiClient(app.baseUrl!, 'customer');
  const id = fixtures().services.sale.id;
  const result = await call('POST', '/api/v1/customer/wishlist/items', { service_id: id });
  await expect(result.status).toBe(201);
  await expect(result.body.data.service_id).toBe(id);
  await expect(result.body.data.is_available).toBe(true);
});
