# GitHub enhancement triage: issues 57–79

Static source review: 2026-10-13 (Africa/Cairo). Source issue snapshot: `.e2e/risk-program/remediation/github-open-issues.json`. All 23 issue bodies were read; source findings below are current code evidence, not a fresh browser retest. No product code, database, service, external provider, GitHub comment, issue status or Git publication changed.

## Delivery gates shared by every implementation

Preserve each issue full scope. Existing definitions are not proof of working UI. Reuse module actions, policies, integer-minor-unit money, transactions, after-commit events, EN/AR and locked dependencies. New API surfaces require real request/resource/API registry/Bruno updates. Before committing: Pint and PHPStan, plus Pest --bail for app changes; focused regression checks and real affected-role browser proof where relevant. Use isolated data and approved sandbox integrations. An unmet provider/product requirement is an explicit remaining gate, never a status-only closure.

## #57 — [P1] Expose audited Login as vendor controls

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/57)

**Disposition:** Feasible local implementation; security acceptance required.

**Current evidence:** ImpersonateVendorAction::execute creates an audited Sanctum token expiring after 30 minutes, but this is not a browser login. ImpersonateVendorViaWebAction::execute audits and replaces the web session, records impersonation_started_at and regenerates session; EndImpersonationAction restores the admin. Scoped search found no live UI callers of either start action. The banner widget exists; web start action itself has no 30-minute expiry enforcement.

**Smallest complete plan:** Wire a permission-authorized Login as vendor button on ViewVendorProfile to the web action, add non-bypassable authorization in the application boundary, block nested impersonation, show the existing banner and end control on every vendor page, enforce a server-side 30-minute expiry and safe restoration if the administrator no longer exists. Record start/end/expiry audit without token values. Do not substitute an API token popup for the requested browser session.

**Required gates / external input:** Verify permitted/forbidden roles, nested attempt, expiry, logout/end, suspended/deleted vendor and lost-admin restoration in isolated browser sessions; verify audit actor and session rotation. No provider needed; permission and approved account-state rules must remain consistent.

**Source anchors:**

- `app/Modules/Identity/Application/Actions/ImpersonateVendorAction.php::execute`
- `app/Modules/Identity/Application/Actions/ImpersonateVendorViaWebAction.php::execute`
- `app/Modules/Identity/Application/Actions/EndImpersonationAction.php::execute`
- `app/Modules/Shared/Filament/Vendor/Widgets/ImpersonationBannerWidget.php`

## #58 — [P1] Add controlled admin user and vendor account management

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/58)

**Disposition:** Partly feasible; account-creation and role-security product decisions remain.

**Current evidence:** UserResource and CustomerResource expose list/view pages; VendorProfileResource exposes an EditVendorProfile page. Existing Identity actions include AdminUpdateCustomerProfileAction, UpdateVendorProfileAction, UpdateVendorEmailAction, UpdateVendorPhoneAction, ChangeVendorPasswordAction and ForceLogoutCustomerAction. VendorProfilePolicy uses generated view/update permissions with double-colon naming and custom lifecycle permissions; this must not be silently normalized.

**Smallest complete plan:** Add controlled edit/contact/security UI around existing actions and record-level policies, with audit and validation. Complete creation and role-assignment requirements separately: define permitted role transitions, invitation/verification behavior, temporary credentials and self-demotion/last-admin safeguards before introducing a generic admin create-user flow. Provide customer/vendor pages and explicit force-logout/security controls; a single contact-edit field is not full scope.

**Required gates / external input:** Product input: role matrix and creation/invitation workflow. Verify EN/AR validation, unique contacts, forbidden role escalation, cross-record permissions, lifecycle notifications, session invalidation and audit. Use disposable data; never set real user passwords for QA.

**Source anchors:**

- `app/Modules/Identity/Filament/Resources/UserResource.php::getPages`
- `app/Modules/Identity/Filament/Resources/CustomerResource.php::getPages`
- `app/Modules/Identity/Filament/Resources/VendorProfileResource.php::getPages`
- `app/Modules/Identity/Domain/Policies/VendorProfilePolicy.php`
- `app/Modules/Identity/Application/Actions/AdminUpdateCustomerProfileAction.php`

## #59 — [P1] Expose partial and full refund initiation from bookings and payments

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/59)

**Disposition:** Full refund UI feasible; partial refund blocked by explicit backend contract.

