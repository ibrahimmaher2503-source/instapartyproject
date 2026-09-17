# Public home and discovery UI/UX audit

Closed 2026-09-15 against the isolated 1440px Browser capture in `docs/qa/screenshots/public-desktop-2026-09-15`.

## Closure ledger

| Surface | Result | Browser evidence |
|---|---|---|
| Home, EN + AR | PASS | Long-form hierarchy, RTL/LTR composition, cards, calls to action, and footer render without clipping or mixed-language chrome. |
| Search populated, EN + AR | PASS | Bilingual queries return the same three fixtures; filter rail, prices, dates, result count, cards, and pagination remain readable. |
| Search empty, EN + AR | PASS | Empty copy and recovery action are localized and visually prominent. |
| Planner wizard, EN + AR | PASS | Curated occasions replace placeholder records; selected state is exposed with `aria-pressed`/`aria-current`; date fields show one localized prompt and the primary action is visible. |
| Birthday category, EN + AR | PASS | Canonical redirect is recorded and the selected category returns the matching rental fixture. |
| Birthday occasion, EN + AR | PASS | Canonical redirect is recorded and the selected occasion returns all three matching fixtures. |

## Closed defects

- Removed placeholder occasion records from the isolated capture data instead of hiding them in the view.
- Connected the category and occasion fixtures to published services so canonical discovery routes no longer appear empty.
- Added cross-language SQL fallback search and made unknown category/city filters return zero results instead of silently broadening the query.
- Widened the desktop filter rail, labeled price fields, promoted the empty recovery action, and added an announced result count.
- Localized the date presentation without duplicating the browser's native prompt.

No open desktop UI defect remains in these audited surfaces. Mobile and transactional multi-role acceptance are tracked separately from this public desktop visual audit.
