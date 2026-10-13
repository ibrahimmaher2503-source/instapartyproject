# GitHub remediation — batch 02

Branch: `codex/fix-github-issues-batch-02`  
Base: published batch 01, `04f07b7cdcbf3da9acfd1bf5dc7a805fa519fd60`

## Fifteen primary issues

| Issue | Implementation scope |
|---|---|
| #5 | Owner-only correction page for the current open change request before email verification; other panel gates remain protected. Supported changes and resubmission are atomic. In-app notification source/template is present. |
| #39 | Admin profile page requires the current password for email/password changes and uses the existing strong password rule. |
| #43 | Advertisement-package creation uses the existing action and translated form state. |
| #47 | Tax-rate creation uses the existing action and translated form state. |
| #48 | Tax editing hydrates both existing translations and uses the existing update action. |
| #49 | Global search titles use the actual subscription-payment reference column. |
| #91 | Vendor reply field/actions and submission status have readable bilingual labels. |
| #94 | Phone changes lead to the existing verification flow; send-code failures have feedback while business access remains protected. |
| #97 | Document content/type/size validation precedes permanent storage; owners may delete pending/rejected documents, while approved documents remain protected. |
| #99 | Changing a coverage city clears its own obsolete field error. |
| #102 | Vendor responses have an admin moderation resource, authorization, permitted transitions and audit entries. |
| #103 | Approved vendor-review aggregates and featured review text appear on public vendor views, with compatible customer API data. |
| #104 | Customer cancellation includes accepted allocations, records their actual prior status and invalidates vendor caches; started/completed allocations block cancellation under fresh locks. Vendor decision/proposal/forward callers lock parent before children, and an old draft cannot revive a cancelled/started/completed booking. |
| #105 | Document timeline uses the reviewer identity and effective review time with unambiguous cursor fields. |
| #106 | Document-approval notification has the existing in-app channel and bilingual template. |

## Isolation and review boundaries

The product-file allowlist is `BATCH_02_FILES.txt`. For #103 only the owned controller/view additions were transferred; unrelated storefront filters, layout, gallery and geography edits from the original dirty checkout were excluded. Identity locale transfer contains only the document-deletion rule; later approval/suspension/registration changes are reserved for the next batch. Batch 01's source is inherited unchanged.

Original-checkout source changes and this selected publication snapshot completed independent source review with no material blocker in the listed scope. The five cancellation-dependent actions are VendorAccept, VendorReject, VendorModify, SubmitBookingModificationProposal and AdminForwardModification; their locked rereads revalidate parent relationships. The user explicitly defers automated tests until the issue campaign is finished; no tests, test-source changes, lint/static-analysis commands, browser interaction or app setup are performed for this batch. Earlier local test evidence is historical and does not prove this snapshot.

Runtime adoption still needs normal application/template-seeder rollout and working providers/queues. Real SMS/email delivery, browser rendering and historical data are unverified. Existing API profile caching/rating ordering behavior is retained. Unsupported legacy correction paths fail closed with explanatory feedback; the separate #10 creation-boundary hardening is prepared for the next batch.

This record does not claim a merge, deployment, production-data repair, complete QA coverage or resolution of all project issues.
