# High-only batch03 — implementation and subsequent source review

Branch: codex/fix-issues20-01a133b7-high-03.
Base: 5e81926f6e39c97dc96fdd3abd71a185d2e3236d (main after PR170).

The owner requested the next batch contain only High issues, finish implementation quickly in parallel, then review the code. Live GitHub selection contains only five High reports: #3, #18, #126, #127, #128. No lower-severity reports are included to fill a nominal twenty-ticket batch.

| Issue | Implementation scope | Remaining acceptance |
|---|---|---|
| #3 | Privileged password/login/recovery protection, preserve existing MFA policy | Affected production credential rotation, old-session invalidation and access-log review; no secrets or production state accessed |
| #18 | Truthful bilingual occasion-filter availability explanation; preserve actual mapping and visibility | Genuine occasion mappings and actual filtered/wizard flow; no invented service mappings |
| #126 | Existing expiry/grace transitions and effective subscription analytics | Production scheduler, existing stale rows and paid renewal behavior |
| #127 | Recent-touch interval parsing, terminal failure, owned locks and stale run timeout | Scheduled execution and affected historical runs |
| #128 | Persistent-condition deduplication, valid withdrawal reserve evidence and admin alert/resolution workflow | Existing ledger provenance, queue delivery and actual admin workflow |

Five domain agents implement isolated file scopes. Lead integrates shared contract, scheduling and translation dependencies. Independent source review starts after implementation; correction findings return to each owner. Behavioral tests, browser runs and builds remain deferred per owner instruction. No production data, environment, credentials or immutable ledger history are changed. New migration source, if required, is not executed.

## Implementation and subsequent source review

Five domain implementations are assembled. Independent authentication/search review found no introduced high/medium blocker. Subscription review found yearly MRR used monthly currency; corrected to monthly price/currency for Monthly and annual price/currency divided by12 with explicit HALF_EVEN rounding for Yearly. Reviewer approved the correction. Financial review found ignored known conditions could recreate alerts and latest-run findings were missing from detail views; corrections retain ignored tracking and add append-only run occurrence visibility while preserving original evidence. Run counters count unique findings across wallets.

A finding-tracking/assignment migration is prepared; it has not been executed. Stale-run and subscription sweeps are registered every minute using existing native scheduler coordination. Existing affected production rows, queue delivery and running schedule remain unverified. #3 cannot close solely from source changes because the reported credential must be rotated on production and its logs reviewed. #18 adds the permitted availability explanation without inventing mappings or weakening occasion filtering.

Tests are prepared regression source only. Pest, frontend/browser tests, builds and production actions remain deferred. Publication formatting/static gates will be recorded if executed; source review is not runtime acceptance.

## Final financial safeguards

New approvals require the real, matching posted request-time reserve and posted withdrawal-reserve transaction group. Invalid legacy requests fail closed and need separate ledger investigation; the approval path does not fabricate a reserve or add a second debit. Current-condition views suppress legacy duplicate rows without rewriting their evidence, while run history remains reachable. Reconciliation reserve detection uses the same evidence predicates as approval.

The source fix does not rotate production credentials, review real access logs, repair historical financial rows, execute the migration or demonstrate the live scheduler/queue. These remain explicit acceptance gates. High03 does not expand approved subscription products, renewal billing, tax, disputes or dependencies.

## Review and publication status

Independent post-implementation source reviews are complete: authentication/search approved; subscription currency correction re-reviewed and approved; reconciliation suppression, occurrence visibility, unique counting, valid-reserve approval/detection and legacy current-condition projection re-reviewed and approved. No remaining concrete introduced high/medium source blocker was reported. The final occurrence fixture uses observedFindings() while preserving the original findings() relationship.

No behavioral test, browser flow, build, production credential rotation, real data repair, migration or provider request was executed. Regression sources are prepared but unrun. Scoped native formatting verification passed42 changed PHP files. Full configured static analysis and the exact publication boundary are recorded after their final result. These gates do not prove runtime, queue or deployment acceptance.

Final publication gates: changed-file Pint verification passed42 PHP paths (exit0); full configured PHPStan2G reported [OK] No errors (exit0); Git whitespace and exact46-path boundary checks passed. Native PHPStan excludes module Filament paths. No behavioral test or browser/build result is claimed. The source-only GitHub closure scope is #18/#126/#127/#128; #3 remains open for its explicitly required production credential/session/log acceptance. Historical ledger causes and deployed acceptance remain separate from source publication.
