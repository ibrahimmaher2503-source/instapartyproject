# API Registry

| Method | Endpoint | Module | Phase | Auth | Roles | Request Body | Response | Documented |
|---|---|---|---|---|---|---|---|---|
| POST | /api/v1/register/vendor | Identity | Phase 1 | Public, throttled | Guest | name, email, phone_e164, password + confirmation, bilingual business_name, business_type, governorate/city, locale | 201 VendorProfile; meta registration_successful and verification_pending | Scribe + Bruno |
| GET | /api/v1/admin/vendor-profiles | Identity | Phase 1 | Sanctum | Admin with VendorProfile viewAny policy | Query: status, search, per_page | Paginated VendorProfile collection | Scribe |
| POST | /api/v1/admin/vendor-profiles/{vendor_public_id}/reactivate | Identity | Phase 1 | Sanctum | Admin with suspend_vendor | None | 200 VendorProfile | Scribe + Bruno |
