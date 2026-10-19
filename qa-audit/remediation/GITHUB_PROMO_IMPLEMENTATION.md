# GitHub #13/#14 — promotion create/edit

Four visibility closures referenced an incompatible Get class and failed on the authorized create route. Replacing only their types with installed Filament Forms Get fixes both forms.

TDD evidence: `.e2e/risk-program/remediation/github-promo-red.log`, `github-promo.log`, `github-promo-junit.xml`; tests/Feature/GitHubPromoAdminFormsTest.php passes 3 tests / 18 assertions for percentage/fixed creation and existing-code edits. Scoped Pint passes. #34 used-code deletion remains a distinct open issue and is not proved by this pack. No deployment or provider operation occurred.
