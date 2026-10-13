# Low-only batch05

Branch: codex/fix-issues20-01a133b7-low-05.
Base: c8c1270921678ae23034b4122d3795a6d7234dee (main after PR180).

The owner requests closure of Low GitHub issues using Luna subagents with xhigh reasoning. Live inventory contains eight Low reports; seven gpt-6-luna/xhigh implementers own independent domains. No other priority is added to fill twenty. Source review follows implementation before publication to main.

| Issue | Scope |
|---|---|
| #176 | Force Cancel availability and repeated-cancellation effects |
| #174 | Customer cancellation reason, real refund status and next steps |
| #173 | Supported cart quantity/removal and clear submission label |
| #139 | Safely escaped customer notes in authorized admin booking view |
| #137 | Wizard date-error input retention and earliest allowed date hint |
| #133 | Descriptive account titles, localized date punctuation and DOB maximum |
| #124 | Consistent occasion icon upload create/edit surface |
| #56 | Intended existing refund policy or corrected public link |

Behavioral tests, browser runs and builds remain deferred by prior owner instruction. Regression sources are prepared when non-trivial behavior changes. Native Pint and configured PHPStan are publication gates; source review is not runtime acceptance. No production deployment, user data changes, migrations, provider calls, new dependencies or invented policy content are authorized. Issues requiring unavailable approved content or live diagnosis remain open with their exact gate.

The owner subsequently authorizes creating the missing refund policy with content suitable for Egypt and editing through the existing admin CMS. The new policy uses actual booking/refund behavior and official Consumer Protection Agency guidance, with statutory rights taking precedence. Insert-only installation must preserve existing administrator edits and publishing state. Production publication and migration execution remain outside this request.

## Retained diagnosis

#124: create/edit use the same icon FileUpload schema, installed Alpine component and panel assets. The component awaits uploaded-file metadata before FilePond initialization; source alone cannot identify the observed raw-input cause. No speculative uploader replacement is made. The issue remains open for actual browser console/network and asset diagnosis.

## Assembled implementation

- #176/#139: both admin entry points use canonical terminal lifecycle states, enforce their existing role/permission in the action callback, and handle stale cancellation errors. The shared action rejects already-cancelled rows under the parent lock before intervention/history/event effects. Customer notes render as plain escaped text.
- #174: the customer detail shows booking total, actual captured/refunded/net-paid amounts, customer or explicitly public ForceCancel reason, and truthful refund next steps. Pending/processing take priority; failure reflects the latest attempt; completed partial/full outcomes remain distinct. Other internal notes stay hidden.
- #173: draft owners can edit supported sale/digital quantities while preserving item IDs and price snapshots. Sale capacity updates use the existing service lock and held reservation semantics; rental quantities remain unchanged with removal available. Existing booking submission/confirmation stays intact.
- #133: existing account headings drive descriptive page titles; locale-specific date punctuation preserves viewer timezone labels. DOB max is today; the existing server before-today policy remains unchanged. Captured Blade sections avoid double escaping special-character names.
- #137: date errors return the wizard to the occasion/date step, retain valid inputs and restore normalized old dates to the viewer timezone. Client minimums and server comparisons must describe the same instants, including the downstream search availability window.
- #56: the new canonical refund-policy page reuses existing bilingual CMS/admin editing. Insert-only installation preserves existing edits/publication and adds only a missing published-page footer link. A migration source installs content; it has not been executed. Egyptian goods guidance links directly to the Consumer Protection Agency; rental/service/digital copy follows actual request/refund behavior and preserves mandatory legal rights.

Review corrections include real mounted action authorization, special-character title escaping, latest/partial refund outcomes and planner-to-search timezone semantics. Source-only closure is planned for #176/#174/#173/#139/#137/#133/#56 after final reviews and static gates. #124 remains open for runtime diagnosis; no upload defect or data loss is invented.

## Final publication evidence

The final planner action sends UTC ISO instants to availability search and preserves the viewer-local event date. Planner search still requires a future absolute start; its date input admits the already validated local day, while ordinary search retains its today bound. Invalid dates during later wizard steps return to the date step and receive focus. The existing CMS editor's missing RichEditor import is repaired, and the new refund-policy slug is documented for the existing CMS API in controller PHPDocs, registry and Bruno.

Native Pint passed27 changed PHP source paths (exit0). Full configured PHPStan2G reported [OK] No errors (exit0). JavaScript syntax and Git whitespace, sensitive-path and secret-pattern checks passed. The exact44-path boundary includes this ledger and itself. PHPStan excludes module Filament paths; source checks are not actual admin/browser acceptance.

Focused regression sources are prepared but unrun. No Pest, frontend/browser test, build, server start, data migration, real gateway/provider send, database repair or production deployment ran. The policy's insert-only migration has not run on production. The policy summary cites official Egyptian Consumer Protection Agency guidance; this source review is not legal approval. #124 remains open pending its actual browser/asset diagnosis.
