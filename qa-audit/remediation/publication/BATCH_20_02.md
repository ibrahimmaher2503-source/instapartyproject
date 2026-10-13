# Twenty-issue batch 02 — reviewed source publication

Branch: `codex/fix-issues20-01a133b7-batch-02`.
Base: `f10c5a102790c808ea2df943f3e8904b185a2379` (main after PR #125).
Workspace: `C:/Users/N/.codex/worktrees/issues20-publish-01a133b7/instapartyproject`.

Exactly twenty selected issues: #21 #31 #33 #50 #52 #53 #58 #59 #65 #66 #77 #78 #83 #86 #93 #110 #112 #115 #118 #122.

All twenty source bundles are assembled and have received parallel domain source review. Tests and regression fixtures are prepared source, not passing evidence. No behavioral tests, build, browser, application startup, migration or provider request were executed during this continuation, as directed by the owner. Native formatting and static analysis are recorded separately below for publication; source review does not establish runtime acceptance. This batch does not close issues or merge into main by itself.

The preceding twenty issues were merged and closed through [PR #125](https://github.com/ibrahimmaher2503-source/instapartyproject/pull/125). GitHub had 53 open issues at initial preparation and 88 at the publication read, after additional reports appeared. These new reports do not expand this selected batch. Each inventory count is a point-in-time snapshot.

## Selected source scope

| Issue | Prepared implementation | Main entry points / regression source |
|---|---|---|
| #21 | Shared public media fallback, scoped card/home/service image hunks | Shared `PublicMediaUrl`; storefront media views; `GitHubPublicMediaFallbackTest` |
| #31 | Business/noise/expired idempotency filters and expired-only purge implementation | Payments `IdempotencyKeyResource`, `EloquentIdempotencyKeyRepository`; `GitHubIdempotencyRetentionTest` |
| #33 | Approval reloads and locks the persisted withdrawal; preserves original request timestamp | Settlement `ApproveWithdrawalAction`; three withdrawal pages' timezone hunks; `GitHubWithdrawalTimestampHistoryTest` |
| #50 | Capture/refund events rebuild paid status from captured payments and completed refunds | `BookingPaymentTotalsReader`, binding/reader, booking listener/resource; `GitHubFinanceCampaignTest` |
| #52 | Invoice subtotal/discount/loyalty/delivery reconciliation preserves recorded total | Booking recalculation/discount listener and loyalty writers; `BuildBookingInvoiceDataAction`, invoice view; `GitHubBookingInvoiceAmountsTest` |
| #53 | Invoice shows real booking identity, configured branding, parties, payments and recorded tax context | Delegated invoice controller/action/view, Booking EN/AR invoice keys; `BookingInvoiceTest` |
| #58 | Authorized staff/customer/vendor invitation, contact/role/recovery actions and safe pending-vendor creation | Identity account Actions and UserResource pages, EmailRecipientReader; `AdminAccountManagementSecurityTest` |
| #59 | Partial refund form, remaining cash caps, durable processing/uncertain outcome reservation, authenticated confirmation and bounded vendor reversal | RefundPaymentAction reused in PaymentResource/PaymentsRelationManager; Payments actions/gateway/contracts, settlement reconciliation; API registry and Bruno; finance/integrity/allocation regressions |
| #65 | Accurate provider readiness and Laravel transactional mail adapter with recipient boundary | Communication provider-health page, MailReadiness, LaravelMailEmailAdapter; `TransactionalMailReadinessTest` |
| #66 | Admin MFA setup/login/replay protection, encrypted secret, single-use recovery, logout and support-session proof | Identity security Actions/session/model/page/middleware, additive migration, both AdminPanelProvider variants; `AdminMfaSecurityTest`, support-session regression |
| #77 | Financial EGP sums/windows separated from all-currency attention counts; operational attention ordered first | AdminDashboardSectionsWidget/view, VendorStatsOverviewWidget, VendorDashboardSummaryService; `GitHubPanelFinanceAndFormsTest` |
| #78 | Existing date picker configuration, exact delete reference and deliberate save keyboard behavior | AdminPanelProvider and minimal Enter-only panel hook; `GitHubPanelFinanceAndFormsTest` |
| #83 | Withdrawal balance dependency retained from merged base; viewer timezone applied to request/approval/payment history | WithdrawalResource, WithdrawalsQueueResource, VendorWithdrawalsPage; finance/history regression sources |
| #86 | Existing missing category fields may be edited without changing category; strict new values/category changes remain | CategoryFieldValidationService, all three update Actions and shared schema form concern; `GitHubCatalogLegacySchemaEditTest` |
| #93 | Owned authorized vendor conversations and rental availability calendar | Communication vendor pages/RestrictedChatPanel and frozen scalar view; Catalog calendar page, bilingual keys/views; `GitHubVendorChatCalendarTest` |
| #110 | Snapshot one delivery fee per vendor for rental/sale, currency guard, parent row locking and totals/removal consistency | AddItemToBookingAction selected delivery/parent-lock hunks, CoverageAreaDTO/resolver, RemoveItem/totals/actions and booking view; `GitHubCoverageDeliveryFeeTest` |
| #112 | Customer cancel/pay/review controls call existing guarded APIs with CSRF/idempotency handling | CustomerAccountController, booking/account views, booking-actions JS, storefront initialization, stateful API middleware; PHP/frontend regression sources |
| #115 | Unavailable service/vendor presentation follows current visibility rules | Discovery VendorController and service view scoped hunks; customer action regression source |
| #118 | Explicit viewer timezone labels on customer/admin booking dates and six vendor booking pages | BookingResource, six vendor pages, account views and Booking EN/AR display key; `GitHubBookingEventTimezoneTest` |
| #122 | Admin service forms reuse existing category schema and major-price form behavior for all types | Rental/Sale/DigitalServiceResource, shared schema concern; `GitHubCatalogServiceFormsTest` |

Related safeguards retained: #9 registration uses the existing GeographyRepository boundary; #57 support sessions require the #66 MFA proof; #123 payment relation notifications redact provider/internal failures. Strong bootstrap/reset/password policy supports account/MFA security; no production credential rotation occurred. Existing merged balances, refund DTO/controller/repository helpers and media assets remain dependencies. Mixed AddItem source inventory reservation/retry changes and unrelated platform export/filter/SLA/bulk/notes/UX work were not transferred. Wallet identity #108 changes and its specific finance test were excluded.

## Parallel review completion

Ten domain agents were dispatched and restarted at the owner's request after usage/network interruptions. Completed source corrections include: disk-aware detail/portfolio media fallback; HTTP-only Livewire noise filtering and enum-compatible configured internal replay retention; visible withdrawal timezone labels; live invoice payment headings and Money-based loyalty removal; transport-aware recovery and active-account mail recipients; password-bound MFA proof and revoked support logout; matched elapsed dashboard windows and permission boundaries; persisted-only category grandfathering, empty boolean preservation and recursive multiselect validation; repeated chat/calendar ownership checks; post-commit removal totals and consistent customer review guidance; one net service refund cash budget across payment rows. The vendor self-verification prompt is a related recovery dependency and is bound in the vendor panel; no external email issue is closed by this change.

Regression source was extended for these corrections across the relevant roles, product types and EN/AR locales, but was not executed. Optional MFA enrollment policy remains intact; enrolled sessions must authenticate again because the proof token now also binds the password hash. Existing broader internal idempotency callback concurrency and cross-scope collision behavior was preserved rather than redesigned.

## Delivery boundary and remaining gates

Native publication gates completed after source freeze: changed-file Pint formatting followed by its verification passed for 153 PHP files (exit 0); full configured PHPStan with 2G memory reported `[OK] No errors` (exit 0); `git diff --check` passed. PHPStan excludes module Filament paths, so these results do not establish panel behavior. Pest, frontend tests, browser tests and builds remain explicitly deferred by the owner. No hook was bypassed. The exact allowlist contains 178 paths and no environment, database, runtime, credential or dependency-version changes.

`BATCH_20_02_FILES.txt` is the exact sorted changed/untracked source-file allowlist, including this document and itself. Ignored dependencies/runtime artifacts are excluded. The original checkout and historical batch 05 were left untouched. No dependency version, production environment, customer data, ledger history or deployed service was changed.

The MFA additive migration is prepared and not executed. The previously observed MFA page current-password signature error has been corrected to `rule('current_password:web')` in source; its failing test has not been rerun. No passing result is inferred from that correction.

Actual Paymob partial refund/account eligibility, callback payloads, multi-process database concurrency, mail/SMS/WhatsApp delivery, configured invoice branding, historical withdrawal causes, browser date/forms/media/chat/calendar acceptance, and full native regression remain unverified. WhatsApp remains a stub. Ambiguous refund outcomes reserve their processing state; they are not declared completed or redispatched. Lost MFA recovery credentials have no new email-only bypass. No production release or acceptance claim is made.
