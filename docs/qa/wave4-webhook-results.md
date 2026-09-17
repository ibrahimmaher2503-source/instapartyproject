# Wave 4 Webhook verification — 2026-09-13

Scope: local isolated checkout only. No production data, secrets, `.env`, package or schema changes.

## Reproduced defects and bounded fixes

- Invalid or missing HMAC verification created a rejected webhook row with no processing reason. `ProcessPaymobWebhookAction` now records the sanitized `invalid_signature` code before returning HTTP 401.
- A parser exception or an empty transaction reference left a webhook row pending. The action now records `malformed_payload`; the parser exception is still rethrown for the existing retry/error behavior.
- Unknown payment and missing-refund branches stored gateway references or internal payment IDs in `processing_error`. They now store `unknown_gateway_reference` and `refund_not_found`.
- Processing failures from capture/refund execution now record `processing_failed` before the existing exception is rethrown.
- Idempotency keys now use the parsed transaction type, so event spelling changes do not create separate keys for the same transaction. Duplicate logs render as `Duplicate (idempotent)` / `مكرر (تم التعامل معه بأمان)` instead of Failed.
- Existing payload redaction now also removes HMAC/signature/auth fields and customer PII keys recursively. No raw payload was copied into this report.

## Evidence

- Focused Payments/Pest: **PASS — 13 tests, 60 assertions**.
- Focused Pint: **PASS** on the changed Payments implementation, presentation, helper and tests.
- Targeted PHPStan: existing model-property findings remain in `ProcessPaymobWebhookAction` (`Payment::$amount_minor` and `Payment::$amount_currency`); the redaction helper has no new findings. No baseline errors were hidden.
- Sequential/concurrent duplicate delivery: sequential idempotency is covered by the focused test. Real concurrent delivery remains **UNVERIFIED**.
- Import and Support fixtures are now stable for the local integration run. Full Pest passed with 131 tests / 1,262 assertions; Full Playwright passed with 15 tests / one worker. The Browser webhook slice passed, while concurrent delivery remains UNVERIFIED.

## Files

- `app/Modules/Payments/Application/Actions/ProcessPaymobWebhookAction.php`
- `app/Modules/Payments/Infrastructure/Support/RedactPciFields.php`
- `app/Modules/Payments/Filament/Resources/GatewayWebhookLogResource.php`
- `app/Modules/Payments/Resources/lang/en/payments.php`
- `app/Modules/Payments/Resources/lang/ar/payments.php`
- `tests/Feature/PaymentsIntegrityRegressionTest.php`
- `tests/Feature/GatewayWebhookLogPresentationTest.php`
- `tests/e2e/auth/global.setup.ts`
- `tests/e2e/admin-audit.spec.ts`
