# Customer booking and catalog follow-up batch07

Branch: `codex/fix-issues20-01a133b7-followup-07`.
Base: `c8a16a4022aee047ea7daaa5d54d7b88ee0b9ab5` (main after PR194).

The owner requests #193, #192, #191, #190, #189, #187, #186, #183 and #182 using the existing Luna/xhigh agent workflow. Five implementation agents work by domain: payment continuation; taxonomy administration and subcategory selection; bilingual service content, gallery and category fields; customer occasion eligibility; vendor approval responsiveness. Independent source review follows before publication and merge.

Tests, browser interaction, builds, application servers, database migrations, provider calls and production changes remain deferred or stopped by the owner's explicit instructions. Regression sources may be prepared but must not be reported as executed. Source checks do not prove live timing, payment-provider acceptance or browser behavior.

The original checkout and prior branches are preserved. This batch uses the clean publication worktree and existing locked dependencies. No historical media, payment or user data is rewritten.

## Changes and issue disposition

| Issue | Source result | GitHub disposition after merge |
| --- | --- | --- |
| #193 | Submitted bookings show bilingual waiting-for-confirmation guidance and a booking-status link; confirmed unpaid bookings retain the supported payment action. The post-submit redirect to booking details was already on main. Payment is not enabled before confirmation. | Close as a source fix; payment-provider and browser acceptance remain unrun. |
| #190 | Rental, sale and digital vendor creation preserve both long-description translations through the existing DTO/action path. Filament enforces the second translation when one is supplied. Existing edit and customer projection are retained. | Close as a source fix. |
| #189 | Wizard occasions, recommendations and service choices follow the selected service/category relation. Authoritative add and submit paths reject unrelated/inactive/deleted occasions. A nonempty cart cannot silently switch occasions. Admin creation filters and preflights choices, and malformed planner query arrays are normalized. | Close as a source fix. |
| #186 | Admin occasion create/edit supports selecting eligible subcategories using the existing pivot. Omitted selection preserves links, explicit empty selection clears them, and retained sort order survives. After-commit changes refresh affected published services in Scout. | Close as a source fix. |
| #187 | Vendor main-category options and validation require at least one active child that supports the selected product type; bilingual guidance explains why unavailable parents cannot be selected. | Keep open: the reported 20-second delay has not been reproduced or measured. |
| #192 | Added missing native rental dimensions to the admin relationship form and regression source for edit hydration/persistence/public projection of category Dimensions/Capacity. Dynamic category fields were already present on main for all three product types. | Keep open: the reported live category-field omission has no confirmed source cause; native rental dimensions are a related partial improvement. |
| #191 | Inspected the existing single gallery component and installed media integration; no confirmed duplication cause. No deduplication or media rewrite. | Keep open for runtime reproduction distinguishing component state from persisted duplicate entries. |
| #183 | Approval lists eager-load eligibility relations and latest documents; checklist evaluation is reused and still reports precise missing requirements. The domain approval action retains fresh locked validation. | Keep open: the reported approval hang/no-op is not established by the source improvements. |
| #182 | Reduced repeated eligibility and relation queries during admin review. | Keep open: modal latency requires runtime profiling. |

## Review and regression source

Five implementation agents and five independent reviewers used the requested Luna/xhigh configuration. Review covered shared callers, installed Filament behavior, validation state paths, after-commit events, fixture compatibility and locale/product-type paths.

Review corrections include state-path-aware `requiredWith()` rules, the taxonomy test locale/type dataset, isolated Scout mock setup, payment copy pointing to actual booking status, locking the occasion row before pivot diffs, admin eligibility preflight and atomic admin booking creation. Initial draft transitions now persist in the creation transaction, before submission history, while the draft event and snapshots remain after commit. The obsolete transition listener was removed after checking callers, and the code map was updated. Final independent source review found no outstanding P1/P2.

Five new focused regression files cover payment continuation, occasion eligibility, taxonomy administration, bilingual service content and approval query reuse. Existing successful booking fixtures explicitly link their service category to the selected occasion; no global factory behavior was weakened. All regression files remain unrun under the owner's test deferral.

## Validation

Final checks passed on the frozen source: PHP syntax on all 62 changed existing PHP files (excluding Blade and the deleted obsolete listener); scoped Pint (`passed`); configured full repository PHPStan with `--memory-limit=2G --no-progress` (`No errors`); and `git diff --check`. The documentation checker passed for 7 documents, 85 local links and 17 modules. The batch contains 67 changed paths: 27 domain, 23 UI and 17 tests/documentation. PHPStan configuration excludes module Filament paths and tests, so its result cannot establish panel or behavioral acceptance.

No Pest, browser acceptance, build, server start, migration, provider call or production deployment was performed. No environment, dependency, schema or historical data changes are part of this batch. Normal GitHub merge is required; no required-check bypass or branch deletion is authorized by this ledger.
