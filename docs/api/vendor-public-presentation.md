# Public vendor presentation

Existing public routes retain the `{ data, meta, errors }` envelope and vendor approval/ownership checks. No new route or database migration is introduced.

## Profile and location

`GET /api/v1/customer/vendors/{vendorPublicId}` and the vendor index add `governorates`: unique primary (or primary-city fallback) and coverage governorates. Entries contain external `public_id` and localized `name`. The existing `city` property remains for client compatibility. Service summary `area_hint` now joins these governorates with ` · `.

```json
{"governorates":[{"public_id":"01JEXAMPLE00000000000000000","name":"Cairo"},{"public_id":"01JEXAMPLE00000000000000001","name":"Giza"}]}
```

Arabic requests receive Arabic names through the existing locale handling.

## Portfolio

`GET /api/v1/customer/vendors/{vendorPublicId}/portfolio` and profile `portfolio_preview` use the same source as the Blade gallery: vendor portfolio first, then displayable service galleries ordered by service ID. Duplicate URLs are omitted. A conversion is used only when it exists on its configured disk; the original is the fallback. Media with neither a usable conversion nor original is excluded. `total_count` counts valid unique images before the existing 24-item API limit; the web gallery displays all valid images.

Each item retains `url` and `service_public_id` and adds `thumb`. `service_public_id` is **nullable** for a vendor-owned portfolio image; service-gallery items retain the real service ULID. Consumers must handle this nullable association.

## Web filters

Vendor profile `?type=rental|sale|digital` uses vendor-scoped cursor pagination of 15 services and preserves the query. Disabled digital services remain unavailable. Invalid types are rejected.

Public `/en/search` and `/ar/search` accept `category_slug` plus `subcategory` (category codes). A selected child must be active and belong to the selected parent; invalid pairs return the existing validation response. Old links using a child as `category_slug` normalize to parent plus child. This is a **web filter**; the API continues to use its existing `category_slug` parameter for a child category.

Vendor web profile also accepts optional `q` (string, at most 255 characters), matching English and Arabic service names within the current vendor and optional type. It preserves q/type through cursor pagination and type navigation; clearing resets both. This does not add a search parameter to existing vendor API requests.
