import type { E2EConfig } from 'e2e';
import { web } from '@e2e-dev/web';
import base from './e2e.config';

export default {
  ...base,
  tests: ['tests/example.e2e.ts'],
  agents: {
    default: {
      ...base.agents.default,
      maxSteps: 40,
      maxModelCalls: 40,
      context: `${base.agents.default.context} The app is a disposable local fixture with three published products: E2E Party Lights, E2E Party Decorations and E2E Digital Invitation, from E2E Celebration Vendor. No uploaded media or customer history is seeded. Missing fixture images/content are not product bugs. Email/SMS delivery, Firebase, real gateways and paid integrations are unavailable. Do not sign up, submit bookings/payments, or navigate outside local loopback. An anonymous selected-service wizard redirect to sign-in is intentional. Check screenshots before reporting split text, lazy images or links opening another tab as defects. Report exact reproduction steps and distinguish fixture limitations.`,
    },
  },
  targets: [
    { name: 'desktop', engine: web({ viewport: { width: 1440, height: 900 } }), app: { url: 'http://127.0.0.1:18090', environment: 'test' } },
    { name: 'mobile', engine: web({ viewport: { width: 390, height: 844 } }), app: { url: 'http://127.0.0.1:18090', environment: 'test' } },
  ].filter(target => target.name === (process.env.E2E_AUDIT_TARGET ?? 'desktop')),
} satisfies E2EConfig;
