import type { E2EConfig } from 'e2e';
import base from './e2e.config';
export default {
  ...base,
  tests: ['tests/tester-army/bugs/*.e2e.ts'],
} satisfies E2EConfig;
