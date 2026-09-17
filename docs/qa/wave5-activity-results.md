# Wave 5 Activity Results

Date: 2026-09-13  
Scope: `BUG-ACTIVITY-001` and the related-path check for `UX-INTERVENTION-001`

## Scope decision

`UX-INTERVENTION-001` remains **PARTIAL / unchanged** in this wave. Its table is implemented by `AdminBookingInterventionResource`, while the reported activity problem is rendered by the Rmsramos activity-log resource. The two screens do not share a rendering path or a shared formatter, so changing the activity resource would not fix the intervention table.

## Proven root cause and local fix

The installed activity-log resource rendered `log_name=default` as `Default`, rendered a null or unknown `event` as `-`, and built the subject label with the database `subject_id` (for example, `User # 20`). The existing `ActivityPolicy` was also not registered with Gate, leaving the plugin's policy lookup unable to enforce the application's activity-log permissions.

The local fix is limited to the application resource and its two pages:

- `App\\Filament\\Resources\\ActivitylogResource` keeps the installed table and query, but labels legacy or unknown log/event data as `Legacy / Unknown` (or `قديم / غير معروف`) and shows a loaded subject's `public_id`; missing/deleted subjects remain explicitly legacy/unknown.
- The activity resource is configured to use that application resource, without editing `vendor/` or rewriting historical rows.
- `ActivityPolicy` is registered for `Spatie\\Activitylog\\Models\\Activity`, so `view_any_activitylog` and `view_activitylog` protect the existing page.
- The labels are loaded from the Shared EN/AR admin translation files.

## Verification

Focused Feature result: **PASS — 3 tests, 7 assertions** in `tests/Feature/ActivityAuditPresentationTest.php`.

Covered behavior:

- an authorized admin sees the legacy label and the subject `public_id`, while the rendered list does not expose `#<internal id>`;
- `?lang=ar` renders `قديم / غير معروف`;
- a user without the activity-log view permission receives the existing denial response (redirect or 403).

Pint: **PASS** for the changed PHP files.  
PHPStan: **PASS — 0 errors** for the new activity resource and its two pages using a temporary scoped configuration and a 1 GB analysis limit. The broader changed-file invocation also reaches an existing `TextInput::macro()` PHPDoc baseline error in `AppServiceProvider`; that unrelated macro was not changed for this slice.

## Contract boundary / remaining gaps

This is a presentation and authorization correction for the existing legacy rows. It does not fabricate an event contract, producer-side outcome/reason/correlation fields, or a migration for historical data. The inherited detail form and properties view still reflect the installed package's broader legacy record shape; full structured immutable audit coverage, field-level PII policy, producer coverage, filters, export, and Browser acceptance remain **PARTIAL** pending an approved contract and real Browser evidence.

## Browser fixture for coordinator

Use isolated local test data only:

- Create an admin user with `view_any_activitylog` and `view_activitylog` on the `web` guard.
- Create one synthetic `activity_log` row with `log_name=default`, `event=null`, `description=Synthetic activity fixture`, an existing `User` subject, and no real customer, payment, document, or credential data.
- Open `/admin/activitylogs` as that admin. EN should show `Legacy / Unknown` and `User · <public_id>`; `/admin/activitylogs?lang=ar` should show `قديم / غير معروف`.
- Confirm the visible subject label contains the public reference and does not contain `#<internal subject_id>`.

This fixture verifies the local list presentation and permission boundary. It does not prove a structured producer contract or full end-to-end audit acceptance.
