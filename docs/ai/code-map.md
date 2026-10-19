# Code map

Paths are relative to the repository root. This is a routing index, not a class dump.
Verify filenames with `rg --files` when working; update affected rows when entry points move.

## Pick the surface

| Task | Start here | Follow next |
|---|---|---|
| API endpoint | Module `Routes/` below | Controller → Request/Policy → Action → Resource |
| Route registration / middleware / errors | [bootstrap/app.php](../../bootstrap/app.php), [providers](../../bootstrap/providers.php) | Owning module provider, middleware aliases |
| Admin panel | [AdminPanelProvider](../../app/Providers/Filament/AdminPanelProvider.php) | Module `Filament/`, [shared views](../../resources/views/filament/) |
| Admin account / MFA security | [UserResource](../../app/Modules/Identity/Filament/Resources/UserResource.php), [AdminSecurityPage](../../app/Modules/Identity/Filament/Pages/AdminSecurityPage.php) | Identity account Actions, AdminMfaSession, EnsureAdminMfa; identity view namespace loaded by IdentityServiceProvider |
| Vendor panel | [VendorPanelProvider](../../app/Providers/Filament/VendorPanelProvider.php) | Module `Filament/`, [vendor views](../../resources/views/vendor-portal/) |
| Vendor import templates | [Catalog vendor routes](../../app/Modules/Catalog/Routes/vendor.php), [DownloadImportTemplateController](../../app/Modules/Catalog/Http/Controllers/DownloadImportTemplateController.php) | Session-authenticated panel download `vendor.catalog.import.template.web`; API download retains Sanctum; both require approved product type |
| Vendor chat / availability calendar | [VendorChatPage](../../app/Modules/Communication/Filament/Vendor/Pages/VendorChatPage.php), [VendorAvailabilityCalendarPage](../../app/Modules/Catalog/Filament/Vendor/Pages/VendorAvailabilityCalendarPage.php) | Owned conversation/component access, rental manual blocks and reservations, viewer timezone; [vendor views](../../resources/views/vendor-portal/pages/) |
| Public storefront | [web routes](../../routes/web.php) | Module `Routes/storefront.php`, [storefront views](../../resources/views/storefront/) |
| Customer favorites / notification controls | [Discovery web routes](../../app/Modules/Discovery/Routes/storefront.php), [Identity web routes](../../app/Modules/Identity/Routes/storefront.php) | Customer session + CSRF; existing wishlist actions and owner-scoped notification read/unread; booking links are regenerated after ownership checks |
| Public service reviews | [ServiceController](../../app/Modules/Catalog/Http/Controllers/Web/ServiceController.php), [PublicServiceReviewReader](../../app/Modules/Reviews/Domain/Contracts/PublicServiceReviewReader.php) | Reviews-owned public projection, bounded pagination, shared ReviewCursor validation; approved review summary supplies the detail rating widget |
| Public occasions index | [Catalog storefront routes](../../app/Modules/Catalog/Routes/storefront.php) | `/{locale}/occasions` → `TaxonomyController::occasions` → `ListPublicOccasionsAction` → `storefront.occasions.index` |
| Public crawler metadata | [web routes](../../routes/web.php), [RobotsTxtController](../../app/Modules/Shared/Http/Controllers/RobotsTxtController.php), [SitemapController](../../app/Modules/Shared/Http/Controllers/SitemapController.php) | Localized public entry routes only; robots directives do not enforce access control |
| Homepage / CMS | [Shared](../../app/Modules/Shared/), [home view](../../resources/views/storefront/home.blade.php) | [Home components](../../resources/views/components/home/), [PRODUCT](../../PRODUCT.md), [DESIGN](../../DESIGN.md) |
| Schedule / jobs | [console routes](../../routes/console.php) | Module Console/Commands or Application/Commands and listeners |
| Subscription expiry / financial reconciliation | [console routes](../../routes/console.php) | ExpireSubscriptionsAction, ExpireStaleReconciliationRunsAction, RunReconciliationAction; findings keep original evidence and recurring-condition tracking |
| API locale / envelope | [SetLocaleMiddleware](../../app/Modules/Identity/Http/Middleware/SetLocaleMiddleware.php), [ApiResponse](../../app/Modules/Shared/Http/ApiResponse.php) | Module Resources/lang/{en,ar}, Requests and Resources |
| Phone verification / SMS | [IdentityServiceProvider](../../app/Modules/Identity/Providers/IdentityServiceProvider.php), [SmsOtpGateway](../../app/Modules/Identity/Infrastructure/Gateways/SmsOtpGateway.php) | SendOtpAction → Communication SmsProviderInterface / SMS Misr → cache challenge → VerifyPhoneAction; local/testing use StubOtpGateway |
| Transactional mail readiness | [CommunicationProviderHealthPage](../../app/Modules/Communication/Filament/Pages/CommunicationProviderHealthPage.php) | MailReadiness → LaravelMailEmailAdapter; EmailRecipientReader binding in IdentityServiceProvider; transport acceptance is separate from delivery |
| Booking invoice / refund | [BookingInvoiceController](../../app/Modules/Booking/Http/Controllers/Admin/BookingInvoiceController.php), [RefundPaymentAction](../../app/Modules/Payments/Filament/Actions/RefundPaymentAction.php) | BuildBookingInvoiceDataAction, BookingPaymentTotalsReader; InitiateRefundAction → durable ProcessRefundAction → authenticated confirmation → settlement reconciliation |
| Customer invoice | [CustomerBookingInvoiceController](../../app/Modules/Booking/Http/Controllers/Web/CustomerBookingInvoiceController.php), [Booking storefront routes](../../app/Modules/Booking/Routes/storefront.php) | Customer ownership by public ULID → BuildBookingInvoiceDataAction → customer-redacted printable invoice; pending refunds are read through BookingPaymentTotalsReader |
| Customer help / FAQ | [Support storefront routes](../../app/Modules/Support/Routes/storefront.php), [CustomerHelpController](../../app/Modules/Support/Http/Controllers/Web/CustomerHelpController.php) | Existing FAQ repository and support ticket action; [help views](../../resources/views/storefront/support/) |
| Customer account capabilities | [Identity storefront routes](../../app/Modules/Identity/Routes/storefront.php), [CustomerAddressPageController](../../app/Modules/Identity/Http/Controllers/Web/CustomerAddressPageController.php) | Owned address CRUD via Identity actions; Discovery saved searches; Loyalty read-only balance/history; Communication chats inbox; [account views](../../resources/views/storefront/account/) |
| Public vendor portfolio | [PublicVendorPortfolioReader](../../app/Modules/Identity/Domain/Contracts/PublicVendorPortfolioReader.php) | Approved vendor public ID → existing public media projection; consumed by Catalog service detail and Discovery vendor profile views |
| Pre-booking vendor inquiries | [Communication storefront routes](../../app/Modules/Communication/Routes/storefront.php), [CustomerVendorInquiryController](../../app/Modules/Communication/Http/Controllers/Web/CustomerVendorInquiryController.php), [VendorInquiryListPage](../../app/Modules/Communication/Filament/Vendor/Pages/VendorInquiryListPage.php) | Published-service contract in Catalog → owned inquiry/messages in Communication → customer/vendor conversation pages; customer account chat inbox links these real threads; separate from confirmed-booking chat |
| Private admin notes / tags | [InternalRecordNotesRelationManager](../../app/Modules/Shared/Filament/Resources/RelationManagers/InternalRecordNotesRelationManager.php) | AddInternalRecordNoteAction, SyncInternalRecordTagsAction; allowlisted subject types, existing record read/update permissions, append-only note history |
| Money / identifiers | [MoneyCast](../../app/Modules/Shared/Domain/Casts/MoneyCast.php), [HasPublicId](../../app/Modules/Shared/Traits/HasPublicId.php) | Owning module migrations/models; engineering policy §6 |
| Timeline / audit | [TimelineServiceProvider](../../app/Modules/Shared/Providers/TimelineServiceProvider.php) | Shared Application/Timeline + module Application/Timeline |
| Media / signed URLs | [SharedServiceProvider](../../app/Modules/Shared/Providers/SharedServiceProvider.php) | Shared Domain/Contracts + Infrastructure/Services |
| Chat moderation / token bridge | [functions/src](../../functions/src/), [functions README](../../functions/README.md) | [Firestore rules](../../firestore.rules), Communication module |
| Admin chat history | [ChatThreadResource](../../app/Modules/Communication/Filament/Resources/ChatThreadResource.php), [ChatModerationHistory](../../app/Modules/Communication/Filament/Resources/ChatThreadResource/Components/ChatModerationHistory.php) | Public-ID record URLs; authorized, audited Firestore pages with redaction before Livewire serialization |
| Test behavior | [Feature tests](../../tests/Feature/), [Pest setup](../../tests/Pest.php) | [TestCase](../../tests/TestCase.php), [Fakes](../../tests/Fakes/), [Support](../../tests/Support/) |

