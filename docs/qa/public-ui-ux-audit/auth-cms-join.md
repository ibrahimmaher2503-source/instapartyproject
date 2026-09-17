# Public desktop UI/UX audit: auth, CMS and join-us

Closed 2026-09-15 against the isolated 1440px Browser capture in `docs/qa/screenshots/public-desktop-2026-09-15`.

## Closure ledger

| Surface | Result | Browser evidence |
|---|---|---|
| Login, register, forgot and reset, EN + AR | PASS | Balanced auth composition, visible labels, clear primary actions, LTR account inputs, and 44px recovery targets. |
| Phone verification, EN + AR | PASS | Capture now performs registration first and reaches the real OTP screen. The Arabic title fits the card and the E.164 number keeps correct visual direction. |
| FAQ, EN + AR | PASS | Published questions and answers render with clear hierarchy in both directions. |
| Terms and privacy, EN + AR | PASS | Published body text renders as headings and paragraphs instead of a flat or empty card. |
| About, EN + AR | PASS | Published content remains readable and localized. |
| Contact, EN + AR | PASS with configuration state | Vendor application and location content render. When public email/phone are absent, the page truthfully reports that contact details are being updated and does not claim a nonexistent form. |
| Join us, EN + AR | PASS | Hero, actions, and four-step vendor path are correctly composed in LTR and RTL. |

## Closed defects

- Added a session-backed OTP capture and removed the previous false registration-screen evidence.
- Shortened the Arabic OTP heading and isolated the phone number direction.
- Parsed published CMS plain text into a visible heading/body hierarchy.
- Removed placeholder contact values and corrected the pending notice so it does not promise a form that is not present.
- Unified the Arabic InstaParty brand spelling and bundled storefront fonts locally.

No open desktop UI defect remains in these audited surfaces. Supplying production contact email/phone is an environment/content task and is intentionally not fabricated in local QA.
