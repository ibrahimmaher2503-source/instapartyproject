# GitHub #94 — phone-change recovery

Local remediation, 2026-10-13. No reported vendor data, SMS credentials or production state was read or changed.

## Reproduction and change

An actual account-page action changed a valid phone number and cleared verification without redirecting to verification (`github94-red.log`: missing redirect). The account action now directs an unverified vendor to the existing verification page; saving an unchanged verified number does not redirect or invalidate verification. Its EN/AR explanation explicitly states that business access depends on successful verification.

A separate actual verification-page action reproduced the absence of a notification when the gateway rejected sending (`github94-send-red.log`). Expected gateway validation failures now show a danger notification using the existing localized error; they do not show the success notification or mark the phone verified. Independent review also found the expected send-limit exception bypassing that feedback. Actual limiter RED evidence is retained in `github94-throttle-red-final.log`. The page now catches that specific exception and shows the existing localized throttling message; the real limiter remains in force. Unexpected failures retain existing handling.

## Verification scope

The reproduction test is [VendorPhoneAccessRecoveryTest.php](../../tests/Feature/VendorPhoneAccessRecoveryTest.php). It checks actual authorized HTTP routes for Services, Categories, Sale Import, Reviews, Wallet and Bookings before change, after change (403), and after verification (200), in EN and AR. Only the external OTP boundary is replaced. Existing phone validation and verification tests cover malformed/duplicate numbers, preserved same-number verification, required email verification, and role/profile access.

The latest independently reviewed refinement passed 12 tests / 124 assertions (`github94-reviewed.log`, `github94-reviewed-junit.xml`), including EN/AR fourth-attempt feedback, exactly three gateway sends and an unverified timestamp. Scoped Pint passed. Earlier evidence lives in `.e2e/risk-program/remediation/`: `github94-junit.xml`, `github94-final.log`, both initial RED logs, and `github94-phpstan-2g.log`. Full configured PHPStan passed at that earlier scope with a 2 GB process limit. The first latest PHPStan attempt exhausted the default 128 MB limit; its failed output is retained in `github94-phpstan.log`. No product configuration was changed for the retry.

This does not prove SMS delivery on the originally affected environment. No tester.army browser or real SMS-provider run has been completed for this change. #94 remains open until those acceptance conditions are verified; source/Livewire/HTTP results are not hosted browser proof. The merged 681-test / 5927-assertion run predates this final recovery change; the focused run is the evidence for this later scope. No deployment or commit/push is claimed.
