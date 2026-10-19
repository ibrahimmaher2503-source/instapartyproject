# GitHub #61 — operational and financial reference search implementation

Issue: https://github.com/ibrahimmaher2503-source/instapartyproject/issues/61

Date: 2026-10-13. Local implementation; GitHub remains open. No comments, commits, push, deployment or production-data operations performed.

## Result and exact matched scope

Search now resolves public record/customer/vendor identifiers, booking public identifiers and reference numbers, customer/vendor names, email and E.164 phone, plus English/Arabic vendor business names where the list has those relationships. Existing displayed fields, labels, record actions, permission policies, filters and resource base queries remain authoritative. Additional relationship predicates are grouped so they cannot escape queue state or per-admin ownership constraints. No internal ID is added as a searchable/displayed identity.

| List | Added search context | Existing access/query scope |
|---|---|---|
| BookingResource | Own public ID/reference; customer name/contact/public ID; participating vendor EN/AR name/contact/public ID | Existing admin/booking-manager/super-admin access |
| BookingsMonitorResource | Same booking/customer/vendor references | Existing BookingPolicy permission and negotiation monitor lifecycle query |
| AdminBookingInterventionResource | Same booking/customer/vendor references | Existing booking.intervene.access and trouble-bucket query, excluding completed/cancelled/deleted |
| BookingModificationResource | Proposal public ID; parent booking identity/customer contact; vendor identity/contact | Existing proposal resource policy |
| PaymentResource | Payment public ID/gateway ref; booking identity/customer and participating vendor contacts | Existing payment resource role gate |
| RefundResource | Refund/gateway/payment/booking public reference; customer/vendor contacts | Existing RefundPolicy |
| PaymentAttemptResource | Parent payment/gateway/booking reference; customer/vendor contacts | Existing PaymentAttemptPolicy; attempts have no own public-ID column |
| WithdrawalResource / WithdrawalsQueueResource | Public withdrawal and bank transfer reference; vendor EN/AR name/contact/public ID | Existing WithdrawalPolicy and queue filters/state scope |
| CommissionResource | Commission/payment/item public IDs; booking identity/customer contact; vendor EN/AR name/contact | Existing CommissionPolicy |
| WalletLedgerViewerResource | Wallet public ID; customer or vendor owner public identity/contact; vendor EN/AR name | Existing WalletPolicy; owner type remains paired with owner ID |
| LoyaltyLedgerResource | Entry public ID; customer/vendor identity/contact and vendor EN/AR name | Existing ledger policy |
| LoyaltyRedemptionResource | Redemption/booking public reference; customer/vendor identity/contact and vendor EN/AR name | Existing redemption policy |
| SupportTicketResource | Ticket identity/subject/guest email; signed-in customer identity/contact; linked booking public reference | Existing support policy; booking FK lookup uses a selected-ID subquery because model has no booking relation |
| ReportResource | Report/reporter public identity and contacts; reported vendor identity/contact/EN/AR name | Existing report policy; only approved vendor-profile target type is searched |
| NotificationPreferenceResource | Preference public identity and recipient user name/contact/public ID | Existing notification preference policy |
| NotificationDispatchResource | Dispatch/provider public reference; recipient name/contact/public ID; linked booking public identity/reference | Existing notification dispatch policy; no user relation exists, so scoped selected-ID lookup is used |
| AdminInboxResource | Own alert public ID and title EN/AR | Existing admin_id = authenticated administrator condition retained |

Wallet/report search supports persisted full class names and known legacy/canonical aliases without changing stored type values or migrations. Selected numeric IDs are database joins only; they are not rendered as identity or directly matched to user search input.

## Inventory and explicit exclusions

The inventory included Booking, Payments, Settlement, Loyalty, Communication, Support and TrustSafety resource/table definitions and their existing base queries/policies. Identity and Catalog resources are excluded by lead ownership to avoid concurrent batch collisions; existing user/vendor catalog searches were reviewed as prior coverage, not changed. Global-search QA40 is separate. Configuration/taxonomy lists (rules/templates/program definitions/FAQ), provider webhook payload logs, idempotency keys, reconciliation run/finding lists and ledger groups already have their own relevant keys/search; no new broad contact joins were invented for resources without a direct customer/vendor/booking relation. Chat thread/message/flag lists define tables on their List pages, rather than these Resource tables, and remain an explicit integration/coverage gate for the chat-owning batch; this pack does not claim they gained contact search.

## Changed files

