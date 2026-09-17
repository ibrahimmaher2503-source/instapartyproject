# InstaParty Full-Stack E2E QA Report

Date: 2026-05-22  
Environment: `http://localhost:8000`  
Runner: Codex Browser / Playwright with API calls through `fetch()` in `browser_evaluate`  
Scope note: the requested suite mentions 20 phases, but the provided instructions include Phases 0 through 13 only. This report covers every provided phase in sequence.

## Executive Summary

The local application became testable after MySQL was running. Public discovery, auth basics, templates/import validation, wishlist, and several booking paths are operational. The main blockers are clustered around state serialization, admin moderation actions, vendor approval actions, booking submission semantics, payment initiation, and fulfillment transitions.

Overall result:

| Phase | Area | Result |
|---|---|---|
| 0 | Server health | PASS |
| 1 | Public and geography API | PASS with contract issue |
| 2 | Customer auth/profile/address | PARTIAL FAIL |
| 3 | Vendor registration/profile setup | PARTIAL FAIL |
| 4 | Admin vendor approval | FAIL |
| 5 | Service creation for rental/sale/digital | FAIL |
| 6 | Service moderation | FAIL |
| 7 | Service change request flow | FAIL |
| 8 | Excel import templates/validation | PASS |
| 9 | Discovery/wishlist/vendor profiles | PASS |
| 10 | Booking create/submit/vendor accept | PARTIAL FAIL |
| 11 | Booking negotiation | PARTIAL FAIL |
| 12 | Payments/refunds | FAIL |
| 13 | Fulfillment states | FAIL / UI not implemented |

## Important Test Fallbacks Used

The suite instruction said to never stop on failure, so these fallbacks were used to continue later phases:

| Fallback | Why it was needed |
|---|---|
| Direct DB lookup for numeric `governorate_id`, `city_id`, and `category_id` | Public APIs expose public IDs, but later vendor registration/address payloads require internal numeric IDs. |
| Direct Sanctum token creation for seeded vendors | Seeded vendor logins started returning `429 Too Many Requests` during the multi-actor run. |
| Direct DB changes for new vendor product-type approvals | Admin product-type approval workflow was unreachable/broken after profile approval. |
| Direct DB restore for suspended sale vendor | Suspend action worked, but no working unsuspend/activate action was visible. |
| Direct DB service status updates to `pending_review` and `published` | Submit-for-review and admin publish actions were broken. |
| Direct DB creation/status handling for one service change request | Admin request-change UI and API flow did not complete as requested. |

These fallbacks should be treated as evidence of gaps, not as passing behavior.

## Test Data Created Or Used

| Object | Identifier |
|---|---|
| Registered customer | `customer.e2e@instaparty.local` |
| Registered vendor | `vendor.e2e@instaparty.local` |
| New vendor profile public ID | `01KS7KD99BB6A8KMXJ75VMD0Q5` |
| Rental service, `E2E Bounce House` | `01KS7KWYV6BVH1A88BVDSVDGV7` |
| Sale service, `E2E Birthday Cake` | `01KS7KWZSE4QBN5H1TNVQXTAGF` |
| Digital service, `E2E Digital Invite` | `01KS7KX0DACJVZ94V3XGPZ0MNS` |
| New vendor rental test service | `01KS7KX3DQW9WJZ1W3QP9E6MK6` |
| Reject service, `Reject Me` | `01KS7M2YF5737H0B7SJ9NG8BHX` |
| Phase 10 booking | `01KS7MD5BE6T6TVC6AG55787PK` |
| Phase 11 accepted-modification booking | `01KS7MFC1BERSSTSMBN1S5Q7Q8` |
| Phase 11 rejected-modification booking | `01KS7MHA6C320AP2AHT77RRBNG` |
| Change request fallback | `01KS7M7EPY6SYQ7CSSG7BBRREW` |

## Phase Details

### Phase 0 - Server Health Check

Result: PASS after environment recovery.

Observed:

| Check | Result |
|---|---|
| Landing `http://localhost:8000` | Loaded. |
| `/api/v1/feature-flags/public` | Returned `200` after MySQL came up. |
| `/admin` | Filament login page loaded. |

Initial blocker:

The API initially returned a database connection error because MySQL was not running:

```text
SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it
```

Once `c:\xampp\mysql\bin\mysqld.exe` was running, Phase 0 passed and the suite continued.

Screenshot: [phase0_result.png](phase0_result.png)

### Phase 1 - Public And Geography API

Result: PASS with one contract issue.

Observed:

