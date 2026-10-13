# GitHub remediation — batch 01

Branch: `codex/fix-github-issues-batch-01`  
Base: `c8882e45f895fe31245fd4e7e57147129d3ec544`

## Fifteen primary issues

| Issue | Published behavior |
|---|---|
| #7 | Approval checklist uses neutral EN/AR requirement labels, separate from failure messages. |
| #13 | Promotion creation uses the installed Filament form-state type. |
| #14 | Promotion editing uses the same compatible form-state type. |
| #15 | FAQ creation imports its actual rich-text component. |
| #16 | FAQ editing uses that component without the missing-class failure. |
| #17 | Customer detail uses actual Infolist layout components. |
| #34 | Used promotion codes are retained with localized feedback; unused deletion remains supported. |
| #37 | Settings editing preserves decoded JSON/scalar types and rejects invalid or empty numeric/boolean text before saving. |
| #80 | Withdrawal availability uses the already-reserved ledger balance rather than subtracting the reserve twice. |
| #82 | Wallet statements derive cumulative balances without editing ledger history. |
| #84 | Commission projections reconcile item/allocation payout and fund delivery once; completed refunds reconcile after delayed capture jobs, using stable item allocations and cumulative component caps. Full refund retains vendor delivery fees under the user's explicit decision. |
| #87 | Service creation links respect approved product types, including digital when enabled. |
| #95 | Phone changes enforce E.164/uniqueness through panel and API; unchanged numbers retain verification and valid new numbers clear it. |
| #96 | EN/AR business-hour guidance explains closing earlier than opening as the following day. |
| #98 | Mandatory System preferences display enabled and cannot be disabled; optional choices stay editable. |

The exact product-file allowlist is [BATCH_01_FILES.txt](BATCH_01_FILES.txt). Shared availability projections also support the still-open #83 acceptance work; this batch does not claim that entire report closed. The existing balanced-ledger-check migration is included as a MySQL/MariaDB compatibility dependency; no migration is run or production ledger rewritten here.

## Review and deferred validation

The user explicitly instructed on 2026-10-13: publish each fifteen-issue batch on a new branch, rely on code review now, and defer tests until all issues are finished. No test runner, browser test, syntax/lint/static-analysis gate, dependency installation or application setup is executed for this publication. Earlier local test results are historical and are not proof of this selected branch snapshot. Final regression and browser acceptance remain deferred, including the digital-link source refinement.

Existing test source and local evidence remain preserved in the original checkout for the later validation campaign. This publication includes product code and its batch record; no credentials, runtime output, original QA data, dependency manifests, or unrelated storefront work are included. Later #94 recovery and #97 document-deletion locale hunks were deliberately excluded.

Deferred test-source compatibility work: `CommissionRefundAllocationTest.php` directly constructs the previous refund listener and mocks its former reader flow. It will need adaptation to `ReconcileCompletedRefundsAction`, `completedRefundsForPayment()` and the cumulative reversal argument when the user resumes the validation campaign. No test source was changed under the current instruction. The production reader has one implementation; no production implementer was left behind.

Publishing this branch is not a merge, deployment, production-data repair or release acceptance. Existing GitHub closures for these fifteen issues describe local fixes; the branch supplies their reviewable source publication.
