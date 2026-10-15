# Main release integration — 2026-10-15

## Publication scope

The integration starts at `4b086b58a23cf122bfb5667e9aacce0137665899` (main after PR #195). All previously existing local and origin branch tips were ancestors of that commit when fetched. Their uncommitted working-tree changes, rather than redundant branch merges, required reconciliation.

Source was reconciled from the primary checkout, `qa-verify-flows`, and the earlier issue worktrees. Overlapping implementations retain the stricter authorization, payment and document requirements. Superseded implementations are consolidated into the active flow, rather than registering duplicate routes, resources or migrations. Original working trees and their local data are preserved.

The publication includes application source, translations, source assets, regression tests, test harnesses and relevant documentation. It excludes environment/MCP configuration, credentials, local databases, dependency directories, caches, generated builds, screenshots/traces and raw QA execution archives. Those local artifacts remain in their original locations.

## Integrated behavior

- Catalog: eligible parent/subcategory pairs, editable category schemas, gallery state, service import/moderation, service packages and theme choices, and read-only availability/price estimates using the booking pricing model.
- Booking: current inventory checks, financial snapshot consistency, pending vendor decisions, customer consent before an alternative vendor replaces an allocation, and auditable in-app notices.
- Customer/vendor surfaces: owned addresses, saved searches, loyalty history, published portfolios, inquiry conversations and booking chat access checks.
- Administration: private append-only notes/tags, queue age/SLA filters, navigation/table improvements, safe activity links, settings audit redaction and real booking-cohort analytics.
- Content/communication: bilingual CMS/navigation/home-block edits, geography selection, campaign scheduling/quiet hours and notification-template validation.

## Dependency reproducibility

The existing translatable Filament plugin is declared explicitly because both panel providers require it; a clean install must not depend on undeclared packages left in an old vendor directory. Unused orphaned editor/calendar dependencies are removed from the lockfile. Security updates retain Laravel 12, Livewire 3 and the existing application stack. The existing SVG sanitizer moves to its maintained 1.0 release and is covered by valid-content, executable-content and entity-reference checks.

## Validation and acceptance boundary

Exact final validation results are recorded in the pull request. Earlier passing batches and earlier failures are not substitutes for the final release checks. Database feature tests use disposable SQLite databases; browser tests use a separate owned database and test sinks. No real charge, payout, external notification, production migration or deployment is performed by this integration.

After publication, issue comments distinguish implemented source from browser, deployed and external-provider acceptance. Operational reports such as mail/SMS delivery, real gateway lifecycles, legacy wallet provenance, the affected production scheduler and live admin credential rotation stay open until the environment-specific evidence exists.

## Deployment handoff

Use the committed Composer and npm lockfiles, install the locked dependencies and build assets. Run the standard migration process after a backup and staging validation on the deployment database engine; new migrations include vendor document types, service packages, inquiry messages, internal notes/tags, CMS public slugs, queue settings and scoped idempotency keys. Some rollbacks intentionally refuse to discard existing audit or scoped-key data.

Apply the narrowly scoped permission/template seeders required by the new admin decisions, service themes and in-app notices. Do not run broad development/demo seeders against production or rewrite existing invoice/ledger history. Refresh application/route/view caches and restart long-running queue workers through the normal deployment process. Verify scheduler, queue, mail/SMS, storage and payment provider configuration in staging before release acceptance.

## Publication validation checkpoint

- Configured PHPStan: passed with no errors. Full Pint: passed. Vite production build: passed. Composer validation/platform checks: passed; locked dependency audit reported no advisories.
- Last completed full Pest run: 1,537 passed and 14 failed (13,028 assertions). The affected cases were then corrected and passed in focused reruns: Booking 62/424, Identity 139/1,112 plus product-type guidance 10/174, payment integrity 18/118, and public media fallback 3/41 (tests/assertions).
- The subsequent full `--bail` run was stopped before completion at the user's explicit request to publish immediately. It is not recorded as a passing full-suite gate. This user instruction overrides the repository's usual pre-commit full-suite completion requirement for this publication.
- Actual browser checks passed for the English/Arabic homepage, 390px Arabic layout, and authenticated estimator-to-booking date/timezone carry. Occasion administration browser acceptance was still in progress when testing was stopped; no broader browser or production acceptance is claimed.
- Agent documentation checks and isolated configuration/route cache generation passed. No hosted CI workflow is configured in this checkout.