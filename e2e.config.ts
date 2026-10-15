import type { E2EConfig } from 'e2e';
import { web } from '@e2e-dev/web';
import { chatgpt } from 'e2e/oauth/chatgpt';
import { readFileSync } from 'node:fs';

export default {
  credentials: Object.fromEntries(['customer', 'other_customer', 'admin', 'vendor'].map(role => [role, {
    username: `tester-army-${role}@example.test`,
    password: () => JSON.parse(readFileSync('.e2e/credentials.json', 'utf8'))[role].password,
  }])),
  agents: {
    default: {
      model: chatgpt('gpt-6-luna'),
      system: 'Verify each outcome on screen. Use only the local test fixture; do not submit bookings or payments.',
      context: 'InstaParty is a marketplace with Arabic RTL and English LTR pages. Guests must sign in before planning with a selected service.',
    },
  },
  tests: ['tests/example.e2e.ts', 'tests/tester-army/*.e2e.ts'],
  workers: 1,
  retries: 0,
  trace: 'on',
  reporters: ['list', 'markdown', 'junit'],
  targets: [{
    name: 'instaparty-local',
    engine: web({ viewport: { width: 1440, height: 900 } }),
    app: {
      url: 'http://127.0.0.1:0',
      readyUrl: 'http://127.0.0.1:{port}/up',
      environment: 'test',
      command: {
        executable: 'node',
        args: ['tests/tester-army/serve.mjs', '{port}'],
        env: { WSL_INTEROP: process.env.WSL_INTEROP ?? '' },
        startupTimeout: 900_000,
        log: '.e2e/logs/app.log',
      },
    },
  }],
} satisfies E2EConfig;
