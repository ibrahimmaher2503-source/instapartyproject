# Vendor approval and priority follow-up batch06

Branch: codex/fix-issues20-01a133b7-low-06.
Base: 05d66dd7511f9f12c1907b1308c4a46d92fb94de (main after PR188).

Two additional Low reports appeared while the prior Low batch was in progress: #181 vendor city selector consistency and #182 observed admin modal latency. Two Luna/xhigh agents investigate their separate sources. #124 remains open for actual browser/upload initialization diagnosis.

The owner then prioritizes #183 account approval, #184 product-type workflow discoverability, #185 pending-type vendor guidance, #1 email delivery, #3 production admin credentials, #130 historical billing and #85 response expiry. Luna/xhigh agents investigate these independent domains alongside #181/#182. #176 and #174 are verified CLOSED and their source changes are already merged through PR188; production deployment remains explicitly cancelled.

The latest #184 reporter correction confirms that All Vendors row approval already works for Rental/Sale. Its remaining scope is discoverability and second-step guidance, not inventing a parallel product-type approval implementation. #183's required-document checklist must identify actual missing/rejected/expired types without weakening approved requirements. Historical billing, real transport delivery, existing production credential rotation and live scheduler acceptance require genuine external evidence; source changes alone cannot establish their completion.

No tests, browser, builds, servers, migrations, production, provider sends or data changes are executed. Minimal proven source fixes can proceed; reported timing cannot be called improved without actual profiling. Missing runtime evidence remains an explicit open gate, not an invented fix. Native formatting/static gates and independent source review precede any publication.

## Implementation and retained gates

| Issue | Source outcome | Remaining acceptance |
|---|---|---|
| #176/#174 | Already merged and verified CLOSED through PR188 | Deployment/runtime acceptance separate |
| #181 | Active-city count replaces the default50 selector cap for both rendering and search; same city query and translations | Actual affected selector retest |
| #184 | Existing type-approval workflow remains authoritative; approved-type list links authorized operators to All Vendors with explicit prerequisite guidance | Actual admin workflow retest |
| #185 | Type-specific entry controls and onboarding/status guidance agree with approved active types; disabled Digital is unavailable rather than pending and direct create authorization stays protected | Actual vendor workflow retest |
| #183 | Required-document diagnostics identify latest missing/pending/rejected/expired types; profile approval reaches locked domain validation and its failure toast | Reported80-second request hang remains unobserved; production network/server profiling needed |
| #182 | Duplicate eligibility evaluation removed; no claim of measured modal latency improvement | Actual timing/profile/network evidence |
| #1 | Registration/reset listeners, panel reset, vendor resend and admin recovery share transport-readiness checks; queued production failures retain safe codes, vendor reset uses the guarded page and SMTP timeout is30seconds | Real configured transport/sender, workers consuming default and notifications queues, exhausted-job monitoring and verification/reset inbox delivery |
| #3 | Demo identity/admin/global seeders fail closed outside local/testing, before any database writes | Existing production credential rotation, session/token invalidation and access-log review |
| #130 | Current local/testing fixture correction remains intact; no historical financial backfill | Original price/period/discount and genuine captured-payment evidence; no existing production subscription billing writer |
| #85 | Existing timeout action, locked deadline guards, registered enabled scheduler and fulfillment restrictions verified in source | Actual deployed scheduler/configuration and affected booking retest |

No approved document requirement is relaxed to accommodate the reported Commercial Register-only case. Company/establishment document rules remain unchanged. No historical invoice is repriced or marked paid without evidence, no credential is accessed or rotated, and no production worker/scheduler is changed. Open external/runtime gates stay open on GitHub.

## Review and validation

Independent Luna/xhigh source reviews cover city-selector limits, approval eligibility, product-type guidance, production seeder guards and mail readiness. Review caught and corrected the selector's unsupported zero/negative search limits, the global development seeder's partial production writes, and disabled Digital being described as pending. No latency improvement or actual mail delivery is claimed.

Focused regression sources cover EN/AR onboarding and per-type access, disabled Digital, more than50active cities, latest required-document failures, production seeder guards and mail readiness. These sources are prepared but unrun under the owner's explicit test deferral. Native Pint, configured PHPStan, diff hygiene and sensitive-path/secret-pattern review are recorded with the final publication evidence; static analysis excludes module Filament paths and does not prove browser acceptance.

Final static gates: scoped `php vendor/bin/pint` passed for all33changed PHP files; `php vendor/bin/phpstan analyse --memory-limit=2G --no-progress` passed with no errors; scoped `php -l`, `git diff --check` and sensitive-path/secret-pattern checks passed. The batch contains34changed paths. No behavioral test was executed. Independent final reviews approve the source after the findings above and a corrected SMTP timeout regression that reads actual configuration rather than assigning its expected value.

Source completion closes #181, #184 (reporter-corrected discoverability scope) and #185 after publication. #1, #3, #130, #85, #183 and #182 remain open with the acceptance gates above. #124 remains open for actual upload initialization diagnosis. #176/#174 are already CLOSED through PR188.
