# InstaParty open-issue backlog audit — 2026-10-13

## Scope and evidence

Audited the 60 open issue records and their exported comments in `C:/Users/N/.codex/worktrees/issues20-publish-01a133b7/open-issues-qa-20261013.json` against source at `HEAD=4b086b58a23cf122bfb5667e9aacce0137665899` (`codex/qa-verify-flows`). The audit checkout had no `.codegraph/`; I used the checkout code map and scoped source reads. The shared worktree acquired concurrent edits during this audit; claims below refer to the requested `HEAD` where a relevant file was dirty.

The coordinator split accounts for every exported issue exactly once: this audit covers 21 IDs (`42, 62–64, 68–76, 79, 141–147`); the other assignment covers 39 (`1, 3, 30, 32, 85, 100, 108, 124, 130, 134, 148–169, 172, 178, 182, 183, 187, 191, 192`). The union is 60/60, with no duplicate assignment or unassigned issue. This is scope coverage, not a claim that other agents have finished their work.

The open-issue export is authoritative for current status: all listed items remain open. The October 4 handoff backlog, September 12 remediation ledger, and Wave 8 purchase-chain results predate these October 12–13 enhancement/UX reports. They provide useful flow context but do not accept or close these newer items. No application changes, issue updates, tests, browser sessions, provider calls, or production checks were made for this read-only audit. The repository lacks the locked feature/spec files noted in `docs/ai/checkout-gaps.md`; proposed work below still needs product decisions where called out.

## Admin workflow enhancements

### #42 — Permission-scoped CSV/Excel exports

**Current evidence:** No general admin-resource export was found. Export exists in the vendor wallet ledger (`Settlement/Filament/Vendor/Pages/VendorWalletPage.php`) and Catalog failed-row handling; this matches the report’s warning that some export already exists. The issue itself is report-based and proposes finance/operations as a starting point.

**Acceptance and next step:** Pick the first admin list, then prove that an authorized user can export the active filters (and selected rows if selection is supported), while an unauthorized role cannot. Assert the exported columns and row set, and exclude secrets and unnecessary personal/bank data. Add the focused test beside that resource; do not implement exports across all 101 reported lists in one pass.

**Decision:** Which first resource and columns are approved, and which fields require masking or exclusion?

### #62 — Scoped bulk actions outside service lists

**Current evidence:** Bulk actions already exist for vendor service submit/archive, active-booking fulfillment, notification-template deletion, and admin-inbox batch resolution. The gap is additional, explicitly scoped operations for queues such as vendor/document review and support/report triage, with per-record outcomes.

**Acceptance and next step:** Select one queue and one operation. Test that only selected, still-eligible records are changed, per-record failures are visible, successful records remain successful, and the acting role is checked. Reuse that queue’s existing Action/policy path instead of adding a second mutation path.

**Decision:** Which queue/action is first, and should one failure stop the batch or allow independent records to continue?

### #63 — Operational analytics and conversion funnel

**Current evidence:** The admin dashboard already contains access-aware operational and financial KPIs, a seven-day financial series/comparison, and top-vendor revenue summaries (`Shared/Filament/Widgets/AdminDashboardHeaderWidget.php`, `AdminDashboardSectionsWidget.php`). Separate subscription and advertising analytics also exist. No booking conversion funnel or funnel-stage definitions were found. The issue is a proposed improvement, not evidence that an empty dataset is itself broken.

**Acceptance and next step:** Define the funnel stages and denominator first. Then test a small known dataset for counts/rates over a selected period, permission-aware visibility, and an honest empty state. Do not add a new event pipeline until the required booking-stage data is shown to be missing.

**Decision:** Which funnel stages, attribution window, comparison periods, and roles are in scope?

### #64 — Internal notes, tags, and contact history

**Current evidence:** Bookings have customer-submitted notes and admin intervention notes/timeline entries (`BookingResource.php`, `AdminBookingInterventionResource`); support tickets have their own records. The source does not show a shared private-note/tag/contact-history surface connecting customer, vendor, booking, and ticket records.

**Acceptance and next step:** Before schema work, choose the record types and visibility rules. A focused test should prove note authorship/timestamp, record links, role isolation from customers/vendors, and the chosen edit/delete behavior. Preserve existing customer notes as a separate kind of information.