Storefront routes are discovered by `routes/web.php`; API route loading and contract bindings live in module providers. Do not search only `routes/` for APIs.

## Module index

Each module link leads to its actual directory. Inspect its provider for bindings, migrations and routes. Names describe implemented responsibility, not approved phase scope.

| Module | Search here for | Route files currently present |
|---|---|---|
| [Identity](../../app/Modules/Identity/) | login, registration, users, vendor approval/documents/permissions | customer, vendor, admin, storefront |
| [Catalog](../../app/Modules/Catalog/) | services, rental/sale/digital, inventory, availability, imports | customer, vendor, admin, storefront |
| [Discovery](../../app/Modules/Discovery/) | search, browsing, discovery; public wizard context via POST `wizard/context` → `PlannerController::store` → `StorePlannerContextAction` | customer, storefront |
| [Booking](../../app/Modules/Booking/) | draft, items, negotiation, cancellation, fulfillment | customer, vendor, admin, storefront |
| [Payments](../../app/Modules/Payments/) | Paymob, payment attempts, refunds, idempotency, webhooks | customer, admin, webhook |
| [Settlement](../../app/Modules/Settlement/) | commissions, wallet, ledger, withdrawals | customer, vendor, admin |
| [Communication](../../app/Modules/Communication/) | chat, pre-booking inquiries, notifications, channels, templates | customer, vendor, admin, internal, storefront |
| [Reviews](../../app/Modules/Reviews/) | service/vendor reviews, moderation, rating | customer, vendor, public |
| [Geography](../../app/Modules/Geography/) | countries, governorates, cities, regions | customer |
| [Loyalty](../../app/Modules/Loyalty/) | points, rewards, loyalty ledger | customer, vendor, admin |
| [Shared](../../app/Modules/Shared/) | CMS, branding, settings, money, media, timeline, public crawler metadata | customer, vendor, storefront |
| [Subscriptions](../../app/Modules/Subscriptions/) | subscription implementation; scope conflict recorded | customer, vendor, admin |
| [Tax](../../app/Modules/Tax/) | tax implementation; scope conflict recorded | none |
| [Advertising](../../app/Modules/Advertising/) | advertising implementation; approval unverified | none |
| [TrustSafety](../../app/Modules/TrustSafety/) | trust / safety | customer |
| [Support](../../app/Modules/Support/) | FAQ / support tickets | customer, storefront |
| [Promotions](../../app/Modules/Promotions/) | promo codes | customer |

