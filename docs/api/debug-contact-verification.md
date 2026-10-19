# Contact verification in debug mode

`APP_DEBUG=true` disables phone OTP delivery and phone/email contact verification requirements. `APP_DEBUG=false` restores normal verification. This uses Laravel's `app.debug` configuration, including cached configuration; after changing the environment setting, refresh the application's configuration cache through the normal deployment process.

- Storefront customers are signed in immediately after a valid registration. Pending storefront verification sessions can continue without entering a code.
- `POST /api/v1/register/customer` keeps its existing response. Customers can sign in using their password; `POST /api/v1/phone/verify` also accepts an omitted `code` in debug mode. A registered, non-deleted phone is still required. Outside debug, a six-digit valid code is required.
- `POST /api/v1/phone/otp/send` returns `data.sent=false` and does not send an OTP or consume the phone OTP attempt budget in debug mode. Outside debug it returns `data.sent=true` after sending. The existing HTTP throttle still applies.
- Vendor registration emails are skipped. Approved vendors can use the portal and API without verified email or phone dates. The phone page hides its code controls.
- `POST /api/v1/register/vendor` reports `meta.verification_pending=false` in debug and `true` otherwise. Vendor approval remains required.

The bypass does not write verification dates or fire verification events. Disabling debug restores the stored verification state immediately. Passwords, account suspension, role/ownership checks, vendor approval, password-reset tokens and admin MFA retain their existing requirements. Keep debug disabled on public/production installations: the debug phone verification endpoint can issue a token for a registered phone without proof of phone ownership.
