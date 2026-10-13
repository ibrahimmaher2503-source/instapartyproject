# GitHub remediation — batch 03

Branch: `codex/fix-github-issues-batch-03`  
Base: published batch 02, `793868684886beecd5d60a582fcbbf1437e24533`

## Fifteen primary issues

| Issue | Implementation scope |
|---|---|
| #2 | Vendor-list approval shares the existing checklist gate and reports action validation errors. |
| #4 | Vendor form and backend registration share the displayed password complexity rule. |
| #8 | Vendor list and detail timestamps use the admin's timezone, with Cairo fallback. |
| #10 | Change-request fields use supported selections, translated add labels and action/API validation. |
| #12 | Registration clears password and confirmation state after submission failures/success and rate limits. |
| #19 | Ledger-group and reconciliation model route keys match their public identifiers. |
| #20 | Homepage service hydration uses the same published/type-displayability gate as detail pages. |
| #23 | Provider-health names use the existing translated provider-name namespace. |
| #36 | Both vendor suspension confirmation modals collect a bounded reason and pass it to the existing audited action. |
| #40 | FAQ-category and trust-badge interface labels have English and Arabic translations. |
| #44 | Home-block payload validation errors reach the actual Filament field paths. |
| #45 | Subscription plan code uniqueness is validated before persistence, ignoring the current edit record. |
| #51 | Dashboard gates use actual resource authorization; per-resource queries, charts, panels and cache fingerprints preserve permissions. The vendor-operations pending tile uses the actual pending count. |
| #55 | Admin booking inbox titles/source labels use canonical readable references, including historical title rendering. |
| #88 | Current business-logo preview uses its storage disk and falls back when the file is missing. |

## Isolation and review boundaries

Only owned source hunks are transferred. The original checkout's unrelated storefront eager-loading, inbox reference-search and listener restructuring are excluded. Batch 01/02 source is inherited. Registration retains the existing registration/notification flow; the separate #11 privacy response and #3 administrator bootstrap changes are not included. Suspension reasons here cover the reported Filament modals; the existing API default reason is retained.

The user explicitly defers all tests until the issue campaign is finished. No automated tests, test-source edits, browser interaction, lint/static-analysis checks, build or dependency installation are performed for this batch. Source review does not establish runtime acceptance, real delivery, historical data parity or browser rendering.

The product allowlist is BATCH_03_FILES.txt. Two independent Luna Max source reviews found no material blocker in the selected snapshot after the dashboard panel/count refinements. The registration transfer was re-reviewed as the narrow #4/#12 change; both suspension UI callers pass their reason to the existing audit transition. Dashboard query and view gating were re-reviewed together. A non-blocking repeated eligibility-query cost remains in the row action. A separate pre-existing #77 reporting risk remains: the header credit aggregate combines currencies and rounds to whole units; this permission patch does not claim to correct that financial definition. No merge, deployment, production-data repair, complete QA coverage or resolution of all project issues is claimed.