| Check | Result |
|---|---|
| `GET /api/v1/customer/governorates` | `200`, `data` array present. |
| `GET /api/v1/customer/cities` | `200`, `data` array present. |
| `GET /api/v1/cms/homepage` | `200`. |
| `GET /api/v1/feature-flags/public` | `200`. |
| `GET /api/v1/theme/tokens` | `200`. |
| `GET /api/v1/customer/occasions` | `200`. |
| `GET /api/v1/customer/categories` | `200`. |

Contract issue:

The public APIs correctly expose `public_id`, but later suite payloads require numeric `governorate_id`, `city_id`, and `category_id`. This forced a direct DB lookup to continue.

Screenshot: [phase1_result.png](phase1_result.png)

### Phase 2 - Customer Auth Flow

Result: PARTIAL FAIL.

Observed:

| Step | Result |
|---|---|
| Register customer | `201`, `public_id` present. |
| Send OTP | `202`, `sent` true. |
| Login seeded customer | `200`, token present. |
| Get profile | `200`, name matched `Nour Hassan`. |
| Update profile | `200`. |
| Add address with suite payload | FAIL: `422`. |
| Add address with corrected fallback payload | `201`. |
| List addresses | `200`, at least one address. |
| Wrong password | `422`, accepted by suite criteria. |

Bug detail:

The suite sends a public city ID and omits `label`. The implementation requires an internal numeric `city_id` and appears to require `label`. This conflicts with the public-ID API convention in the prompt and AGENTS.md.

Screenshot: [phase2_result.png](phase2_result.png)

### Phase 3 - Vendor Registration And Profile Setup

Result: PARTIAL FAIL.

Observed:

| Step | Result |
|---|---|
| Register new vendor | `201`, vendor profile public ID present. |
| Login new vendor | `200`, token present. |
| Get vendor profile | `200`, `approval_status=pending`, business name matched. |
| Update vendor profile | `200`. |
| Set business hours | `200` or equivalent success. |
| Add coverage area | `200` or equivalent success. |
| Login seeded rental/sale/digital vendors | FAIL: `429 Too Many Requests`. |

Fallback:

Direct Sanctum tokens were created for the seeded vendors so catalog and booking phases could continue.

Screenshot: [phase3_result.png](phase3_result.png)

### Phase 4 - Admin Vendor Approval

Result: FAIL.

Observed:

| Step | Result |
|---|---|
| Admin login | PASS, dashboard loaded. |
| Find new vendor | PASS, vendor visible in admin. |
| Approve vendor profile | PASS, profile became approved. |
| Approve vendor for product types | FAIL. |
| Verify vendor profile via API | Required DB fallback for product-type approvals. |
| Suspend sale vendor | PASS, status became suspended. |
| Unsuspend/activate sale vendor | FAIL, no working action visible. |

Bug detail:

After approving the vendor profile, the product-type approval action was not available in the UI. Directly invoking the related action also failed because it compared a model-state object to a product-type enum. The sale vendor suspend action worked, but the suspended row still exposed `Suspend Vendor` and no working unsuspend/activate action was available.

Fallback:

The new vendor's type approvals were inserted directly in the database. The sale vendor was restored directly in the database.

Screenshot: [phase4_result.png](phase4_result.png)

### Phase 5 - Catalog Service Creation

Result: FAIL.

Observed:

| Step | Result |
|---|---|
| Create rental service | FAIL response: `500`, but record persisted. |
| Create sale service | FAIL response: `500`, but record persisted. |
| Create digital service | FAIL response: `500`, but record persisted. |
| PATCH rental/sale/digital base price | FAIL: `501 Direct update not implemented yet`. |
| New approved vendor creates rental | FAIL response: same `500`, record persisted. |
| Digital vendor creates rental | PASS: `403`. |

Primary error:

```text
Undefined property: App\Modules\Catalog\Domain\States\ServiceStatus\DraftState::$value
```

Bug detail:

Create endpoints persist data, then fail while serializing the response. The application likely treats a Spatie state object as a backed enum and reads `$value`.

Screenshot: [phase5_result.png](phase5_result.png)

### Phase 6 - Catalog Service Moderation

Result: FAIL.

Observed:

| Step | Result |
|---|---|
| Submit service for review at `/submit-for-review` | FAIL: endpoint missing. |
| Try `/resubmit` fallback | FAIL: `422`, `changed_fields` required. |
| Admin approve/publish rental | FAIL. |
| Admin approve/publish sale | FAIL. |
| Admin approve/publish digital | FAIL. |
| Create reject service | Persisted but create response hit same state serialization bug. |
| Admin reject service | FAIL. |
| Vendor verifies reject status | Could not verify proper rejected status through the intended flow. |

Admin moderation error:

```text
State::resolveStateClass(): Argument #1 ($state) must be of type string, App\Modules\Catalog\Domain\Enums\ServiceStatus given
```

