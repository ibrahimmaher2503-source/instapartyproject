import type { E2EConfig } from 'e2e';
import base from './e2e.config';
export default {
  ...base,
  tests: ['tests/tester-army/benchmark/*.e2e.ts'],
  targets: base.targets.map(target => ({
    ...target,
    app: { ...target.app, command: { ...target.app.command, env: { ...target.app.command.env, TESTER_ARMY_BENCHMARK: '1' } } },
  })),
} satisfies E2EConfig;