**Current evidence:** PaymentsRelationManager already has captured-payment Refund action with reason/confirmation, but no amount field. InitiateRefundAction::execute explicitly throws PartialRefundUnsupportedException for a supplied amount unequal to the payment amount. Existing ProcessRefundAction and ledger events must remain authoritative.

**Additional confirmed source defect:** ProcessRefundAction previously set the payment status to Refunded after every successful refund, even when completed refund rows total less than the captured amount. The action now locks the payment, performs current locking reads of its completed refund rows, and derives Partially Refunded versus Refunded from the cumulative minor-unit total. This fixes the payment status projection only; it does not implement the missing partial-refund UI or provider/allocation contract. No historical payment/refund records were repaired, and this source-only change has not been runtime-verified.

**Additional confirmed concurrency defect:** InitiateRefundAction previously summed non-failed refund reservations before opening its creation transaction, so concurrent requests with different idempotency keys could both pass the over-refund check against the same payment. It now locks the payment first, performs a plain current reservation sum (including pending and processing refunds), and creates the reservation in that transaction; the payment lock is released before ProcessRefundAction invokes the gateway. The reservation aggregate stays non-locking to avoid reversing the processor's refund-then-payment lock order. Current callers do not wrap the action in an outer transaction. The existing Refund idempotency column now stores a bounded actor/payment/header-key prefix plus the existing HTTP request fingerprint; matching retries reuse the persisted refund status without dispatching the gateway again, including when the middleware claim was removed after an exception. Mismatched amount, currency, reason code, or request fingerprint returns 409. This is a source-level correction only; the no-test/no-check instruction prevented runtime verification. The existing full-only request guard remains, including for payments in Partially Refunded state; no partial-refund policy or UI is claimed as implemented.

**Idempotency rollout note:** IdempotencyKeyMiddleware now includes HTTP method and concrete request URI in its existing route/body hash, preventing same-user/key/body responses from crossing booking path parameters. Previously cached `idempotency_keys` rows use the old hash and will return the existing 409 conflict response until their configured HTTP TTL expires (24 hours by default); no legacy-hash fallback was added. No production rows or services were changed.

**Smallest complete plan:** Expose/refine full refund initiation on both booking and payment views with permission checks, captured amount/currency, prior refund state, bilingual reason, confirmation and timeline. For partial refunds retain the exact issue scope: obtain provider-supported partial-refund semantics, idempotency and allocation policy, then implement matching gateway/ledger/commission/VAT/booking-status handling; never remove the guard only to make a form accept amounts.

**Required gates / external input:** Required provider/product input for partial Paymob refunds and allocation/rounding. Verify captured/authorized/failed payments, duplicate/in-flight refunds, over-refund, policy-ineligible rental/sale/digital, gateway failure/retry, append-only balanced ledger and browser visibility. Full-only delivery cannot close this whole issue.

**Source anchors:**

- `app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/PaymentsRelationManager.php::table`
- `app/Modules/Payments/Application/Actions/InitiateRefundAction.php::execute`
- `app/Modules/Payments/Application/Actions/ProcessRefundAction.php::execute`

## #60 — [P1] Add restricted-chat moderation actions and report context

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/60)

**Disposition:** Existing moderation wiring feasible; external message-source acceptance remains.

**Current evidence:** FreezeChatAction and UnfreezeChatAction Filament wrappers exist with chat_moderation permissions, bilingual reasons and domain actions. ViewChatThread::getHeaderActions currently returns []; ChatThreadResource reads mirrored chat_message_log.body and uses a placeholder only if absent.

**Smallest complete plan:** Wire both existing moderation wrappers on the detail page without adding generic edit/delete. Add report/booking/participants/context links with permission-aware access, display mirrored message content and distinguish unavailable content from empty conversation. Audit sensitive content access and moderation; enforce server-side permissions and unfreeze window. Backfill/sync actual Firestore content only through the existing integration contract.

**Required gates / external input:** Verify unauthorized access, freeze/unfreeze allowed-window and repeated action, privacy-safe context, read access audit, mirrored content and unavailable-source state. Provider/emulator proof required for real Firestore synchronization; a placeholder replacement with invented text cannot satisfy scope.

**Source anchors:**

