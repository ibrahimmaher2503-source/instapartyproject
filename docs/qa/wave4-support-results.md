# Wave 4 Support workflow results

Date: 2026-09-13  
Issue: `BUG-SUPPORT-WORKFLOW-001`  
Status: **PARTIAL**

## Closed slice

The existing admin status buttons now use `TransitionSupportTicketStatusAction`.
The action authorizes the actor through `SupportTicketPolicy`, locks the ticket
row, and accepts only the transitions already exposed by the resource:

- `open` → `in_progress`, assigning the current operator
- `in_progress` → `resolved`

The Support policy is registered with Gate. A direct action call without
`update_support::ticket` is rejected, an allowed operator is assigned when the
ticket enters `in_progress`, and a direct `open` → `resolved` transition is
rejected. The guard is server-side; hiding a Filament action is only a UI aid.

## Current reachable workflow

- Customer/guest submission: `POST /api/v1/customer/support/tickets`.
- Submission is idempotent for 24 hours by a content hash and emits
  `SupportTicketCreated` after commit.
- Admin list/detail: Filament `SupportTicketResource` under
  `/admin/support-tickets`.
- Detail currently renders public ID, status, customer, assignee, email,
  subject, body, and creation time.
- Admin actions currently mark in progress and resolve; there is no existing
  reply, internal-note, reopen, assignment picker, status-history, or
  attachment action to preserve or test.

## Browser fixture for finance_integrity

Use a local test database fixture only. Create an admin/operator with these
`web` permissions:

- `view_any_support::ticket`
- `view_support::ticket`
- `update_support::ticket`

Create one `support_tickets` row with `status=open`, `assigned_to=null`, a
non-empty subject/body, and a generated 26-character `public_id`. Keep
`booking_id=null` for the current schema. Open:

`/admin/support-tickets/{public_id}`

When a database fixture is inconvenient, create the same guest ticket through
the existing local API and use the returned `data.public_id`:

```http
POST /api/v1/customer/support/tickets
Content-Type: application/json

{"subject":"Browser QA support workflow","body":"QA ticket for status and assignment verification.","email":"qa-support@example.test"}
```

The headed Browser check should verify the row appears in the list, the detail
page shows the public reference/customer/message, `Mark In Progress` changes
status and sets `assigned_to` to the operator, and `Resolve` changes status to
`resolved`. Capture the observed URL, operator identity, and resulting status.
Do not use a production ticket or a guessed replies fixture.

## Contract gaps retained as PARTIAL

The current migrations contain no priority, SLA/deadline, status-transition
history, replies/internal-notes, attachments, or immutable support audit table.
There is also no customer ticket-detail/reply route, and no registered listener
for `SupportTicketCreated` was found. Implementing those requires the missing
approved support contract, so they remain open rather than being inferred from
the issue prompt.

## Validation

- `tests/Feature/SupportTicketWorkflowTest.php`: **3 passed, 7 assertions**.
- Targeted Pint: **PASS**.
- Targeted PHPStan for Support action/resource/policy/model: **PASS**.
- PHP syntax checks for changed PHP files: **PASS**.

Changed files:

- `app/Modules/Support/Application/Actions/TransitionSupportTicketStatusAction.php`
- `app/Modules/Support/Domain/Models/SupportTicket.php`
- `app/Modules/Support/Filament/Resources/SupportTicketResource.php`
- `app/Modules/Support/Providers/SupportServiceProvider.php`
- `tests/Feature/SupportTicketWorkflowTest.php`
- `docs/qa/wave4-support-results.md`
