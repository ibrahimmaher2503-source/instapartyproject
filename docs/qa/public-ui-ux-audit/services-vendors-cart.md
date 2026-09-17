# Public services, vendors, and cart UI/UX audit

Closed 2026-09-15 against the isolated 1440px Browser capture in `docs/qa/screenshots/public-desktop-2026-09-15`.

## Closure ledger

| Surface | Result | Browser evidence |
|---|---|---|
| Rental service, EN + AR | PASS | Relevant local fallback media renders in hero and gallery; price, location, setup time, vendor, and add-to-cart action are clear. |
| Sale service, EN + AR | PASS | Cake media, sale type, lead time, price, vendor, and action render without broken media. |
| Digital service, EN + AR | PASS | Invitation media, delivery method, price, vendor, and action render without broken media. |
| Vendors index, EN + AR | PASS | Every fallback cover now follows the vendor's actual service context: rental, cake, or digital. Cards have visible keyboard focus and safe logo fallback. |
| Vendor detail, EN + AR | PASS | Contextual cover, initial logo fallback, facts, service card, and vendor-filtered CTA form one consistent route. |
| Guest cart, EN + AR | PASS | Empty state has one explanation and clear recovery actions; populated controls retain labels and focus treatment in code. |

## Closed defects

- Removed stale media rows from isolated capture data using the model morph alias, so irrelevant uploaded test photos no longer override storefront fallbacks.
- Reused `StorefrontImageFallback` for vendor covers instead of assigning images by public-ID hash.
- Hardened service hero/gallery failures and vendor logo/cover failures without creating new assets.
- Aligned the vendor CTA copy with its vendor-filtered search destination.

The public desktop visual issues in this report are closed. The authenticated purchase chain is validated by `tests/Feature/FullPurchaseJourneyTest.php`; payment capture/settlement and full multi-role Browser acceptance remain tracked in the marketplace E2E reports rather than being implied by these screenshots.