- `app/Modules/Communication/Filament/Resources/ChatThreadResource/Pages/ViewChatThread.php::getHeaderActions`
- `app/Modules/Communication/Filament/Actions/FreezeChatAction.php::make`
- `app/Modules/Communication/Filament/Actions/UnfreezeChatAction.php::make`
- `app/Modules/Communication/Filament/Resources/ChatThreadResource.php::infolist`

## #61 — [P1] Add reference search to operational and financial lists

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/61)

**Disposition:** Feasible using resource-specific query predicates.

**Current evidence:** UserResource already makes name/email/phone/public references searchable. BookingResource and PaymentsRelationManager contain searchable reference columns; gaps are not universal. Financial and support resources are independent tables with distinct actor scopes.

**Smallest complete plan:** Inventory the requested operational/financial lists, add booking public-reference, customer email/phone, vendor/business and record public-ID search to each relevant query using existing relations and scopes. Preserve literal/reference matching and existing filters; eager-load displayed labels. Keep global-search QA40 separate.

**Required gates / external input:** Verify each requested list with exact public reference and EN/AR name, phone/email, unknown query, pagination and restricted actor. Avoid broad unscoped OR predicates or exposing internal IDs. Search must visibly work, not merely set searchable on an unrelated column.

**Source anchors:**

- `app/Modules/Identity/Filament/Resources/UserResource.php::table`
- `app/Modules/Booking/Filament/Resources/BookingResource.php::table`
- `app/Modules/Payments/Filament/Resources/PaymentResource.php`
- `app/Modules/Settlement/Filament/Resources/WithdrawalResource.php`

## #62 — [P1] Add scoped bulk actions beyond service lists

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/62)

**Disposition:** Feasible only around approved per-record operations; unsupported triage needs definition.

**Current evidence:** Catalog has existing bulk moderation wrappers. CustomerResource bulkActions is empty; Identity vendor/document review, SupportTicketResource and ReportResource have independent state/permission surfaces. Selection support in one template table does not prove these queues have bulk domain operations.

**Smallest complete plan:** Add selected-record bulk approve/reject/review/triage only where corresponding per-record action exists. Authorize every record, validate state/reason, delegate to its action and return succeeded/skipped/failed results per record; do not wrap many unrelated records in an opaque all-or-nothing UI callback. Define the target support/report triage operations that do not yet have a domain action.

**Required gates / external input:** Verify mixed permitted/forbidden/stale states, selection across pages, double-click/retry, per-record feedback, audit and bilingual reasons. Product input only for new triage transitions, not a reason to delay existing-action bulk wiring.

**Source anchors:**

- `app/Modules/Catalog/Filament/Actions/BulkApproveServicesAction.php`
- `app/Modules/Identity/Filament/Resources/VendorApprovalQueueResource.php`
- `app/Modules/Identity/Filament/Resources/VendorDocumentFilamentResource.php`
- `app/Modules/Support/Filament/Resources/SupportTicketResource.php`
- `app/Modules/TrustSafety/Filament/Resources/ReportResource.php`

## #63 — [P2] Add operational analytics and conversion funnel

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/63)

**Disposition:** Operational counters feasible; conversion measurement contract needs decisions.

**Current evidence:** AdminDashboardSectionsWidget already aggregates captured payments, completed refunds, commissions, queue attention and daily totals. Service view writes analytics_events; booking states exist. This does not prove an end-to-end attributed funnel dataset exists.

**Smallest complete plan:** Build period-aligned booking/vendor/payment KPIs and previous-period comparisons from existing facts. Define funnel event stages, identity/session attribution, denominator and conversion window; instrument missing stages through existing analytics persistence and show coverage/empty-state disclosures. Do not infer conversion from a total booking count alone.

**Required gates / external input:** Product input: funnel stages and attribution window. Verify bounded dates/timezone, same previous-period length, refunds/cancellations, authorized metric visibility and honest no-data/partial-instrumentation states. No synthetic counts in production.

**Source anchors:**

- `app/Modules/Shared/Filament/Widgets/AdminDashboardSectionsWidget.php`
- `app/Modules/Catalog/Http/Controllers/Customer/ServiceDiscoveryExtrasController.php::trackView`
- `app/Modules/Booking/Domain/Enums/LifecycleStatus.php`

## #64 — [P2] Add internal notes tags and contact history

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/64)

**Disposition:** Requires new approved internal-note/tag data contract; booking notes alone insufficient.

