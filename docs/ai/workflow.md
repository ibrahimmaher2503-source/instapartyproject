# Search and verification

Run from the repository root. Start with the code-map row for the affected surface.

## Find only what matters

```powershell
# Find likely filenames without reading the entire repository.
rg --files app/Modules/Booking tests -g '*Booking*'

# Locate the route, then read its controller and request.
rg -n -F 'bookings/{bookingPublicId}/submit' app/Modules/Booking/Routes

# Find every caller before changing this shared action.
rg -n -F 'SubmitBookingAction' app tests

# Follow a contract to bindings and consumers.
rg -n -F 'TaxRateResolver' app/Modules

# Find views and translations from an observed UI label or translation key.
rg -n -F 'vendor-approval-status' app resources lang

# Discover feature tasks only if the directory exists; do not scan vendor.
if (Test-Path specs) { rg --files specs -g tasks.md }

# Refresh module inventory directly from disk; compare with the map.
Get-ChildItem app/Modules -Directory | Select-Object -ExpandProperty Name
```

Use `rg -l` when only filenames are needed, `rg -n -F` for literal symbols and `rg -n` for intentional patterns. Read complete relevant functions, not just matched lines. Widen a search only when a dependency crosses the initial module boundary. Do not run recursive repository dumps, inspect `.env`, or ingest old screenshots/logs/route dumps to find source code.

## Reuse existing mechanisms

- Start with Shared `ApiResponse`, `MoneyCast`, `HasPublicId`, signed URL contracts and timeline provider before inventing helpers.
- Read provider bindings before introducing a repository/interface. Reuse existing module contracts; do not add one-implementation abstractions except where the module boundary requires a contract.
- Public content uses existing CMS/models/media; read PRODUCT.md and DESIGN.md for storefront edits. Do not create a parallel CMS or hardcode editable content.
- For Filament, follow the actual panel provider and neighboring resource. Preserve policies, translation and tenant/vendor ownership; do not copy a bug solely for consistency.
- A root-cause fix includes callers, event listeners, jobs, API and panel entry points that share the changed code. Do not refactor neighboring features without need.

## Checks by change

| Change | Check |
|---|---|
| Agent docs/index only | `powershell -NoProfile -File scripts/check-agent-docs.ps1` |
| PHP behavior | Relevant existing Pest test; add a focused regression check for non-trivial logic |
| Type-aware / user-facing API | Rental + sale + digital; EN + AR; auth, ownership, validation, idempotency as relevant; update API docs |
| Filament / Blade | Relevant tests and actual browser flow in affected role/locale; asset build if assets changed |
| Firebase functions | Read functions/README.md; scripts below; rules tests need emulator setup |
| Before a commit | Pint + PHPStan; additionally full Pest `--bail` for app changes; all required gates green |

Tool locations below are conventional entry points for the declared dependencies, **currently missing in this checkout**. Do not automatically install/update packages or run setup merely to validate docs.

```powershell
php vendor/bin/pint --test
php vendor/bin/phpstan analyse
php vendor/bin/pest --bail
# Target an actual file during iteration:
php vendor/bin/pest tests/Feature/VendorRegistrationLifecycleTest.php --bail
```

Use `php vendor/bin/pint` to format the relevant changed PHP files before a commit; avoid unrelated formatting. The old `php artisan pint` instruction was incorrect. Tests use RefreshDatabase and phpunit.xml selects SQLite `:memory:`; verify effective configuration is disposable before running database tests. Never repoint tests to shared application data. PHPStan excludes module Filament paths, so it cannot establish panel correctness.

Root package.json provides `npm run build` and `npm run e2e`; the latter currently targets a missing suite and can start a server on port 8000 via playwright.config.ts. Do not run it when the user forbids server starts. Functions scripts are `npm --prefix functions run build`, `npm --prefix functions test`, and `npm --prefix functions run test:rules`; do not run deploy for local validation.

## Keep this useful

Update code-map.md when a module/entry point moves; update checkout-gaps.md when a gap is actually resolved. Keep detailed policies in engineering-policy.md and completed work in real task files. Do not append chat transcripts or duplicate class inventories. Report exact checks, failures and unverified behavior; never equate an index check with application acceptance.