**Decision:** Which roles can read/write notes, which records can be linked, and are notes append-only or editable?

### #68 — Record-linked audit entries and safe change details

**Current evidence:** `Shared/Domain/Models/AuditLog.php` has a polymorphic `auditable` relation and stores `changes`. Booking detail uses an audit timeline; vendor profile activity shows property changes and recursively masks bank fields in `old`/`new` (`Identity/Filament/Resources/VendorProfileResource/RelationManagers/ActivityLogRelationManager.php`). Admin `/activitylogs` has permission and identifier-presentation coverage (`tests/Feature/ActivityAuditPresentationTest.php`). This is partial coverage, not a single linked admin audit view for every record type. Never render credential/secret values from diffs.

**Acceptance and next step:** Choose the first missing record type. Add a test fixture with an auditable change and confirm the entry links to the correct record, shows only approved before/after fields, masks sensitive data, and respects `view` permission. Keep append-only audit writes unchanged.

**Decision:** Which audit sources/actions and safe fields belong in the first cross-record view?

### #69 — Queue age, SLA, and oldest-first triage

**Current evidence:** The booking negotiation monitor already exposes the nearest vendor response deadline and urgency/overdue state (`Booking/Filament/Resources/BookingsMonitorResource.php`). The source does not establish defined SLAs for the separate vendor approval, withdrawal, report, or support queues in the issue.

**Acceptance and next step:** Choose one queue and specify when its clock starts/stops. Test the age/deadline boundary, oldest-first ordering, overdue state, and localized display using UTC-backed timestamps. Avoid adding an arbitrary deadline to other queues.

**Decision:** SLA duration, pauses/escalations, and operating calendar for each queue remain unspecified.

## Admin UX proposals

### #70 — Navigation grouping, favorites, and recents

**Current evidence:** `AdminPanelProvider.php` already defines nine named groups, most collapsed. Favorites/recent shortcuts and the proposed reorganization are not present in the inspected provider. The issue’s 101-item count is report evidence, not re-counted here.

**Acceptance and next step:** Select the admin roles and top tasks to optimize. A browser check should prove that the chosen groups/favorites are discoverable in English and Arabic and that permission-hidden pages do not appear as shortcuts.

**Decision:** Grouping, favorite ownership (per-user or shared), and the first pages are not specified.

### #71 — List columns, actions, and contrast

**Current evidence:** The booking monitor table currently shows reference, customer, lifecycle, total, submitted time, nearest deadline, and urgency; it does not show vendor or event date in the table source. No sticky action or saved-column behavior was established. Reported contrast and off-screen action concerns require browser evidence.

**Acceptance and next step:** Start with the booking monitor and one other named queue. At desktop and 390px, verify useful default columns, discoverable row actions, keyboard focus, and readable status contrast. Extend `tests/Feature/GitHubBookingsMonitorTest.php` for the data contract, then do the required browser check for layout/contrast.

**Decision:** Which list is first, and which columns/actions should be the role-specific defaults?

### #72 — Date/amount/vendor filters and saved views

**Current evidence:** The booking monitor has lifecycle, payment, product-type, and negotiation-scope filters. Date, amount, vendor filters and named saved views were not found there. Other resources may differ, so the report should not be generalized to every list.

**Acceptance and next step:** Pick one target table. Test each filter independently and together against a known fixture set; test that a saved view restores those filters and remains available only to an authorized user. Add persistence only after saved-view scope is decided.

**Decision:** Target list, saved-view ownership/sharing, and retention are open.

### #73 — Actionable bilingual validation and failure feedback

**Current evidence:** The issue’s owner comment documents hidden validation on inactive bilingual tabs, an approval action without visible outcome, and checklist wording that conflicts with failure copy. It gives specific acceptance criteria: identify invalid tabs/fields, show accessible failure state without false success, preserve input, distinguish validation/permission/server errors, and avoid internal detail leakage. This overlaps the behavior of #183 but should not duplicate its approval-state fix.

**Acceptance and next step:** Add a browser regression for one bilingual form with an invalid inactive tab and one failed action. Assert a tab error marker/count, focus/field feedback, retained input, and an accessible localized failure message; separately assert that a true failed action never shows success styling or a stuck spinner.