**Current evidence:** Booking has AppendCustomerBookingNoteAction and administrative intervention records; those are event/customer notes, not a shared vendor/customer/ticket CRM contact history. Existing support and account pages can provide scoped relation context.

**Smallest complete plan:** Define internal notes author visibility/retention, tags, contact activity types and eligible subjects. Add smallest module-owned persistence/action and permissioned relation UI with append-only authorship/history; reuse existing audit/timeline and record links. Keep internal text out of customer/vendor API/resources and notifications.

**Required gates / external input:** Product input: visibility, retention, tagging and contact-event semantics. Any new schema must have reviewed migration and module boundary. Verify allowed staff versus vendor/customer denial, authorship/edit history, tagging and cross-subject ownership. Do not close using booking notes alone.

**Source anchors:**

- `app/Modules/Booking/Application/Actions/AppendCustomerBookingNoteAction.php`
- `app/Modules/Booking/Domain/Models/BookingAdminIntervention.php`
- `app/Modules/Support/Filament/Resources/SupportTicketResource.php`
- `app/Modules/Shared/Application/Actions/BuildAuditTimelineAction.php`

## #65 — [P2] Add communication and payment setup checklist

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/65)

**Disposition:** Checklist feasible; actual provider readiness needs credentials/controlled test targets.

**Current evidence:** CommunicationProviderHealthPage already exposes passive channel configuration/last-success/failure cards and gated test push/SMS/WhatsApp/email actions. Rendering intentionally never contacts providers; historical success is not live reachability. Paymob readiness belongs to Payments integration.

**Smallest complete plan:** Reuse health cards in a setup checklist covering Email/SMS/Paymob/Firebase, show missing configuration categories without values, link permitted settings, distinguish configured/untested/degraded/verified and provide deliberate safe test actions with recipient confirmation. For Paymob use a provider sandbox verification workflow rather than charge a real customer.

**Required gates / external input:** Provider input: authorized test destinations, sandbox accounts and credentials configured outside issue text. Verify no external calls on page render, denial without permission, redacted output and dispatch/gateway result evidence. Merely displaying green because a config value exists is not closure.

**Source anchors:**

- `app/Modules/Communication/Filament/Pages/CommunicationProviderHealthPage.php::providerCard`
- `app/Modules/Communication/Filament/Pages/CommunicationProviderHealthPage.php::getHeaderActions`
- `app/Modules/Communication/Filament/Pages/ManageProviderSettings.php`
- `app/Modules/Payments/Domain/Contracts/PaymentGateway.php`

## #66 — [P2] Add admin MFA and account-security setup

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/66)

**Disposition:** MFA enforcement requires security policy and existing-stack capability verification.

**Current evidence:** AdminPanelProvider uses IsolatedPanelLogin and web guard; scoped search found a two_factor_secrets migration but no panel MFA enrollment/enforcement call. A schema table is not MFA implementation. Dependency remains locked to the current Filament line.

**Smallest complete plan:** Define mandatory versus optional admin roles, supported factor, enrollment/recovery/re-auth policy and lockout handling. Inspect installed auth packages for reusable TOTP capability; implement complete enrollment, confirmation, encrypted secret, recovery codes, login challenge, reset and enforcement on admin entry. Do not add a new package without explicit approval.

**Required gates / external input:** Product/security input: recovery authority, required roles and factor policy; dependency approval if installed stack lacks factor support. Verify challenge cannot be bypassed by remembered/session login, secret encryption, recovery one-time use, reset audit and lockout. Profile/password UI is separate QA29 scope.

**Source anchors:**

- `app/Providers/Filament/AdminPanelProvider.php::panel`
- `app/Modules/Identity/Filament/Auth/IsolatedPanelLogin.php`
- `app/Modules/Identity/Database/Migrations/2026_01_01_000017_create_two_factor_secrets_table.php`

## #67 — [P2] Show upcoming vendor-document expirations and alerts

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/67)

**Disposition:** Near-expiry view feasible; alert cadence/channel contract required.

**Current evidence:** VendorDocumentFilamentResource displays expires_at and has an expiry editor; CheckDocumentExpiryCommand, AutoSuspendForExpiredDocAction and SendDocExpiredNotificationListener handle expired documents. Near-expiry and already-expired are different lifecycles.

**Smallest complete plan:** Add a 30-day upcoming-expiry filter excluding null/already-expired values and permissioned admin queue. Add scheduled deduplicated admin/vendor reminders using existing dispatcher/templates and document subject links; distinguish critical and noncritical documents and avoid pre-expiry suspension.

