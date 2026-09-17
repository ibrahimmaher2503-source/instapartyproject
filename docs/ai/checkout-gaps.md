# Checkout gaps and instruction review

Observed 2026-09-10. Recheck when the checkout changes. This records evidence, not new product approvals.

| Previous claim | Observed evidence | Agent handling |
|---|---|---|
| Git working tree is available | `.git` points to `/home/instaparty/git/instaparty-production.git`; local `git status` fails | Track touched files explicitly; no Git init/repair, commit or clean-status claim |
| PRD, locked specs, constitution, ADRs and feature tasks exist | `docs/specs/`, `docs/adr/`, `.specify/memory/constitution.md`, `specs/` absent | Request needed requirements for dependent feature decisions; do not invent their contents |
| Claude hooks/rules/slash commands are installed | `.claude/` absent; README previously described a setup bundle | Root README now documents this checkout; no claim of automatic guard enforcement |
| AGENTS and CLAUDE agree | Original AGENTS bans subscriptions; original CLAUDE lists subscription ADR and newer media/ledger decisions | Originals retained under reference/; no silent ratification, removal or expansion of business features |
| 13 modules, backend only, separate Next.js frontend | 17 module directories; Blade storefront in routes/web.php and resources/views/storefront; PRODUCT.md describes it | Map actual entry points; existing Blade work does not authorize adding a frontend framework |
| Required PHP is 8.3+ and dependency versions match policy | composer.json says PHP ^8.2, Firebase ^7.0, and includes packages absent from old allowlist | Preserve manifests; resolve with restored tech/package specs before dependency changes |
| Architecture tests enforce invariants | tests/Architecture absent; phpunit.xml lists Unit and Feature only; tests/Unit also absent | Never claim architecture gates passed or disable policy because tests are missing |
| Run php artisan pint | Pint, PHPStan and Pest were installed from locked dev dependencies on 2026-09-10; `php artisan pint` remains the wrong entry point | Use `php vendor/bin/pint`, `php vendor/bin/phpstan` and `php vendor/bin/pest`; these now exist |
| API registry and Bruno cover all endpoints | Registry contains 3 data rows; collection has 2 request files plus bruno.json; many module routes exist | Treat registry as incomplete; inspect real routes and update contracts for touched endpoints |
| Playwright setup was runnable | The referenced `tests/e2e` directory was initially absent | Restored an isolated SQLite setup and admin audit suite; `npm run e2e` now passes three Chromium tests |

## Pattern review outcome

Applied: one shared instruction entry point, topic-based policy loading, actual module/surface routing, scoped search recipes, a runnable index/link check, and explicit evidence gaps. Kept original instructions as on-demand references so unique decisions are recoverable without loading both files every session.

Code patterns must not be copied blindly: `Booking/Application/Actions/SubmitBookingAction.php` has raw minor-unit tax arithmetic, and `BookingNegotiationController::submit` exceeds the stated three-line controller rule. These are concrete policy discrepancies, not permission to change financial rounding or authorization during an agent-documentation review. Follow the real callers and obtain the missing feature requirements before a financial refactor.

No application behavior, dependencies, services, database, Git metadata or production state changed in this review. Full application QA is not implied by the documentation check.

## Upgrade audit — 2026-09-10

The requested Laravel 13 + Filament 5 + Flux 2 migration was dependency-checked but not applied. PHP 8.4 satisfies Laravel 13's platform requirement. Composer 2.10.3 resolved these blockers:

- Filament 5 releases were blocked by Composer security advisories; disabling advisory blocking would hide a security decision and was not done.
- Existing `bezhansalleh/filament-language-switch:^3.1`, `bezhansalleh/filament-shield:^3.3`, `filament/spatie-laravel-settings-plugin:^3.2`, `pxlrbt/filament-excel:^2.4`, and `saade/filament-fullcalendar:^3.2` require Filament 3. Upgrade-capable versions exist for some, but not all are stable/currently compatible.
- `filament/spatie-laravel-translatable-plugin` latest stable remains 3.3.55 and requires Filament support at its own version; it cannot be combined with Filament 5.
- `awcodes/filament-tiptap-editor` latest stable remains 3.5.16 and requires Filament 3.
- `pestphp/pest-plugin-laravel:^3.2` does not support Laravel 13; current Laravel 13 support is in the Pest 5 line and requires a coordinated dev-tool upgrade.
- `livewire/flux:^2` is available, but Flux is a Livewire component library. It does not replace Filament's internal panel components; use it for Blade/custom Livewire surfaces after the Filament migration boundary is settled.

The attempted `composer require ... -W` reverted both `composer.json` and `composer.lock` automatically. Current installed/locked versions remain Laravel 12.62, Filament 3.3.54 and Livewire 3.8.1. Do not edit only version strings: the safe next migration requires choosing replacements/removals for incompatible plugins, then running the official Filament upgrade tooling and fixing the resulting application code.

The follow-up attempt with the user's approved removals/upgrades also reverted automatically. It additionally identified `filament/spatie-laravel-settings-plugin:^3.2` and `filament/spatie-laravel-tags-plugin:^3.2` as Filament 3 locks; their current compatible line is 5.x. No manifest or lockfile change from either attempt remains.

The test environment is isolated by forced SQLite values in `phpunit.xml`, and the Playwright setup creates its own `storage/e2e.sqlite` before authenticating a bounded test administrator. The complete PHP suite passes 58 tests with 835 assertions. The repeatable Chromium suite passes setup plus two browser tests covering the dashboard and five critical create screens, EN-to-AR switching, RTL, raw translation/error detection, and a 390px viewport.

IP-QA-001 through IP-QA-012 are code-closed in this checkout. Category selection is server-searched with a 50-result limit; browser timing on the 301-category dataset was 2.50 seconds for the rental create page and the marker category was absent from the initial DOM. Static Filament translation keys and all 14 enums passed directly to Filament options now have EN/AR regression gates. The stale vendor-review command is enabled by default, scheduled every minute, idempotent, and still supports an explicit environment kill switch. Production at `instaparty.online` was observed running the previous build, so live acceptance remains pending deployment and post-deploy UAT.