**Decision:** Define the reference-ID source/format for server failures; use an existing correlation ID if present rather than inventing a second scheme.

### #74 — Readable linked labels instead of raw IDs/translation keys

**Current evidence:** Translation helpers and localized resources exist, but BookingResource still falls back to `#<customer_id>` and `#<occasion_id>` when labels are unavailable. This issue is broader than those examples and explicitly overlaps older, separately tracked raw-identifier defects.

**Acceptance and next step:** Inventory a short, named list of affected admin tables. For each, test localized record labels and links; when a related row is missing, show a neutral missing-record label plus a stable support reference, never an internal integer ID or untranslated key. Keep fixes for already-open specific reports with their assigned owners.

**Decision:** The first tables and fallback wording are not specified.

### #75 — Mobile card views for admin lists

**Current evidence:** The ticket reports horizontal table scrolling at 390px. Source inspection did not prove a card layout or establish which of the many admin tables should change; no browser check was run.

**Acceptance and next step:** Pick the highest-use list and inspect its current 390px behavior. If a card view is needed, verify its key fields, status, and primary action fit without hiding keyboard/screen-reader access; add a viewport browser regression for that resource.

**Decision:** Priority list and whether cards replace or supplement each table on mobile remain open.

### #76 — RTL phone and language-switcher formatting

**Current evidence:** Admin supports `en`/`ar` language switching and applies HTML direction middleware. Storefront text has an LTR-isolated phone/email component, and the customer booking view explicitly wraps the recipient phone in `bdi dir="ltr"`. This does not verify the reported admin language-switcher/phone defects visually.

**Acceptance and next step:** Reproduce the report in an Arabic admin form/list containing E.164 phone and mixed date/digits. Verify switcher labels, plus sign, phone reading order, and localized dates in both directions; fix only the reproduced surface and record browser evidence.

**Decision:** None required until the exact affected admin surfaces are reproduced.

### #79 — Impact summaries and reasons for risky actions

**Current evidence:** The booking force-cancel modal requires a reason; vendor suspension also collects a reason. The issue’s cancel/mark-paid/suspend walkthrough did not execute those actions, so the report does not prove all three lack safeguards. The action summary (amount/customer/vendor/reference and impact) is still not established by these source checks.

**Acceptance and next step:** Choose the risky actions to include, then inspect each modal in a disposable local database. Before mutation, display the affected record and relevant monetary/status impact; require the approved reason where applicable. Test cancel/mark-paid/suspend separately and assert cancellation/refund/ledger invariants.

**Decision:** Define the action list and which reasons are mandatory. Do not use a live or production account for this check.

## Customer-facing enhancements

### #141 — Pre-booking customer-to-vendor contact

**Current evidence:** The public vendor page has no vendor-specific inquiry/chat CTA in its Blade source. Booking chat is provisioned on `BookingConfirmed`, which is after the pre-booking decision. No public contact route for this purpose was found.

**Acceptance and next step:** Choose inquiry form or pre-booking chat. Test that the inquiry is tied to the selected vendor/service, can be answered through a defined vendor workflow, and exposes no private phone/email unless the vendor opted in. Preserve a link back to the service.

**Decision:** Channel, response handling, expected response-time copy, and opt-in contact policy are open.

### #142 — Customer invoice action from booking details

**Current evidence:** The booking invoice page is registered under the admin web route; the customer booking API has a tax-invoice request action, and the customer details view shows totals/refund details but no invoice view/download action at the audited base. A tax-invoice request is not the same as retrieving an invoice. `resources/views/storefront/account/booking.blade.php` was dirty in the shared checkout; its base version was checked with `git show HEAD`.

**Acceptance and next step:** Add an owner-authorized customer invoice endpoint/view for eligible bookings, plus an explicit unavailable state before an invoice exists. Test correct booking totals/reference, paid status only when captured, denial for another customer, and no leakage of internal IDs. Reuse the existing invoice data action if it provides the needed document.

**Decision:** Decide invoice eligibility and whether customer delivery is printable HTML or a downloadable file; keep tax invoice issuance rules separate.

### #143 — Cancellation/refund terms before confirmation

**Current evidence:** A bilingual editable refund-policy CMS page and its route have focused tests (`tests/Feature/RefundPolicyCmsPageTest.php`). The service detail and cart views show price/booking actions but do not surface the applicable cancellation/refund terms at the decision point. This is separate from the full policy page/404 report.

