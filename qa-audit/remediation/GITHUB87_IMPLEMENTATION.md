# GitHub #87 implementation evidence

## Finding

The report says a vendor approved for Sale only sees an **Add Rental Service** link, which leads to a forbidden page. Source review found this mismatch in two parts of the vendor UI: the overview widget linked Rental and Sale without checking the vendor's active approved types, and the services-list page rendered Rental and Sale create actions unconditionally in both its header and empty state. The resource access checks already enforce each product-type approval and remain unchanged.

## Change

- `app/Modules/Shared/Filament/Vendor/Widgets/VendorStatsOverviewWidget.php` builds Rental/Sale links from the vendor's active `approvedTypes()` relation. Digital-service links retain the existing `catalog.digital_services_enabled` flag.
- `app/Modules/Catalog/Filament/Vendor/Pages/VendorServicesListPage.php` gates `addRental`, `addSale`, `createRental`, and `createSale` with their corresponding resource `canAccess()` checks. This removes incorrect links from both the header and empty-state actions; the resource route guards still decide access.
- `tests/Feature/VendorDashboardProductTypeLinksTest.php` covers widget links for Sale-only, Rental-only, both types, a revoked Rental grant, and the Digital feature flag. Real Livewire page cases assert the exact create-link counts in the header and empty state for Sale-only, Rental-only, and revoked-Rental vendors.
- `tests/Feature/VendorDashboardLabelsTest.php` seeds active approved type rows so its dashboard-label assertions use a valid vendor fixture.
- `qa-audit/remediation/GITHUB_ISSUE_TRIAGE_80_93.md` records the current source status and the remaining browser retest.

## Red/green evidence

Before adding the services-page visibility conditions, the new page cases failed as expected: **3 failed, 5 passed, 14 assertions**. In each failing case, the wrong create URL appeared twice in rendered HTML, matching the header and empty-state actions.

After the fix, the captured focused run in `.e2e/risk-program/remediation/github87.log` reports Pint passing for the changed services page and regression test, then **21 tests and 255 assertions passed** across:

- `tests/Feature/VendorDashboardProductTypeLinksTest.php`
- `tests/Feature/VendorDashboardLabelsTest.php`
- `tests/Feature/VendorSaleServiceAuthorizationTest.php`
- `tests/Feature/VendorApprovalPortalAccessTest.php`

JUnit output is saved at `.e2e/risk-program/remediation/github87.junit.xml`. An earlier widget-focused run, before the page cases were added, passed **8 tests and 93 assertions**; the current run above includes the added Livewire page and authorization cases.

## Remaining acceptance

This verifies the current checkout with focused automated tests. A tester-army/browser run against a Sale-only vendor account has not been performed, so rendered browser acceptance remains open. The GitHub issue has not been commented on or closed.
