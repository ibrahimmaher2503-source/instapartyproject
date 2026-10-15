import type { E2EConfig } from 'e2e';
import base from './e2e.benchmark.config';
export default {
  ...base,
  tests: ['tests/tester-army/risk/*.e2e.ts'],
} satisfies E2EConfig;
