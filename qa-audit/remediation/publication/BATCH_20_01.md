# GitHub remediation — twenty-issue batch 01

Branch: `codex/fix-issues20-01a133b7-batch-01`

Base: published aggregate batch 04, `177fbc0b456ec5dd689a35235b7e607bd3e7ceaa`

## Twenty primary issues

| Issue | Selected implementation scope |
|---|---|
| #9 | Registration City depends on Governorate; legacy blank/dash bank placeholders remain absent in forms, eligibility and the banking checklist link. |
| #11 | Public vendor registration has a uniform guest response for eligible and duplicate email submissions; caller-supplied Livewire validation rules cannot expose uniqueness checks. |
| #24 | Document edit controls and direct edit routes share the existing update policy plus review permission. |
| #25 | Planner and search guest counts accept whole numbers from 1 to 10,000, with EN/AR feedback and matching browser form limits. |
| #28 | Deliberate robots directives and a sitemap of localized public entry routes replace the static robots file. |
| #38 | Admin imports select an approved vendor and require the exact service-type create permission at page and action boundaries. |
| #41 | The existing native Filament form handles import submission/upload processing state. |
| #46 | Package recommendation name and description fields have explicit bilingual labels. |
| #57 | Vendor impersonation keeps the administrator session separate, validates bilingual reasons and vendor eligibility, expires after 30 minutes, rechecks authorization and records terminal audits. |
| #60 | Authorized moderation detail views link existing restricted context, respect redaction and audit access; freeze/unfreeze actions enforce their permissions. |
| #61 | Operational reference search groups OR predicates within the existing permission/filter query and searches supported linked identifiers/contact fields across 24 list entry points. |
| #67 | Expiry lists and reminders use the latest document by creation time then ID; superseded documents cannot trigger suspension. EN/AR alerts and expiry filters remain within existing list access. |
| #81 | Document checklist status follows required document types and deterministic rejected/expired/missing/pending precedence; the vendor page explains missing required documents. |
| #89 | The phone verification form has an explicit Send code control alongside Verify. |
| #90 | Unstored business-hour toggles begin closed, and every open day requires both times; existing overnight interpretation remains. |
| #92 | General dark-theme surface/text tokens apply to vendor statement headers. |
| #101 | Populated booking-monitor urgency uses the imported Carbon class. |
| #107 | The same dark-theme tokens apply to vendor recent-booking headers/cards. |
| #121 | Category schema options use structured bilingual rows, distinct values and safe legacy hydration; unsupported structures are rejected without silently replacing stored options. |
| #123 | Schema language tabs use the existing EN/AR translation labels. |

## Isolation and ownership

The source snapshot and publication checkout are separately managed worktrees for this twenty-issue campaign. Another active chat uses the original checkout and older Batch 05; neither is modified by this publication work. The base commit is immutable. Selected source files and owned hunks are listed in `BATCH_20_01_FILES.txt`.

Mixed files transfer only the selected guest-count, reference-search, impersonation, document and translation hunks. Existing published cancellation/refund/session/ownership changes are preserved. Original storefront geography/subcategory/gallery work, account-management/MFA work, service form/schema/price changes for #122, export/date/queue/bulk/internal-note features and broader dashboard/navigation/mobile UX are excluded. General dark-theme tokens are the only CSS change.

The document-expiry command and suspension action are necessary #67 callers. Sale/digital table import guards are necessary #38 entry points. Existing vendor-registration lifecycle expectations are updated only for selected #9/#11 behavior. Prepared old regression sources for the earlier sixty issues remain in the isolated source checkout for later scoped compatibility integration; this batch neither transfers them wholesale nor discards them.

## Deferred validation and acceptance

The user postpones functional/runtime test execution until campaign implementation is finished. The lead completed the static precommit gates for this exact reviewed snapshot:

- Scoped Pint formatting finished with exit 0; the later registration privacy regression file was also formatted with exit 0. After the final admin-panel access predicate was added to the impersonation start action, that exact action and its security regression file were formatted again with exit 0; the full configured PHPStan rerun again finished with exit 0 and `[OK] No errors`.
- Full configured PHPStan finished with exit 0 and `[OK] No errors`, using a 2 GB process limit and process-only testing settings: SQLite `:memory:`, array cache/mail and disabled Telescope. The first attempt failed because this isolated checkout lacked `storage/framework/views`; runtime directories were created inside the worktree and the rerun passed. Those runtime directories are excluded from publication.
- The staged allowlist contains exactly 105 paths and excludes credentials, dependencies and runtime output. `git diff --cached --check` passed with exit 0 after removing a Markdown trailing-space hard break and an extra final blank line in the prepared import browser regression.

No Pest/functional tests, asset build, browser interaction, application startup or dependency installation has been performed for this publication snapshot. Focused regression sources are included but remain unexecuted. #61's prepared populated regression covers its existing seventeen-list dataset plus scoped inbox cases; source transfer across 24 callers does not establish executed coverage for every caller.

Source review and publication do not establish runtime acceptance. Final integration must run the appropriate native checks and role/locale/browser flows. Real email/SMS delivery, deployed document scheduler/queue behavior, historical data causes, FilePond upload behavior, public crawler responses, rendered dark themes and provider/proxy configuration remain unverified here. No financial history, production data or configuration is rewritten. No merge, deployment or issue closure is included in this publication task.

Two independent final source reviews found no concrete blocker in the selected scope; the lead authorized publication of these twenty issues after the static gates above. The staged path boundary and Git whitespace check are reviewed before the layer commits. This authorization does not include GitHub comments, issue closures, merge or deployment.