**Required gates / external input:** Confirm reminder cadence/channels and recipients; provider verification only for actual delivery. Verify timezone/date boundaries day 0/30/31, reupload/replaced docs, repeat schedule idempotency, critical status and missing expiry. Both view and alerts must exist to close.

**Source anchors:**

- `app/Modules/Identity/Filament/Resources/VendorDocumentFilamentResource.php::table`
- `app/Modules/Identity/Console/Commands/CheckDocumentExpiryCommand.php`
- `app/Modules/Identity/Application/Actions/AutoSuspendForExpiredDocAction.php`
- `app/Modules/Identity/Application/Listeners/SendDocExpiredNotificationListener.php`

## #68 — [P2] Link audit entries to records and show sensitive changes

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/68)

**Disposition:** Record linking and safe diffs feasible; secret redaction mandatory.

**Current evidence:** ActivitylogResource formats subject type/public ID and exposes view action; no record URL is attached by its subject-column formatter. Shared BuildAuditTimelineAction exists. Generic log properties may contain configuration changes and must not be printed wholesale.

**Smallest complete plan:** Resolve known subject types to authorized real resource pages, show missing/deleted/forbidden fallback, and render allowlisted before/after fields for settings changes with secret values replaced by changed/not-changed markers. Retain append-only events and actor/time context; leave export QA32 as distinct requirement.

**Required gates / external input:** Verify target-resource permissions, deleted subjects, unknown model classes, secret/key/password/token nested redaction and bilingual labels. Never expose a secret even to an admin through a diff; log redaction must occur before persistence where possible.

**Source anchors:**

- `app/Filament/Resources/ActivitylogResource.php::getSubjectTypeColumnComponent`
- `app/Modules/Shared/Application/Actions/BuildAuditTimelineAction.php`
- `app/Modules/Shared/Filament/Pages/ManageSettings.php`
- `app/Modules/Communication/Filament/Pages/ManageProviderSettings.php`

## #69 — [P2] Add queue age SLA indicators and oldest-first views

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/69)

**Disposition:** Age/oldest-first feasible; new SLA thresholds require product values.

**Current evidence:** Communication CheckVendorApprovalSlaCommand exists for vendor approvals; bookings already have vendor response deadlines. Withdrawals/support/reports have their own creation/state fields and cannot inherit an invented universal SLA.

**Smallest complete plan:** Display elapsed queue age and oldest-first sort on approval, withdrawal, report and support queues. Reuse actual deadlines where present; add configured threshold indicators for the remaining queues only after approved values. Distinguish paused/closed states and disclose timezone/window.

**Required gates / external input:** Product input: per-queue SLA durations, pause rules, escalation recipients and working-hours behavior. Verify age boundaries, closed/exempt state, oldest-first stable pagination and cross-role visibility. Age-only is partial delivery of the complete issue.

**Source anchors:**

- `app/Modules/Communication/Console/CheckVendorApprovalSlaCommand.php`
- `app/Modules/Identity/Filament/Resources/VendorApprovalQueueResource.php`
- `app/Modules/Settlement/Filament/Resources/WithdrawalsQueueResource.php`
- `app/Modules/Support/Filament/Resources/SupportTicketResource.php`
- `app/Modules/TrustSafety/Filament/Resources/ReportResource.php`

## #70 — [UX] Simplify admin navigation and add favorites

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/70)

**Disposition:** Navigation grouping feasible; favorites/recent persistence contract needs a small approved choice.

**Current evidence:** AdminPanelProvider configures navigation groups and discovers module resources. The report 101-item count is historical, not recounted here. Module navigation declarations are independent; no favorites persistence found in scoped provider review.

**Smallest complete plan:** Regroup related operational pages without losing permissioned entries; consolidate typed import navigation into existing tabs/pages, move infrequent config to Advanced, add per-admin favorites and recent pages. Use browser-scoped persistence only if accepted; server persistence needs a small reviewed schema. Favorites must resolve current permissions on every render.

**Required gates / external input:** Choose persistence scope and clear behavior; no arbitrary hiding of functionality. Verify all existing permitted routes remain reachable, permission revocation removes stale shortcuts, EN/AR labels, keyboard navigation and mobile sidebar. Grouping alone does not fulfill favorites/recent.

**Source anchors:**

