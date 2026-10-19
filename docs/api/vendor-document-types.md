# Vendor document types and approval

Admins with `review_vendor_documents` can manage document types at `/admin/vendor-document-types` (Vendor onboarding → Vendor document types).

- Add a type with non-empty English and Arabic names.
- Select the business types that must provide it: individual, company or establishment. Leave every option unchecked to make it optional for everyone.
- Edit names and requirements at any time. Changes apply to vendor upload options, approval, onboarding and document expiry enforcement.
- Remove a type to stop new uploads and exclude it from approval requirements. Uploaded files, their reviews and the type's historical label remain available.

The vendor Documents page lists all active types, regardless of business type, and shows the types required for the current vendor. PDF, JPEG and PNG uploads retain the 10 MB limit, private storage, ownership checks and file-content validation. Rejected documents can be replaced only while their type remains active.

Approval uses the latest document for every required type. Each must be approved and unexpired. One required type needs only one approved file. When no types are required, no uploads are needed and optional pending, rejected or expired documents do not block approval or trigger expiry suspension. Other account eligibility checks still apply. Rental, sale and digital grants remain separate admin actions after profile approval.

Existing API routes are unchanged. `doc_type` accepts an active type's stable `key`: the legacy values `cr`, `tax_card`, `national_id`, `iban_proof`, `other`, or the key assigned when an admin creates a type. Responses retain the same `doc_type` string and never expose private storage paths. A removed type fails validation for new uploads; existing documents remain readable by authorized owners and reviewers.

Deployment requires `2026_10_14_000001_configure_vendor_document_types`. It preserves the initial business-type requirements, seeds the five existing types and widens `vendor_documents.doc_type` to accept custom keys without removing existing files or records. Rollback keeps the wider document column to preserve custom document keys.