**Acceptance and next step:** Test the chosen booking confirmation point in both locales. Show the applicable concise terms and a link to the full policy; make the language match the policy actually applied to that booking. Do not add an acknowledgement checkbox unless approved.

**Decision:** Global versus service-specific terms, precedence when they differ, and whether acknowledgement is required need product/legal approval.

### #144 — Customer FAQ and help pages

**Current evidence:** The localized storefront has a CMS `p/{slug}` route, and `CmsPageController` has a built-in `faq` fallback. Support also exposes public FAQ category/search API routes. A separate public Help route/page was not found. This means the source contradicts an unqualified “FAQ route absent” claim, while runtime content/public behavior remains unverified.

**Acceptance and next step:** Test `/en/p/faq` and `/ar/p/faq` with and without a published CMS FAQ record, test the public FAQ API, and check footer/account links. Specify and implement the missing Help destination only after choosing how it uses the existing FAQ data and contact fallback.

**Decision:** Whether the storefront FAQ should render the structured searchable API content or the CMS page, plus the Help route/content structure, remains open.

### #145 — Event-date availability and upfront estimate

**Current evidence:** Catalog has a public `check-availability` API and `CheckServiceAvailabilityController`; its contract explicitly says the check creates no inventory hold. The service page shows a base “from” price and sends customers to the wizard; it has no event-date check or computed estimate UI in the view. Vendor availability calendar (#93) is a separate surface.

**Acceptance and next step:** Wire the service page to the existing availability contract and authoritative pricing/booking calculation. Test available/unavailable dates, relevant rental duration or sale/digital quantity, fees/exclusions, estimate-versus-final wording, and that selected inputs reach the wizard/booking without creating a hold.

**Decision:** Applicable inputs and estimate rules by product type, included fees, and whether any price is a quote rather than an estimate remain open.

### #146 — Vendor portfolio gallery

**Current evidence:** Core portfolio support is present: vendor API routes list/upload/delete portfolio media; uploads accept JPEG/PNG/WebP up to 5 MB; approved vendor pages render portfolio media. `tests/Feature/VendorPublicGalleryTest.php` covers locale rendering, hiding another vendor’s media, and empty gallery controls. That test was not run here. The customer JSON portfolio endpoint uses a separate repository projection of published-service gallery media, so the two surfaces should not be treated as identical.

**Acceptance and next step:** First verify the existing upload/list/delete API and public-page path together, including owner isolation and approved-vendor visibility. Then add moderation only if required; test unapproved/pending media visibility and any captions/tags if those become part of the product decision.

**Decision:** Whether portfolio upload should be moderated before public display, and whether captions/tags are needed, remains open. Core gallery code appears implemented; browser and end-to-end management acceptance is still missing from this audit.

### #147 — Customer booking confirmation notification

**Current evidence:** `OnBookingSubmitted` dispatches customer in-app, push, and email notifications from `booking.submitted`; customer templates exist. The customer payload currently uses the booking public ID and does not set the issue’s requested human booking reference, current status, or direct booking-details URL. Existing submission-page coverage fakes the event; `VendorBookingInAppNotificationTest.php` focuses on the vendor notice. Customer-core notification tests cover `booking.confirmed`, not the submitted customer outcome. Source therefore shows partial implementation, not proven delivery or acceptance.

**Acceptance and next step:** Extend the customer submission test to execute a successful persisted submission and assert exactly one customer-visible notice with human reference, submitted/pending-review status, and a customer-owned booking-details link in EN/AR. Assert failed submit sends none and listener replay does not duplicate. Test dispatch/persistence locally; live email/push delivery remains an external provider/queue gate.

**Decision:** Choose the supported customer channel(s) for the required confirmation and preserve the distinction between “received/pending vendor review” and “confirmed.”

## Suggested follow-up order

Before new implementation, confirm the decisions for #42, #62–64, #68–75, #79, and #141–147. The clearest existing-code verification candidates are #68, #69, #144, #146, and #147. #146 appears to have its core upload/display path already; #147 has a dispatch path with an acceptance-payload/test gap. Keep issue status open until the corresponding local behavior and any required browser or external delivery gate have evidence.