- `app/Providers/Filament/AdminPanelProvider.php::panel`
- `app/Modules/Catalog/Filament/Pages/ImportRentalServicesPage.php`
- `app/Modules/Catalog/Filament/Pages/ImportSaleServicesPage.php`
- `app/Modules/Catalog/Filament/Pages/ImportDigitalServicesPage.php`

## #71 — [UX] Improve list columns action visibility and contrast

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/71)

**Disposition:** Feasible within existing Filament tables.

**Current evidence:** BookingResource columns and actions are explicit; admin Table::configureUsing sets page sizes/search and empty states. Current layout must be rechecked in browser to establish offscreen actions and contrast, not inferred from source.

**Smallest complete plan:** Choose useful booking/vendor/date/status defaults per operational resource, make important row actions accessible without horizontal travel, allow persistent column choice using current Filament support, and adjust status badge/color contrast using theme tokens. Preserve full searchable/exportable data and action authorization.

**Required gates / external input:** Browser evidence at desktop/390px in EN/AR; keyboard and screen-reader action labels, contrast measurement, saved-column reset, role visibility, pagination. A column addition alone is not completion of action accessibility/contrast.

**Source anchors:**

- `app/Modules/Booking/Filament/Resources/BookingResource.php::table`
- `app/Providers/Filament/AdminPanelProvider.php::boot`
- `app/Modules/Settlement/Filament/Resources/WithdrawalResource.php`

## #72 — [UX] Add date amount vendor filters and saved views

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/72)

**Disposition:** Resource filters feasible; permission-aware saved views require explicit persistence.

**Current evidence:** Filament resource filters are table-local; BookingsMonitorResource is the existing operational example. Requested date/amount/vendor filters must bind native money and relationships rather than generic text.

**Smallest complete plan:** Add relevant date-range, integer-minor-unit amount and vendor filters per financial/operational table plus quick status tabs. Persist named saved views per admin with current-role permission checks and validated filter schema; decide browser or server scope without a new dependency.

**Required gates / external input:** Choose saved-view persistence/ownership policy. Verify inclusive date/timezone bounds, currency-aware amounts, combined filters, stale/deleted vendor, role revocation, sharing prohibition unless requested and empty/reset states.

**Source anchors:**

- `app/Modules/Booking/Filament/Resources/BookingsMonitorResource.php`
- `app/Modules/Payments/Filament/Resources/PaymentResource.php`
- `app/Modules/Settlement/Filament/Resources/WithdrawalResource.php`
- `app/Modules/Loyalty/Filament/Resources/LoyaltyRedemptionResource.php`

## #73 — [UX] Provide actionable errors and reference IDs

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/73)

**Disposition:** Feasible safe error presentation; do not erase actual failures.

**Current evidence:** bootstrap/app.php already renders authentication, fulfillment and HttpException API errors; many action callbacks handle exceptions locally. PaymentsRelationManager currently displays raw exception messages in Capture/Void/Refund notifications.

**Smallest complete plan:** Add correlation IDs to unexpected error logging and user-safe response/notification text, retain field validation and known domain messages, and provide operation/retry/support context. Replace raw third-party exception exposure with sanitized localized messages and a reference. Keep existing individual bug issues open until their cause is fixed.

**Required gates / external input:** Verify 401/403/404/409/422/500 plus provider exception paths, log/reference correspondence, no secrets/stack traces, API envelope and bilingual UI. Friendly 500 text cannot close underlying QA failures.

**Source anchors:**

- `bootstrap/app.php`
- `app/Modules/Shared/Http/ApiResponse.php`
- `app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/PaymentsRelationManager.php::table`

## #74 — [UX] Replace raw identifiers and translation keys with readable linked labels

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/74)

**Disposition:** Feasible via existing translated labels/resource links.

**Current evidence:** ActivitylogResource already humanizes recognized event/subject types and falls back for legacy records; UserResource supplies translation fallback. Multiple operational resources still use public references, which should stay copyable as references rather than disappear.

**Smallest complete plan:** Add human account/vendor/booking labels adjacent to public IDs with authorized record links; repair missing translation labels through existing language files and typed enums. Unknown/deleted relations get clear fallback. Retain public-ID copy affordance and internal-ID privacy. Keep QA7/11/41/46 fixes separately tracked.

