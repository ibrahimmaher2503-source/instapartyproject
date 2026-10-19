import type { E2EConfig } from 'e2e';
import { web } from '@e2e-dev/web';
import base from './e2e.config';
export default {
  ...base,
  tests: ['tests/tester-army/mobile/*.e2e.ts'],
  targets: base.targets.map(target => ({ ...target, engine: web({ viewport: { width: 390, height: 844 } }) })),
} satisfies E2EConfig;