- `app/Modules/Booking/Filament/Resources/AdminBookingInterventionResource.php`
- `app/Modules/Booking/Filament/Resources/BookingModificationResource.php`
- `app/Modules/Booking/Filament/Resources/BookingResource.php`
- `app/Modules/Booking/Filament/Resources/BookingsMonitorResource.php`
- `app/Modules/Communication/Filament/Resources/AdminInboxResource.php`
- `app/Modules/Communication/Filament/Resources/NotificationDispatchResource.php`
- `app/Modules/Communication/Filament/Resources/NotificationPreferenceResource.php`
- `app/Modules/Loyalty/Filament/Resources/LoyaltyLedgerResource.php`
- `app/Modules/Loyalty/Filament/Resources/LoyaltyRedemptionResource.php`
- `app/Modules/Payments/Filament/Resources/PaymentAttemptResource.php`
- `app/Modules/Payments/Filament/Resources/PaymentResource.php`
- `app/Modules/Payments/Filament/Resources/RefundResource.php`
- `app/Modules/Settlement/Filament/Resources/CommissionResource.php`
- `app/Modules/Settlement/Filament/Resources/WalletLedgerViewerResource.php`
- `app/Modules/Settlement/Filament/Resources/WithdrawalResource.php`
- `app/Modules/Settlement/Filament/Resources/WithdrawalsQueueResource.php`
- `app/Modules/Support/Filament/Resources/SupportTicketResource.php`
- `app/Modules/TrustSafety/Filament/Resources/ReportResource.php`
- `app/Modules/Shared/Filament/Tables/ReferenceSearch.php`
- `tests/Feature/OperationalReferenceSearchTest.php`
- `qa-audit/remediation/GITHUB_61_IMPLEMENTATION.md`

The shared helper is justified by the same grouped, bound relationship predicate in 18 native Filament search closures. It accepts only hard-coded field maps from source, uses the active database grammar for JSON selectors and escaped LIKE parameters, and has no independent endpoint or authorization bypass. No package, schema, API, export or dependency changes. No EN/AR language keys were added because existing labels were retained.

## TDD and verification evidence

- RED before search implementation: `php vendor/bin/pest tests/Feature/OperationalReferenceSearchTest.php --filter=populated --bail` failed because the populated Booking list omitted the matching record for customer email (two assertions before failure). A fixture-only CommissionStatus enum/state mismatch was corrected before this behavioral RED.
- Further RED: wallet owner email and NotificationDispatch recipient email returned no matching row before their corresponding search wiring.
- GREEN final: `php vendor/bin/pest tests/Feature/OperationalReferenceSearchTest.php --bail --compact` — **35 passed, 435 assertions, 22.24 seconds**. Seventeen list datasets test rendered Livewire tables with matching and nonmatching real records, own public IDs where present, customer/vendor contacts, booking identities, Arabic vendor names, unknown queries and populated denied customer-role attempts. Separate inbox test proves bilingual/reference lookup never reveals a second admin inbox. Monitor/intervention tests also exclude a matching-contact completed booking outside review scope.
- Scoped Pint: ran on the 18 resources, helper and focused test; exited 0. Formatting limited to owned files, including native required import/line-ending corrections.
- PHP syntax: `php -l` passed for all 20 PHP files in this pack.
- `git diff --check` against the same scoped files exited 0.
- Explicit `php vendor/bin/phpstan analyse app/Modules/Shared/Filament/Tables/ReferenceSearch.php --no-progress --memory-limit=1G` exited 1 with **No files found to analyse**: repository configuration excludes module Filament paths. This is not a passing PHPStan claim. Lead must complete the required native global PHPStan gate and assess the documented Filament exclusion.

## Necessary populated-list rendering fixes discovered by the regression

- BookingsMonitorResource::urgencyLabel used `Carbon\Carbon` without a Carbon namespace import, resolving to a nonexistent module-local class and throwing a view exception. Failing populated deadline render was observed; added `use Carbon\Carbon` and used `Carbon::parse` at its two existing call sites. Lead accepted this minimal prerequisite fix.
- LoyaltyLedgerResource used translated **No expiry** as the default for a dateTime column. A real null-expiry entry threw a Carbon parsing view exception. Replaced default with placeholder so empty date display is not parsed as a timestamp.
- Existing Commission/LoyaltyRedemption vendor search and AdminInbox title search used MySQL-only JSON_UNQUOTE raw SQL; SQLite populated-list search threw no-such-function. Existing vendor predicates now use Laravel JSON selectors; inbox search uses the shared grammar-backed selector and includes requested Arabic/public-reference coverage. No mocked database compatibility function was added.

## Remaining release and browser gates

1. Lead review of exact source diff, repeated read-only cross-module lookup conventions and all touched search paths.
2. Fresh native global Pint/PHPStan and full Pest --bail against the integrated current checkout; previously running root full-suite snapshots predate final search wiring and are not acceptance of this pack.
3. Tester-army browser proof for each matched list with authorized staff and denied customer/vendor roles, existing filters, unknown references, real pagination, EN/AR and desktop/mobile. Native Livewire tests are populated table-query/render evidence, not actual browser interaction.
4. MySQL/MariaDB deployment-equivalent query smoke check for JSON selector plus ESCAPE syntax; focused automated run used disposable SQLite :memory:, never shared application data.
5. Confirm original issue list coverage with chat-owning and Identity-owning batches. Keep #61 open while any required list or browser gate remains; do not treat this report or a friendly empty state as closure.