**Required gates / external input:** Verify EN/AR resolved labels, long RTL text, missing/deleted relations, denied target link, no raw translation keys and no N+1 queries. Humanizing an enum string is insufficient if the expected label is a linked related record.

**Source anchors:**

- `app/Filament/Resources/ActivitylogResource.php`
- `app/Modules/Identity/Filament/Resources/UserResource.php`
- `app/Modules/Booking/Filament/Resources/BookingResource.php`
- `app/Modules/Communication/Filament/Resources/NotificationDispatchResource.php`

## #75 — [UX] Add mobile card views for admin lists

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/75)

**Disposition:** Feasible using native responsive table composition; scope all affected admin lists.

**Current evidence:** Filament tables are declared by each module Resource; no universal mobile-card view exists in inspected global Table configuration. The historical 390px report needs fresh layout evidence.

**Smallest complete plan:** Implement native responsive stacked/card presentation for key operational lists using existing Filament table layout components and responsive columns, with reference, actor, date/status and primary action visible. Keep desktop tables, filters/sort/pagination/selection and all permission checks. Apply to the requested admin lists, not just a demonstration card.

**Required gates / external input:** Browser proof 390px/desktop EN+AR, keyboard reading order, labels, record actions, bulk selection and long content. Verify no required field becomes unreachable and avoid injecting a second independent data-query/UI path.

**Source anchors:**

- `app/Providers/Filament/AdminPanelProvider.php::boot`
- `app/Modules/Booking/Filament/Resources/BookingResource.php::table`
- `app/Modules/Identity/Filament/Resources/VendorApprovalQueueResource.php`
- `app/Modules/Payments/Filament/Resources/PaymentResource.php`

## #76 — [UX] Improve RTL phone and language-switcher formatting

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/76)

**Disposition:** Feasible presentation-only change.

**Current evidence:** AdminPanelProvider sets EN/AR locales with LanguageSwitch and one date-time format; phone fields/resources currently render ordinary text. No live visual claim is made.

**Smallest complete plan:** Isolate E.164 phone strings, references and machine-readable dates as LTR within RTL layouts; use consistent localized display format and explicit English/العربية language labels in the existing switcher. Preserve field stored values and locale behavior.

**Required gates / external input:** Browser EN/AR desktop/mobile, plus placement and digit order, copy/paste exact phone, date format consistency and keyboard language switching. Do not convert stored E.164 numbers or UTC timestamps.

**Source anchors:**

- `app/Providers/Filament/AdminPanelProvider.php::boot`
- `app/Modules/Identity/Filament/Resources/UserResource.php::table`
- `app/Modules/Identity/Filament/Resources/CustomerResource.php`

## #77 — [UX] Prioritize attention and align dashboard reporting windows

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/77)

**Disposition:** Attention-first/window disclosure feasible; comparison definitions require clarity.

**Current evidence:** AdminDashboardSectionsWidget mixes all-time captured/refund totals with last-24-hour provider issues and a bounded daily chart. AdminDashboardHeaderWidget is separate. This proves multiple windows, not an arithmetic bug.

**Smallest complete plan:** Move actionable queue alerts before decorative header content, label every metric window and align related chart/table links with the same period. Add previous-period comparisons with equal-length periods only for supported facts. Reuse permissions/cache keys and avoid mixed-currency sums. Keep QA42 mismatch separate until verified.

**Required gates / external input:** Choose all-time versus selectable default reporting period; verify source queries/chart/drilldown window parity, timezone, previous-period zero denominator, cached permission isolation, refund/cancel handling. Reordering banner alone cannot close reporting-window/comparison scope.

**Source anchors:**

- `app/Modules/Shared/Filament/Widgets/AdminDashboardSectionsWidget.php`
- `app/Modules/Shared/Filament/Widgets/AdminDashboardHeaderWidget.php`
- `app/Filament/Pages/Dashboard.php`

## #78 — [UX] Improve date entry and form action consistency

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/78)

**Disposition:** Date-picker portion partly already present; remaining UX consistency feasible.

**Current evidence:** CommissionRulesResource currently imports DatePicker and uses DatePicker::make(effective_from), so historical text-only observation is stale or path-specific. LoyaltyProgramResource has independent edit page. City Region blanking remains explicitly uncertain.

**Smallest complete plan:** Retest exact form paths, keep/finish date picker keyboard behavior, consistent save/cancel placement, deliberate Enter semantics without breaking accessibility, and named-record delete confirmation across requested settings forms. Investigate city-region only on reproducible current evidence; do not bundle an unproven data mutation.