Fallback:

The three core services were moved to `published` directly in the database so discovery and booking phases could continue.

Screenshot: [phase6_result.png](phase6_result.png)

### Phase 7 - Service Change Request Flow

Result: FAIL.

Observed:

| Step | Result |
|---|---|
| Admin requests changes in UI | FAIL: action did not create a usable change request. |
| Vendor lists change requests | FAIL: `GET /api/v1/vendor/change-requests` returned `404`. |
| Vendor replies with suite payload | FAIL: endpoint/payload mismatch. |
| Vendor replies with implemented payload shape | PARTIAL: required direct status setup. |
| Admin approves change request | FAIL through API/UI. |

Bug detail:

The suite expects:

```text
GET /api/v1/vendor/change-requests
POST /api/v1/vendor/service-change-requests/{id}/reply with message
```

The implementation did not expose the expected list endpoint. The reply endpoint expected a different payload shape (`body`) and only worked after the request was in `awaiting_clarification`. Admin API approval was blocked by authorization: unauthenticated calls returned `401`, and admin-token calls returned `403 User does not have the right roles`.

Fallback:

A change request was created/transitioned directly in the database to keep later phases moving.

Screenshot: [phase7_result.png](phase7_result.png)

### Phase 8 - Catalog Excel Import

Result: PASS.

Observed:

| Check | Result |
|---|---|
| Download rental template | `200`. |
| Download sale template | `200`. |
| Download digital template | `200`. |
| Empty rental import payload | `422`, validation error. |
| Admin import history page | Loaded. |

Minor note:

The invalid import validation mentioned both `store_id` and file requirements. The file validation behavior is correct, but `store_id` should be reviewed against the intended vendor-scoped import contract.

Screenshot: [phase8_result.png](phase8_result.png)

### Phase 9 - Discovery, Wishlist, Vendor Profiles

Result: PASS.

Observed:

| Check | Result |
|---|---|
| Search services | `200`, returned services. |
| Browse vendors | `200`, returned vendors. |
| View single vendor profile | `200`. |
| View published rental service | `200`. |
| Add to wishlist | `201`. |
| Get wishlist | `200`, items present. |
| Remove from wishlist | `204`. |

Screenshot: [phase9_result.png](phase9_result.png)

### Phase 10 - Booking Create, Submit, Vendor Accept

Result: PARTIAL FAIL.

Observed:

| Step | Result |
|---|---|
| Create booking draft | `201`, lifecycle `draft`. |
| Add booking item | `201`. |
| Submit booking | FAIL: returned `422`, but booking advanced. |
| Vendor sees incoming booking | `200`, count at least 1. |
| Vendor accepts booking | `200`, sub-status accepted. |
| Remove submitted booking item | FAIL by suite criteria: returned `409` instead of accepted `422`. |

Bug detail:

Booking submit appears to mutate the lifecycle while returning an error. That is dangerous for clients because retries may not be idempotent from the user's perspective. The item removal lock is valid behavior, but the response status differs from the requested contract.

Final observed booking:

```text
booking_public_id: 01KS7MD5BE6T6TVC6AG55787PK
lifecycle_status: confirmed
payment_status: unpaid
```

Screenshot: [phase10_result.png](phase10_result.png)

### Phase 11 - Booking Negotiation

Result: PARTIAL FAIL.

Observed:

| Step | Result |
|---|---|
| Create second booking and submit | PARTIAL: submit behavior had the same issue as Phase 10. |
| Vendor modify with suite `proposal_kind=price_adjustment` | FAIL: `422`. |
| Vendor modify with implemented `proposal_kind=change_price` | `201`. |
| Customer lists modifications | `200`, modification present. |
| Customer accepts modification | `200`. |
| Customer rejection path | `200` after direct booking-vendor identification. |

Bug detail:

The negotiation API contract does not match the suite contract. The suite uses `price_adjustment`; the app accepts `change_price`. The incoming booking-vendor list also did not reliably surface the freshly created booking for the rejection path, requiring direct lookup.

Screenshots: [phase11_result.png](phase11_result.png)

### Phase 12 - Payments And Refunds

Result: FAIL.

Observed:

| Step | Result |
|---|---|
| Initiate payment | FAIL: `500`. |
| Check payment status | Could not proceed because no payment ID was returned. |
| Duplicate payment initiation | FAIL: same `500`. |
| Admin/manual capture | Could not complete through intended flow. |
| Verify booking payment status | Booking remained unpaid. |
| Admin refund API | FAIL: `403`. |

Primary payment error:

```text
Undefined property: App\Modules\Payments\Domain\States\PaymentStatus\PendingState::$value
```

Bug detail:

