# GitHub remediation — batch 04

Branch: `codex/fix-github-issues-batch-04`  
Base: published batch 03, `d40a34e7bffc2f2e79e448157d5b25a556b82684`

## Fifteen primary issues

| Issue | Implementation scope |
|---|---|
| #6 | Filament logout logs out the active panel guard and rotates the shared session without deleting other guard entries. |
| #22 | Shared response middleware adds anti-framing, nosniff, referrer policy and HTTPS-only HSTS; CSP is limited to frame ancestors. |
| #27 | Forbidden responses have a bilingual error page matching the existing house layout. |
| #26 | Invalid search values return to a sanitized search page with visible localized feedback instead of silently returning home. |
| #29 | Search result counts distinguish singular/plural, including Arabic forms. |
| #54 | Inverted price bounds have explicit feedback and preserve the entered amounts. |
| #35 | Pending withdrawal rejection uses the permission actually granted by the existing seeder; reason, ledger release and audit behavior are retained. |
| #109 | Admin cancellation cancels actionable vendor allocations under parent-first locks, records transitions, invalidates caches and preserves queued notification recipients. |
| #111 | Customer booking/payment pages show recorded refund amounts and statuses for their own bookings through the Payments reader. |
| #113 | Submitted, cancelled and force-cancelled booking events have customer in-app channels and bilingual templates; external channels are retained. |
| #114 | Customer reviews identify their target and moderation state; only approved, type/target/vendor-matched responses are displayed. |
| #116 | Cancelled booking pages explain the preserved historical fulfillment statuses instead of changing persisted item history. |
| #117 | Force-cancel timeline entries resolve a bilingual action label. |
| #119 | Booking identity/navigation precede the Notes card. |
| #120 | Sale/rental category selectors preload within their existing limit and search translated names. |

## Related refund safety dependency

Seven backend files associated with #59 are included as cancellation/refund safety dependencies, without claiming the full #59 UI feature is complete. Refund reservations are serialized against the payment; completion state reflects cumulative completed refunds; matching persisted refund requests replay without blind gateway redispatch. HTTP idempotency hashes include method, URI and route as well as body. Existing entries using the earlier hash may return a conflict until their existing TTL expires, normally 24 hours. No legacy-hash fallback or persisted-row rewrite is introduced. Ambiguous pending gateway outcomes still require provider reconciliation.

Discounted full-refund gross-versus-cash allocation remains pending the owner's separate business answer. This batch changes no such policy, delivery-retention rule or historical ledger. Repeated force cancellation remains possible under the existing admin entry points; it can produce repeated audits/notifications. Stored vendor response deadlines remain historical; cancelled vendor rows no longer qualify for active response counters or deadline summaries. InProgress and terminal allocation states are preserved.

## Isolation and review boundaries

Only selected owned hunks are transferred. Original storefront geography/subcategory features, withdrawal timezone/reference-search changes and unrelated RQA helpers are excluded. The search snapshot has no subcategory feature to import. #121/#123 schema editing and #52 currency/deduction work are reserved for a later batch. Registration privacy and administrator bootstrap credentials are not part of this batch.

The user explicitly defers tests until the campaign finishes. No tests, test-source edits, browser interaction, application startup, lint/static-analysis commands, build or dependency installation are performed. One transfer agent accidentally ran a read-only Git whitespace check; it reported no diagnostics. This does not establish application acceptance. Existing test doubles for expanded reader contracts may need updates when test work resumes.

The selected-file allowlist is BATCH_04_FILES.txt. Two independent Luna Max source reviews found no remaining blocker in their assigned selected snapshot scopes after corrections: logout destroys the prior session record while preserving other current guard entries; city/governorate mismatch uses the visible sanitized search feedback path; refund completion takes the Payment lock before its constrained Refund row. Ownership, review-response approval/type/vendor matching, queued recipient capture, templates and bindings were reviewed against the isolated source. The existing refund-webhook path can redispatch the gateway for an ambiguous pending refund; this separately tracked #59 limitation remains under investigation for a later batch, and this branch is not deployment approval. Normal template rollout and working workers/providers remain runtime requirements. Real external delivery, reverse-proxy HTTPS/HSTS behavior, rendering and reported historical data are unverified. No merge, deployment, production-data repair or complete QA coverage is claimed.