There are no standalone Negotiation or Reporting module directories here. Negotiation entry points are in Booking; locate reporting by page/export symbol rather than inventing a module path.

Vendor document requirements are managed at `/admin/vendor-document-types` through Identity's `VendorDocumentTypeResource`. `RequiredVendorDocumentTypesResolver` reads the same catalog for vendor upload options, approval, onboarding and expiry enforcement. Removed types retain their historical labels and uploaded files.

## A concrete trace: submit booking

1. [Booking customer routes](../../app/Modules/Booking/Routes/customer.php): `bookings/{bookingPublicId}/submit`.
2. [BookingNegotiationController](../../app/Modules/Booking/Http/Controllers/Customer/BookingNegotiationController.php): `submit`.
3. [SubmitBookingRequest](../../app/Modules/Booking/Http/Requests/SubmitBookingRequest.php) and [SubmitBookingDTO](../../app/Modules/Booking/Application/DTOs/SubmitBookingDTO.php).
4. [SubmitBookingAction](../../app/Modules/Booking/Application/Actions/SubmitBookingAction.php): transaction, ownership, inventory/tax dependencies, events.
5. [BookingServiceProvider](../../app/Modules/Booking/Providers/BookingServiceProvider.php): bindings and event listeners; inspect dependency providers too.
6. [BookingResource](../../app/Modules/Booking/Http/Resources/BookingResource.php): response representation.

This trace locates code; it does not certify policy compliance. Before editing, search `SubmitBookingAction` across app/tests, then inspect relevant event consumers.

[CreateBookingDraftAction](../../app/Modules/Booking/Application/Actions/CreateBookingDraftAction.php) persists the initial draft transition inside its transaction. `BookingDraftCreated` remains an after-commit event for the initial snapshot; subsequent lifecycle transitions are written by their actions. The admin [CreateBooking page](../../app/Modules/Booking/Filament/Resources/BookingResource/Pages/CreateBooking.php) wraps draft creation, item addition and submission in one transaction.