This is the same state-object serialization pattern seen in Catalog. Payment initiation does not persist a usable payment row for the booking before failing.

Final observed booking payment state:

```text
booking_public_id: 01KS7MD5BE6T6TVC6AG55787PK
lifecycle_status: confirmed
payment_status: unpaid
```

Screenshot: [phase12_result.png](phase12_result.png)

### Phase 13 - Fulfillment States

Result: FAIL / UI not implemented.

Observed:

| Check | Result |
|---|---|
| Admin booking detail page | Loaded. |
| Booking monitor page | Loaded. |
| Rental fulfillment buttons | Not found. |
| Sale fulfillment buttons | Not found. |
| Digital fulfillment buttons | Not found. |
| Mark booking completed action | Not found for tested booking. |

Available admin actions seen:

```text
Edit
Force Cancel
Add Admin Note
```

No route-list evidence of fulfillment transition API endpoints was found during the run, so the item-level fulfillment state machines could not be exercised.

Screenshot: [phase13_result.png](phase13_result.png)

## Consolidated Bug Log

| Bug ID | Severity | Area | Description |
|---|---|---|---|
| BUG-001 | High | Environment/API | API returns a hard 500 when MySQL is unavailable. Local dev needs clearer health failure handling or startup guidance. |
| BUG-002 | High | API contracts | Public APIs expose `public_id`, but several write payloads require internal numeric IDs. |
| BUG-003 | Medium | Customer addresses | Address creation payload contract differs from suite: needs internal `city_id` and `label`. |
| BUG-004 | Medium | Auth | Login throttling blocks seeded multi-actor E2E runs with `429`. |
| BUG-005 | High | Admin/vendor approval | Vendor product-type approval workflow is unavailable/broken after profile approval. |
| BUG-006 | High | Admin/vendor approval | Suspended vendor has no working unsuspend/activate action visible. |
| BUG-007 | Critical | Catalog | Service creation persists records then returns `500` due state serialization. |
| BUG-008 | High | Catalog | Vendor service PATCH endpoint returns `501 Direct update not implemented yet`. |
| BUG-009 | High | Catalog moderation | Expected submit-for-review endpoint is missing; resubmit endpoint requires a different payload. |
| BUG-010 | Critical | Catalog moderation | Admin approve/publish/reject actions fail because model-state transition receives an enum instead of a state class/string. |
| BUG-011 | High | Change requests | Admin request-change and vendor change-request list/reply flow does not match the expected contract. |
| BUG-012 | Medium | Negotiation | Proposal kind mismatch: suite expects `price_adjustment`; implementation accepts `change_price`. |
| BUG-013 | Critical | Booking | Booking submit mutates state while returning `422`. |
| BUG-014 | Critical | Payments | Payment initiation returns `500` due `PendingState::$value` and no usable payment is created. |
| BUG-015 | High | Admin API auth | Admin token cannot access admin change-request/refund APIs, returning `403`. |
| BUG-016 | High | Fulfillment | Admin fulfillment transition actions are missing or not exposed for booking items. |

## Recommended Fix Order

1. Fix all state serialization in API resources and response builders. Spatie model-state objects should be converted with their supported state value/name API, not `$state->value`.
2. Fix moderation transitions to pass valid model-state class names or configured state values, not `ServiceStatus` enum instances.
3. Align all API contracts on public IDs in URLs and request bodies, or clearly document any internal numeric IDs that remain unavoidable.
4. Implement or restore the expected service submit-for-review endpoint.
5. Repair vendor product-type approvals and unsuspend/activate actions in Filament.
6. Add a local/E2E-safe login throttle strategy for seeded test accounts.
7. Repair admin Sanctum role/ability handling for admin APIs such as refunds and change-request approval.
8. Expose fulfillment transition actions in admin, or document the intended API/vendor-portal path and update the suite accordingly.

## Screenshot Evidence

| Phase | Screenshot |
|---|---|
| 0 | [phase0_result.png](phase0_result.png) |
| 1 | [phase1_result.png](phase1_result.png) |
| 2 | [phase2_result.png](phase2_result.png) |
| 3 | [phase3_result.png](phase3_result.png) |
| 4 | [phase4_result.png](phase4_result.png) |
| 5 | [phase5_result.png](phase5_result.png) |
| 6 | [phase6_result.png](phase6_result.png) |
| 7 | [phase7_result.png](phase7_result.png) |
| 8 | [phase8_result.png](phase8_result.png) |
| 9 | [phase9_result.png](phase9_result.png) |
| 10 | [phase10_result.png](phase10_result.png) |
| 11 | [phase11_result.png](phase11_result.png) |
| 12 | [phase12_result.png](phase12_result.png) |
| 13 | [phase13_result.png](phase13_result.png) |

