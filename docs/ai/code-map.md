# Code map

Paths are relative to the repository root. This is a routing index, not a class dump.
Verify filenames with `rg --files` when working; update affected rows when entry points move.

## Pick the surface

| Task | Start here | Follow next |
|---|---|---|
| API endpoint | Module `Routes/` below | Controller → Request/Policy → Action → Resource |
| Route registration / middleware / errors | [bootstrap/app.php](../../bootstrap/app.php), [providers](../../bootstrap/providers.php) | Owning module provider, middleware aliases |
| Admin panel | [AdminPanelProvider](../../app/Providers/Filament/AdminPanelProvider.php) | Module `Filament/`, [shared views](../../resources/views/filament/) |
| Vendor panel | [VendorPanelProvider](../../app/Providers/Filament/VendorPanelProvider.php) | Module `Filament/`, [vendor views](../../resources/views/vendor-portal/) |
| Public storefront | [web routes](../../routes/web.php) | Module `Routes/storefront.php`, [storefront views](../../resources/views/storefront/) |
| Homepage / CMS | [Shared](../../app/Modules/Shared/), [home view](../../resources/views/storefront/home.blade.php) | [Home components](../../resources/views/components/home/), [PRODUCT](../../PRODUCT.md), [DESIGN](../../DESIGN.md) |
| Schedule / jobs | [console routes](../../routes/console.php) | Module Console/Commands or Application/Commands and listeners |
| API locale / envelope | [SetLocaleMiddleware](../../app/Modules/Identity/Http/Middleware/SetLocaleMiddleware.php), [ApiResponse](../../app/Modules/Shared/Http/ApiResponse.php) | Module Resources/lang/{en,ar}, Requests and Resources |
| Money / identifiers | [MoneyCast](../../app/Modules/Shared/Domain/Casts/MoneyCast.php), [HasPublicId](../../app/Modules/Shared/Traits/HasPublicId.php) | Owning module migrations/models; engineering policy §6 |
| Timeline / audit | [TimelineServiceProvider](../../app/Modules/Shared/Providers/TimelineServiceProvider.php) | Shared Application/Timeline + module Application/Timeline |
| Media / signed URLs | [SharedServiceProvider](../../app/Modules/Shared/Providers/SharedServiceProvider.php) | Shared Domain/Contracts + Infrastructure/Services |
| Chat moderation / token bridge | [functions/src](../../functions/src/), [functions README](../../functions/README.md) | [Firestore rules](../../firestore.rules), Communication module |
| Test behavior | [Feature tests](../../tests/Feature/), [Pest setup](../../tests/Pest.php) | [TestCase](../../tests/TestCase.php), [Fakes](../../tests/Fakes/), [Support](../../tests/Support/) |

Storefront routes are discovered by `routes/web.php`; API route loading and contract bindings live in module providers. Do not search only `routes/` for APIs.

## Module index

Each module link leads to its actual directory. Inspect its provider for bindings, migrations and routes. Names describe implemented responsibility, not approved phase scope.

| Module | Search here for | Route files currently present |
|---|---|---|
| [Identity](../../app/Modules/Identity/) | login, registration, users, vendor approval/documents/permissions | customer, vendor, admin, storefront |
| [Catalog](../../app/Modules/Catalog/) | services, rental/sale/digital, inventory, availability, imports | customer, vendor, admin, storefront |
| [Discovery](../../app/Modules/Discovery/) | search, browsing, discovery | customer, storefront |
| [Booking](../../app/Modules/Booking/) | draft, items, negotiation, cancellation, fulfillment | customer, vendor, admin, storefront |
| [Payments](../../app/Modules/Payments/) | Paymob, payment attempts, refunds, idempotency, webhooks | customer, admin, webhook |
| [Settlement](../../app/Modules/Settlement/) | commissions, wallet, ledger, withdrawals | customer, vendor, admin |
| [Communication](../../app/Modules/Communication/) | chat, notifications, channels, templates | customer, vendor, admin, internal |
| [Reviews](../../app/Modules/Reviews/) | service/vendor reviews, moderation, rating | customer, vendor, public |
| [Geography](../../app/Modules/Geography/) | countries, governorates, cities, regions | customer |
| [Loyalty](../../app/Modules/Loyalty/) | points, rewards, loyalty ledger | customer, vendor, admin |
| [Shared](../../app/Modules/Shared/) | CMS, branding, settings, money, media, timeline | customer, vendor, storefront |
| [Subscriptions](../../app/Modules/Subscriptions/) | subscription implementation; scope conflict recorded | customer, vendor, admin |
| [Tax](../../app/Modules/Tax/) | tax implementation; scope conflict recorded | none |
| [Advertising](../../app/Modules/Advertising/) | advertising implementation; approval unverified | none |
| [TrustSafety](../../app/Modules/TrustSafety/) | trust / safety | customer |
| [Support](../../app/Modules/Support/) | FAQ / support tickets | customer |
| [Promotions](../../app/Modules/Promotions/) | promo codes | customer |

There are no standalone Negotiation or Reporting module directories here. Negotiation entry points are in Booking; locate reporting by page/export symbol rather than inventing a module path.

## A concrete trace: submit booking

1. [Booking customer routes](../../app/Modules/Booking/Routes/customer.php): `bookings/{bookingPublicId}/submit`.
2. [BookingNegotiationController](../../app/Modules/Booking/Http/Controllers/Customer/BookingNegotiationController.php): `submit`.
3. [SubmitBookingRequest](../../app/Modules/Booking/Http/Requests/SubmitBookingRequest.php) and [SubmitBookingDTO](../../app/Modules/Booking/Application/DTOs/SubmitBookingDTO.php).
4. [SubmitBookingAction](../../app/Modules/Booking/Application/Actions/SubmitBookingAction.php): transaction, ownership, inventory/tax dependencies, events.
5. [BookingServiceProvider](../../app/Modules/Booking/Providers/BookingServiceProvider.php): bindings and event listeners; inspect dependency providers too.
6. [BookingResource](../../app/Modules/Booking/Http/Resources/BookingResource.php): response representation.

This trace locates code; it does not certify policy compliance. Before editing, search `SubmitBookingAction` across app/tests, then inspect relevant event consumers.
