# GitHub verification ledger - 2026-10-13

Base: `4b086b58a23cf122bfb5667e9aacce0137665899` (main after PR195).

Objective: finish every currently open GitHub task, including all 22 QA-VERIFY-FLOW issues. The initial snapshot contains 60 open issues. Local tests and disposable local runtime are authorized. Production deployment, real charges/payouts and external notifications remain outside authorization.

Luna/xhigh agents own catalog, finance/booking, identity/privacy, content/admin, customer enhancements, discovery, admin operations, admin UX and audit/feedback. Each issue requires its full checklist; passing source tests alone does not prove browser or external-provider requirements.

## Initial issue inventory

| Issue | Requirement | Current status |
| --- | --- | --- |
| [#192](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/192) | [LOW] Admin service edit omits category-specific fields shown publicly | Pending local verification |
| [#191](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/191) | [LOW] Service edit gallery lists one uploaded image twice | Pending local verification |
| [#187](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/187) | [MEDIUM] Required vendor subcategory can take 20 seconds or have no options | Pending local verification |
| [#183](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/183) | [HIGH] Admin vendor approval never applies through any UI path (was #177, closed) | Three admin browser paths passed locally; publication/production retest pending |
| [#182](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/182) | [LOW] Admin modals take 6-10 seconds to open during vendor review | Local modal measurements 1312-2341 ms; production retest pending |
| [#178](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/178) | [Enhancement] Provide an admin workflow for service packages or clarify vendor-only setup | Pending local verification |
| [#172](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/172) | [Enhancement] Let admins decide or reassign pending vendor booking requests | Pending local verification |
| [#169](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/169) | [VERIFY-FLOW] Verify admin transportation-line addition and booking totals | Pending local verification |
| [#168](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/168) | [VERIFY-FLOW] Verify admin failed-payment recovery and abandonment controls in sandbox | Pending local verification |
| [#167](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/167) | [VERIFY-FLOW] Verify City create edit delete after region-selector interruption | Pending local verification |
| [#166](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/166) | [VERIFY-FLOW] Verify notification-template edit save and event rendering | Pending local verification |
| [#165](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/165) | [VERIFY-FLOW] Verify campaign creation scheduling and test-only delivery | Pending local verification |
| [#164](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/164) | [VERIFY-FLOW] Verify navigation-menu edits and public link propagation | Pending local verification |
| [#163](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/163) | [VERIFY-FLOW] Verify existing home-block editing and storefront order | Pending local verification |
| [#162](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/162) | [VERIFY-FLOW] Verify bilingual CMS page edits reach the storefront | Pending local verification |
| [#161](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/161) | [VERIFY-FLOW] Verify admin service moderation and lifecycle actions for all product types | Pending local verification |
| [#160](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/160) | [VERIFY-FLOW] Verify service-theme configuration and storefront propagation | Pending local verification |
| [#159](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/159) | [VERIFY-FLOW] Verify service package configuration and booking propagation | Pending local verification |
| [#158](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/158) | [VERIFY-FLOW] Verify new occasions propagate into vendor service selectors | Pending local verification |
| [#157](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/157) | [VERIFY-FLOW] Verify occasion validation deactivation and in-use deletion | Pending local verification |
| [#156](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/156) | [VERIFY-FLOW] Verify valid admin create forms resist double submission | Pending local verification |
| [#155](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/155) | [VERIFY-FLOW] Verify response-level payload privacy and over-fetching across roles | Pending local verification |
| [#154](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/154) | [VERIFY-FLOW] Verify post-completion customer review and moderation lifecycle | Pending local verification |
| [#153](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/153) | [VERIFY-FLOW] Verify fresh customer SMS registration and account activation | Browser activation/account/API login passed with local OTP stub; real SMS not verified |
| [#152](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/152) | [VERIFY-FLOW] Verify booking settlement wallet withdrawal and payout lifecycle | Pending local verification |
| [#151](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/151) | [VERIFY-FLOW] Verify vendor acceptance rejection and booking-state propagation | Pending local verification |
| [#150](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/150) | [VERIFY-FLOW] Verify vendor Excel import and import-history lifecycle | Pending local verification |
| [#149](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/149) | [VERIFY-FLOW] Verify vendor service Clone and Archive lifecycle | Pending local verification |
| [#148](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/148) | [VERIFY-FLOW] Prove customer payment and refund lifecycle in a gateway sandbox | Pending local verification |
| [#147](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/147) | [Enhancement] Send customers a booking confirmation notification | Focused local dispatch/retry tests passed; browser/provider delivery not verified |
| [#146](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/146) | [Enhancement] Add a vendor portfolio gallery to service and vendor pages | Focused local EN/AR ownership/privacy tests passed; browser pending |
| [#145](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/145) | [Enhancement] Show event-date availability and an upfront service price estimate | Pending local verification |
| [#144](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/144) | [Enhancement] Add customer-facing FAQ and help pages | Focused local EN/AR FAQ and ticket tests passed; browser pending |
| [#143](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/143) | [Enhancement] Show cancellation and refund terms before booking confirmation | Focused local EN/AR CMS policy tests passed; browser pending |
| [#142](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/142) | [Enhancement] Let customers view or download an invoice from booking details | Focused local ownership, totals, refund, and privacy tests passed; browser pending |
| [#141](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/141) | [Enhancement] Add a pre-booking customer-to-vendor contact path | Pending local verification |
| [#134](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/134) | [INFO] Customer account lacks addresses, saved searches, loyalty and chat pages; admin tutorials empty; Admin role permission count differs by 1 | Pending local verification |
| [#130](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/130) | [MEDIUM] Subscription invoices all EGP 99 regardless of plan; one Premium has no invoice; payments page empty | Pending local verification |
| [#124](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/124) | [LOW] Occasion icon upload switches from FilePond on create to a raw input on edit | Pending local verification |
| [#108](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/108) | [INFO] Settlement Wallets contains ownerless Legacy or Unknown wallets with negative balances | Pending local verification |
| [#100](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/100) | [INFO][Unconfirmed] Admin homepage returned 500 after admin sign-in | Reproduced and fixed locally; regression and browser passed; publication pending |
| [#85](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/85) | [MEDIUM] Pending booking stays Pending after response deadline expired | Pending local verification |
| [#79](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/79) | [UX] Add impact summaries and reasons for risky admin actions | Pending local verification |
| [#76](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/76) | [UX] Improve RTL phone and language-switcher formatting | Pending local verification |
| [#75](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/75) | [UX] Add mobile card views for admin lists | Pending local verification |
| [#74](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/74) | [UX] Replace raw identifiers and translation keys with readable linked labels | Pending local verification |
| [#73](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/73) | [UX] Provide actionable errors and reference IDs | Pending local verification |
| [#72](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/72) | [UX] Add date amount vendor filters and saved views | Pending local verification |
| [#71](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/71) | [UX] Improve list columns action visibility and contrast | Pending local verification |
| [#70](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/70) | [UX] Simplify admin navigation and add favorites | Pending local verification |
| [#69](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/69) | [P2] Add queue age SLA indicators and oldest-first views | Pending local verification |
| [#68](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/68) | [P2] Link audit entries to records and show sensitive changes | Pending local verification |
| [#64](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/64) | [P2] Add internal notes tags and contact history | Pending local verification |
| [#63](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/63) | [P2] Add operational analytics and conversion funnel | Pending local verification |
| [#62](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/62) | [P1] Add scoped bulk actions beyond service lists | Pending local verification |
| [#42](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/42) | [INFO] Admin lists lack CSV or Excel export | Booking monitor scoped export implemented; feature/browser checks passed; publication pending |
| [#32](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/32) | [INFO] Admin list pages deliver large HTML payloads | Pending local verification |
| [#30](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/30) | [INFO] Communication providers are unconfigured and onboarding is blocked | Pending local verification |
| [#3](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/3) | [HIGH] Admin account accepts a trivially weak default password | Pending local verification |
| [#1](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/1) | [CRITICAL] Vendor verification and password-reset emails are not delivered | Pending local verification |

## Evidence locations

- `admin-dashboard.md`: reproduced homepage exception, regression and browser verification.

- `catalog.md`: catalog lifecycle, taxonomy, import and media.
- `finance.md`: booking, payment, settlement, reviews and admin controls.
- `identity.md`: activation, approval, privacy and provider gates.
- `customer-enhancements.md`: customer invoice, refund policy, help, gallery and booking notification evidence.
- `content.md`: CMS, navigation, home, campaigns, templates and geography.
- `backlog-audit.md`: remaining enhancements and UX acceptance.
- `admin-operations.md`: filtered booking export, selected vendor approval and booking funnel/queue measurements.
- `admin-ux.md`: navigation, filters, mobile cards and locale checks; browser failures remain open.
- `customer-enhancements.md`: invoices, help/refund terms, portfolio and submitted notification checks.

## Current integration gate

The owner explicitly requested closing successfully tested issues on GitHub before the remaining batch finishes. Issues #42, #100, #141, #142, #143, #144, #146, #147, #155, #182 and #183 were closed with comments recording local evidence and the publication/provider/production boundaries. Their source changes in this batch are still uncommitted and unpushed; closure is local acceptance, not release acceptance. The inventory above is the initial snapshot, not current GitHub state.

The closure regression run passed 50 tests / 399 assertions in 26.79 seconds, covering customer help/policy/invoice, portfolio ownership/privacy, notification replay/retry, dashboard, approval lifecycle and mounted approval/checklist queries. Chromium customer help/policy/portfolio/invoice acceptance passed in EN/AR (1 test, 25.9 seconds). The existing admin approval Chromium test passed all three approval surfaces and an ineligible-vendor error case; measured modal readiness was 2341/1800/1312 ms. Admin dashboard browser acceptance previously passed 12 screens.

Additional closure checks passed 26 tests / 164 assertions for discovery/inquiries (18.24 seconds) and 7 tests / 50 assertions for admin operations (4.92 seconds). The inquiry Chromium case passed customer question, owning vendor reply, and customer reading the reply (13.2 seconds). Its separate availability case failed, so #145 remains open. The earlier operations Chromium run passed the actual Excel download (27.0 seconds total); the later expanded SLA fixture failed before export interaction. Issue #42 records only the requested initial booking operations export, not exports on all lists. See [discovery.md](discovery.md) and [admin-operations.md](admin-operations.md).

No commit, push or deployment has occurred. The latest full Pest `--bail` run stopped with 427 passed, 1 failed and 825 pending (3598 assertions, 242.72 seconds). Its failing idempotency filter test now uses the actual browser select values; that focused suite passed 5 tests / 50 assertions. The full gate must still be rerun after unfinished implementation/review work. Earlier PHPStan passed with 1 GB; final checks remain outstanding. The current Vite build passed with existing unresolved runtime font references.

User decisions: campaign quiet-hours messages are skipped permanently; alternative vendors require customer consent and preserve original prices/deadlines until acceptance; admins create purchasable packages on the vendor's behalf; approval/withdrawal/report/support queue SLAs default to 48 hours and are editable; add customer addresses/saved searches/loyalty/chat pages and hide the empty tutorial launcher until approved content exists.

Do not close an issue without matching evidence for each required case. Record FAIL and BLOCKED cases explicitly.