**Required gates / external input:** Browser pointer/keyboard EN+AR date selection, Enter/escape, cancelled unsaved state, named delete target, Loyalty create/edit consistency. Respect valid native form submission; do not globally disable Enter.

**Source anchors:**

- `app/Modules/Settlement/Filament/Resources/CommissionRulesResource.php::form`
- `app/Modules/Loyalty/Filament/Resources/LoyaltyProgramResource.php`
- `app/Modules/Geography/Filament/Resources/CityResource.php`

## #79 — [UX] Add impact summaries and reasons for risky admin actions

[Current issue](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/79)

**Disposition:** Feasible UX around existing financial/lifecycle actions; reason persistence must reach action.

**Current evidence:** Payment capture/void already require a reason and confirmation in PaymentsRelationManager; refunds require a reason code. Vendor View suspend action requires confirmation but passed reason handling needs implementation review. ForceCancelBookingAction creates intervention audit. UI adjacency alone does not demonstrate an executed mutation.

**Smallest complete plan:** For Force Cancel/Mark Paid/Suspend show customer/vendor/reference, current state, affected amount/currency and downstream effect summary in deliberate confirmation. Require reason and pass it into existing action/audit; reauthorize/recheck state inside execution, distinguish dangerous action styling and provide truthful result. Address QA26 reason bug in the same scoped action only where independently confirmed.

**Required gates / external input:** Verify denied/stale/double actions, accurate paid/refund consequences, reason stored in audit, bilingual modal and keyboard focus. Financial sandbox/disposable capture proof before claiming completion; never execute real payments as a UI smoke test.

**Source anchors:**

- `app/Modules/Booking/Application/Actions/ForceCancelBookingAction.php`
- `app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/PaymentsRelationManager.php`
- `app/Modules/Identity/Filament/Resources/VendorProfileResource/Pages/ViewVendorProfile.php::getHeaderActions`
- `app/Modules/Identity/Application/Actions/SuspendVendorAction.php`

## Important scope boundaries

- #59 partial refunds are explicitly unsupported today; full-only UI is a partial delivery, not resolution of the full issue.
- #57 API token expiry does not enforce web-session expiry; both paths must be distinguished.
- #65 passive configured/historical health is not a live provider test.
- #66 a two-factor table is not an MFA enrollment/challenge implementation.
- #64 relationship notes/tags require an approved private-data contract; event notes are not a substitute.
- #63/#69/#70/#72 need concrete attribution/SLA/persistence choices for their requested new behavior. Existing factual counters, age sorting and native filters can progress independently.
- #78 effective_from already uses DatePicker in current source; preserve the other form consistency requirements and reproduce any remaining date-entry defect on its exact route.

No validation commands or runtime/browser tests were run for this read-only triage. Current files can change during concurrent remediation; implementers must reread exact touched methods before editing.

## Source-only implementation choices for #57, #58, and #60

These are conservative implementation choices for the reported gaps, not accepted ADRs or new product policy. They do not close the GitHub issues or replace runtime acceptance.

- **#57:** Browser impersonation uses the existing vendor session guard while keeping the administrator on the web guard. It requires the existing `impersonate_vendor` permission, an active administrator, an active email-verified vendor with an approved profile, a bilingual reason, and a non-nested session capped at 30 minutes. Start, end, expiry, and permission-revocation events are appended to `audit_logs`; the vendor portal banner exposes the end control. The separate API-token action stays a distinct flow.
- **#58:** Management applies only to existing user rows. Contact edits are super-admin-only and clear the corresponding email/phone verification timestamp when that contact changes. Staff role changes are limited to the already seeded `admin`, `super_admin`, and `booking_manager` roles, with self-demotion and last-active-super-admin guards. Password reset links use the existing Laravel broker and the account’s current Filament panel. Account invitations and user/vendor creation are excluded because the checkout has no approved invitation policy or general vendor-profile creation path; existing vendor accounts without a registered profile are rejected.
- **#60:** The existing permission-gated freeze/unfreeze actions are exposed on restricted-chat detail. Flag detail links to its related chat and shows booking/participant metadata; detail reads append audit rows. No new message body is loaded, and message-log or redaction behavior is unchanged.

The requested follow-up was source-only. No automated tests, static checks, browser work, or application commands were run for these implementation hunks.
