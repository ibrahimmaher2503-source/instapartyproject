import type { E2EConfig } from 'e2e';
import base from './e2e.risk.config';
export default {
  ...base,
  agents: { default: {
    ...base.agents.default,
    maxSteps: 40,
    maxModelCalls: 40,
    system: 'Investigate one business edge at a time in this disposable local fixture. Verify the resulting screen and report exact actions and observations. Never visit an external domain, submit a payment, capture, refund, or final booking submission. Draft setup and local wishlist changes are permitted. Do not report fixture content or screenshot redaction as defects.',
    context: `${base.agents.default.context} Three published fixture products cost 125 EGP: E2E Party Lights (rental), E2E Party Decorations (sale), E2E Digital Invitation (digital). The fixture has no real images or customer history, and external integrations are unavailable. Use credential customer when asked. An anonymous selected-service wizard redirects to login intentionally. Optional event end is a historical policy conflict: report it as a decision question, not a confirmed defect. Lazy images, new-tab links, split accessible text and withheld screenshots are not defects without independent proof. TA-SYS-001 wishlist availability and TA-SYS-002 chat report are already known; do not claim them as new.`,
  } },
} satisfies E2EConfig;
