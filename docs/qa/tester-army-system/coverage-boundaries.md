# Coverage boundaries for the current local system audit

The 1,292 discovery scenarios are a static catalog, not a list of 1,292 passing tests. The execution matrix links only explicit scenario IDs in executed test names. `not-run` means no exact current scenario evidence has been linked; an existing PHP test may cover related behavior without that reconciliation. A passing linked test is `partially-tested` until every catalog clause is checked.

| Area | Current checks | What the checks do not establish |
| --- | --- | --- |
| Identity | Customer/admin/vendor login; EN/AR account navigation; guest API rejection; isolated account ownership | Real OTP, SMS, recovery delivery, production credentials |
| Catalog / Discovery | Three product types in both languages; search/details; price bounds; wishlist mutations; vendor profile exploration | Every inventory/calendar/media edge, production search indexing |
| Geography / Planning | Governorate/region/city fixture; future/past/reversed dates; EN/AR wizard transition | Every timezone or coverage-area combination |
| Booking / Payments | Sale draft, item, checkout total, submit, vendor acceptance, repeated fake payment initiation | Real capture/callback/refund; complete rental or digital fulfillment; concurrent requests |
| Settlement / Subscriptions | Owned wallet/ledger/withdrawal/settlement/bank/commission/subscription API responses; role denial; panel resources | Monetary settlement accuracy from empty fixture responses; real bank transfer |
| Communication / Reviews / Loyalty / Promotions | Inbox/read-count/preferences; read and validation contracts; respective admin resource listings | Populated delivery, earned balances or redeem/review eligibility for completed orders |
| Support / TrustSafety / Shared | Support validation/deduplication; escrow/badges; invalid reports; CMS missing page; menus/branding/flags | Every moderation transition, valid CMS publishing workflow |
| Advertising and other admin resources | Registered resource index rendering where policy permits | Buying ads or running every resource create/edit/action |
| Firebase | Nine contact-pattern unit tests, seven emulator rules tests, TypeScript build | Cloud-function trigger → Laravel mirror → real customer/vendor chat across deployed systems |

The existing PHP suite supplies separate internal evidence: 563 tests / 5,061 assertions passed against disposable SQLite. It is not counted as tester.army browser coverage. Page/table visibility proves rendering and permitted access, not resource CRUD correctness.

Remaining work is scenario-by-scenario reconciliation and execution, starting with confirmed defects, full rental/digital purchase and fulfillment, refunds/cancellations/withdrawals, service submission/moderation, populated account flows, and concurrency. The 31 proposed scenarios require business expectations; external gateways and delivery need an appropriate test environment. No production acceptance follows from this audit.
