# GitHub #97 and #99 — document safety and coverage feedback

2026-10-13, isolated native SQLite fixtures. No reported user files, private production storage or provider credentials were accessed.

## #97 documents

The upload action accepted actual text bytes named `qa-fake.pdf` and stored a Pending record. RED evidence: `.e2e/risk-program/remediation/github97-content-red.log`. Both initial-upload and replacement-upload actions now validate file MIME/content-derived type and the existing 10 MB limit before storage, persistence or the document-upload event. Client MIME declarations do not determine actual bytes; the test reconstructs a normal UploadedFile whose declared header is PDF but whose bytes are text.

Actual modal reproduction also found validation errors were not presented as a notification after the action rejected spoofed MIME. Both upload actions now show their existing localized file-validation message and halt, leaving no newly stored file, record or event. Genuine RED: `github97-modal-red-final.log`; an earlier test-helper misuse is retained separately as `github97-modal-red.log` and is not claimed as product RED.

The user explicitly approved deletion of Pending and Rejected documents while protecting Approved documents, superseding the rejected-only comment referencing the unavailable ADR-0021. Both API and panel use the same action. It locks the current persisted document, checks its owner and current status, deletes only eligible rows, then removes the private file after commit using the same disk-selection rule as upload. A stale Pending instance whose persisted row is now Approved remains protected. The panel delete action is shown only for the two allowed statuses; approved rows and foreign-owner files remain intact. RED evidence for the absent pending delete control: `github97-delete-red.log`.

Tests: [VendorDocumentContentValidationTest.php](../../tests/Feature/VendorDocumentContentValidationTest.php), [VendorDocumentDeletionSafetyTest.php](../../tests/Feature/VendorDocumentDeletionSafetyTest.php), existing upload/lifecycle/onboarding/marketplace tests. Their empty fake-PDF success fixtures were replaced with an actual minimal PDF at `tests/Fixtures/vendor-document.pdf`; existing expected success/persistence assertions were preserved. The new content check was not weakened to accept empty fake bytes.

## #99 coverage

After a real failed add-area submission, selecting a valid city left the city-required error. RED: `github99-red.log`. The Select now sends live updates and validates only its actual action-form state path. EN/AR regressions prove the corrected city error clears, an unrelated delivery-fee error remains, clearing the city reinstates its required error, and nothing is saved until submission. A separate local edit/reload check preserves city row ordering; no shared coverage-replacement semantics or ordering policy was changed.

Test: [VendorCoverageValidationFeedbackTest.php](../../tests/Feature/VendorCoverageValidationFeedbackTest.php). Its second error assertion compares the preserved actual validation message because a successful validateOnly call does not retain Livewire's earlier failed-rule metadata. The business expectation remains unchanged.

## Validation and limits

Combined native run including both document fixes, coverage feedback, #91 review-response behavior and #100 admin-home investigation passed **25 tests / 352 assertions** (`github97-99-final.log`, `github97-99-final-junit.xml`). The later coverage edit-order check passed **3 / 47** (`github99-final.log`, `github99-final-junit.xml`). Scoped Pint passed. Full configured PHPStan passed at the recorded run scope (`github97-99-phpstan.log`); further agents are editing other scoped issues, so this is not their final merged gate.

Independent review and tester.army browser acceptance are ongoing. The reported 30-second upload latency, original hosted ordering behavior, real SMS and production storage delivery have not been verified. No production document deletion, commit, push or deployment is claimed.
