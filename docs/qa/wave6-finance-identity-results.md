# Wave 6 Finance Identity Results

Date: 2026-09-13  
Issue: `BUG-FINANCE-IDENTITY-001`  
Status: **PARTIAL / UNVERIFIED**

## Proven root cause

Existing finance presentation paths read numeric foreign keys directly and
rendered morph values with PHP class names. Refund responses exposed
`payment_id` and `booking_id`; wallet and reconciliation screens exposed
`owner_id`, `resource_id`, and raw types. The Settlement Filament resources for
ledger groups, reconciliation runs, and reconciliation findings also used the
default numeric record route key even though their visible references are
public IDs.

## Implemented slice

The changed presentation layer now resolves an allowlisted finance type to its
existing model, displays a localized entity label and its `public_id`, and
falls back to `Legacy / Unknown` or `قديم / غير معروف` when the type, target,
or public reference is missing. Internal numeric IDs and PHP class names are
kept out of the rendered value.

Changed files:

- `app/Modules/Settlement/Application/Support/FinanceIdentityPresenter.php`
- `app/Modules/Settlement/Filament/Resources/WalletLedgerViewerResource.php`
- `app/Modules/Settlement/Filament/Resources/WalletLedgerViewerResource/RelationManagers/LedgerEntriesRelationManager.php`
- `app/Modules/Settlement/Filament/Resources/LedgerTransactionGroupResource.php`
- `app/Modules/Settlement/Filament/Resources/ReconciliationRunResource.php`
- `app/Modules/Settlement/Filament/Resources/ReconciliationFindingResource.php`
- `app/Modules/Settlement/Filament/Resources/ReconciliationRunResource/RelationManagers/FindingsRelationManager.php`
- `app/Modules/Payments/Domain/Models/Refund.php`
- `app/Modules/Payments/Filament/Resources/RefundResource.php`
- `app/Modules/Payments/Http/Resources/RefundResource.php`
- `app/Modules/Settlement/Http/Resources/ReconciliationFindingResource.php`
- `app/Modules/Settlement/Http/Resources/WalletLedgerEntryResource.php`
- `app/Modules/Settlement/Resources/lang/en/settlement.php`
- `app/Modules/Settlement/Resources/lang/ar/settlement.php`
- `app/Modules/Payments/Resources/lang/en/payments.php`
- `app/Modules/Payments/Resources/lang/ar/payments.php`
- `tests/Feature/FinanceIdentityPresentationTest.php`

No routes, tables, migrations, ledger rows, or historical values were changed.

## Verification

| Check | Result |
|---|---|
| Focused Pest before the final Application namespace move | **PASS — 4 tests, 15 assertions** |
| Focused Pest rerun after the namespace move | **BLOCKED** by the required escalated Laravel runner; automatic approval reported the current usage limit |
| Pint on all changed PHP files | **PASS**; fixer run completed |
| Pint `--test` on all changed PHP files | **PASS** |
| `php -l` on all changed PHP files | **PASS — 13/13** |
| Browser acceptance | **UNVERIFIED**; no live Browser session was available in this worker |

The focused tests cover localized public identity output, the missing/legacy
fallback, Refund API public references without the two internal foreign-key
keys, and Reconciliation API removal of raw resource type/id fields. The final
namespace move only relocates the already-tested presenter and updates imports;
the required final Pest rerun still needs to be performed by the coordinator
when the escalated runner is available.

## Browser fixture for the finance coordinator

Use isolated local test data only:

1. Create an admin user with the existing Settlement/Payments view permissions
   and select the admin panel.
2. Create one approved vendor with EN `Demo Vendor` and AR `مورد تجريبي`, one
   wallet owned by that vendor, one captured payment, one pending refund, one
   reconciliation run, and one finding. Keep the generated model `id` values
   private; record their `public_id` values for assertions.
3. Open the existing Wallets, Refunds, Reconciliation Runs, and Reconciliation
   Findings pages in EN and AR. Visible identity values must contain the
   localized entity label and `public_id`, and must not contain a numeric
   foreign key or `App\\Modules\\...` class name.
4. Open a reconciliation finding whose `resource_id` points to a missing or
   soft-deleted row. The visible value must be `Legacy / Unknown` or
   `قديم / غير معروف`, with no numeric ID.
5. Request the existing Refund and Wallet Ledger API resources. Refund data
   must expose `payment_public_id` and `booking_public_id`; it must not expose
   `payment_id` or `booking_id`. Related and reconciliation identities must
   contain a public reference or a localized safe fallback.
6. Open a Reconciliation Finding row from its run link and confirm the URL
   contains the run `public_id`, never the internal run ID.

This fixture verifies the implemented presentation and authorization surface;
it does not use production data and does not prove a complete Browser journey.

## Remaining contract boundary

Public identity presentation is implemented for this slice. Reveal/encryption,
key rotation, and any missing approved contract for sensitive finance identity
data remain **UNVERIFIED** and were intentionally left unchanged. Full Browser
acceptance and the final coordinator Pest rerun remain pending.
