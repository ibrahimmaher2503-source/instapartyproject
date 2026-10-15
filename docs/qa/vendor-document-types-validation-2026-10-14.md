# Vendor document types validation — 2026-10-14

The implementation adds a shared active document-type catalog, an authorized admin screen, bilingual names, per-business-type requirements and retirement preserving historical uploads.

Full-suite snapshot: 800 passed, 40 failed, 7243 assertions. Run: php vendor/bin/pest --compact. These are reported failures; this snapshot is not a green application release.

Reported failures:

- FAILED  Tests\Feature\AdminMfaSecurityTest > it confirms ownership and authenticator possession before storing en…
- FAILED  Tests\Feature\CommissionRefundAllocationTest > it allocates a payment-level refund across comm…  TypeError
- FAILED  Tests\Feature\CriticalAdminPageRenderTest > it renders sale provider booking and modification admin pages
- FAILED  Tests\Feature\FilamentTranslationCoverageTest > it has English and Arabic values for static Filament tran…
- FAILED  Tests\Feature\GitHubCatalogAdminFormsTest > it roundtrips bilingual structured schema options without fla…
- FAILED  Tests\Feature\GitHubCatalogAdminFormsTest > it refuses to hydrate unsupported options rather than silentl…
- FAILED  Tests\Feature\GitHubCatalogAdminFormsTest > it exposes category-specific fields and conve…  QueryException
- FAILED  Tests\Feature\GitHubCatalogAdminFormsTest > it exposes category-specific fields and converts major curren…
- FAILED  Tests\Feature\GitHubCatalogAdminFormsTest > it exposes category-specific fields and conve…  QueryException
- FAILED  Tests\Feature\GitHubCatalogAdminFormsTest > it exposes category-specific fields and conve…  QueryException
- FAILED  Tests\Feature\GitHubCatalogAdminFormsTest > it exposes category-specific fields and converts major curren…
- FAILED  Tests\Feature\GitHubCatalogAdminFormsTest > it exposes category-specific fields and conve…  QueryException
- FAILED  Tests\Feature\GitHubHomepagePackageLabelsTest > it distinguishes package name and descrip…  ErrorException
- FAILED  Tests\Feature\GitHubHomepagePackageLabelsTest > it distinguishes package name and descrip…  ErrorException
- FAILED  Tests\Feature\GitHubIssue5VendorChangeNotificationTest > it dispatches vendor change notifications for th…
- FAILED  Tests\Feature\GitHubIssue5VendorChangeNotificationTest > it persists the requested vendor corrections in…
- FAILED  Tests\Feature\GitHubPublicMediaFallbackTest > it renders homepage and listing fallbacks in…  ViewException
- FAILED  Tests\Feature\GitHubPublicMediaFallbackTest > it renders homepage and listing fallbacks in…  ViewException
- FAILED  Tests\Feature\GitHubVendorOnboardingIssuesTest > it keeps document edit hidden when an admin can r…  Error
- FAILED  Tests\Feature\GitHubVendorOnboardingIssuesTest > it excludes expired and superseded documents from the up…
- FAILED  Tests\Feature\HomepageOccasionPackagesTest > it loads ten published homepage packages with…  ViewException
- FAILED  Tests\Feature\HomepageOccasionPackagesTest > it keeps homepage packages third in the customer journey
- FAILED  Tests\Feature\HomepageOccasionPackagesTest > it preserves budget ranges and never labels f…  ViewException
- FAILED  Tests\Feature\HomepageOccasionPackagesTest > it keeps packages with unavailable occasion t…  ViewException
- FAILED  Tests\Feature\HomepageOccasionPackagesTest > it shows at most ten cards before JavaScript…   ViewException
- FAILED  Tests\Feature\PaymentsIntegrityRegressionTest > it runs refund processing…  NoMatchingExpectationException
- FAILED  Tests\Feature\PaymentsIntegrityRegressionTest > it records refund not found under the outer webhook key w…
- FAILED  Tests\Feature\StorefrontEventNotesTest > it replaces the celebrant field and stores complete escaped mult…
- FAILED  Tests\Feature\StorefrontEventNotesTest > it replaces the celebrant field and stores complete escaped mult…
- FAILED  Tests\Feature\StorefrontOptionalEventEndTest > it accepts start-only planner searches and still rejects e…
- FAILED  Tests\Feature\StorefrontOptionalEventEndTest > it accepts start-only planner searches and still rejects e…
- FAILED  Tests\Feature\StorefrontSearchGeographyTest > it requires a matching governorate whenever a city filters…
- FAILED  Tests\Feature\StorefrontSubcategoryFilterTest > it combines dependent subcategories with vendor type and…
- FAILED  Tests\Feature\StorefrontSubcategoryFilterTest > it combines dependent subcategories with vendor type and…
- FAILED  Tests\Feature\Storefront\PlannerSearchValidationTest > it requires the planner context before showing dis…
- FAILED  Tests\Feature\VendorProfileCorrectionPageTest > it allows only the owner to open the correction page befo…
- FAILED  Tests\Feature\VendorProfileCorrectionPageTest > it persists the requested correction and resubmits only t…
- FAILED  Tests\Feature\VendorProfileCorrectionPageTest > it denies guests and fails closed fo…  ValidationException
- FAILED  Tests\Feature\VendorRegistrationLifecycleTest > it registers through the Filament Livewire page with the…
- FAILED  Tests\Feature\VendorRegistrationLifecycleTest > it normalizes Filament contact values before validation a…

PHPStan: php -d memory_limit=2G vendor/bin/phpstan analyse --memory-limit=2G --no-progress --error-format=table reports duplicate validation keys in Booking/Resources/lang/ar/booking.php and Booking/Resources/lang/en/booking.php. No document-type code errors were reported.

Two related existing GitHubVendorOnboardingIssuesTest failures were reproduced using the pre-task VendorDocument and VendorDocumentFilamentResource classes loaded from the baseline backup: document edit visibility (getAction on null), and the 30-day expiry filter (empty result). The code was loaded through a temporary PHPUnit bootstrap without restoring or overwriting working files.

Production status: the checkout .env declares APP_ENV=production and MySQL. No migration, deployment, environment edit, commit or push was performed against it. SQLite migration and browser verification use only disposable test data. Native MySQL/MariaDB migration acceptance remains open.

Independent reviewer dispatch did not complete because the reviewer service returned a usage limit.

Focused verification: the document configuration file initially passed 20 tests / 148 assertions, including real Livewire create/edit/remove/upload/review forms, unauthorized settings changes, all five built-in types, a custom type, required document status/expiry, one/no required document approval, all product grants, expiry suspension exclusions and migration preservation. The final combined run completed 56 tests / 463 assertions with no assertion failures and exit 0, but every test reported the application bootstrap warning `The use statement with non-compound name 'Throwable' has no effect`. A subsequent run with `--display-warnings` confirmed the same warning for all 20 configuration tests. This final run is not described as warning-free. These source files were already changing outside this task; unrelated bootstrap code was preserved.

Browser verification uses only storage/vendor-document-types-qa.sqlite on port 8156. The admin custom-type creation, vendor upload, document approval, removal and preserved-history scenario passed. The final separate run of the one-required and zero-required scenarios passed both tests, including all three profile grants and vendor operational access. Three scenarios passed across these scoped runs. The first run also captured English, Arabic RTL and a 390px admin view; no production browser acceptance is claimed.

Commands:
- php vendor/bin/pest tests/Feature/VendorDocumentTypesManagementTest.php --bail --compact
- npx playwright test --config=tests/e2e/vendor-document-types.config.ts
- npx playwright test --config=tests/e2e/vendor-document-types.config.ts --grep='one required|zero required'
- php vendor/bin/pint --test [task PHP files]: passed
- PHPStan with task non-Filament files supplied explicitly and --memory-limit=2G: passed
- git diff --check for the affected source/documentation paths: passed

Browser uploads were finally run after the PHP tests finished, since their Livewire testing temporary disk is shared and simultaneous Storage::fake calls can remove a browser upload. File-content validation was preserved.

Screenshots:
- ../../storage/app/qa/vendor-document-types-2026-10-14/screenshots/admin-document-types-ar.png
- ../../storage/app/qa/vendor-document-types-2026-10-14/screenshots/admin-document-types-en.png
- ../../storage/app/qa/vendor-document-types-2026-10-14/screenshots/admin-document-types-ar-mobile.png
- ../../storage/app/qa/vendor-document-types-2026-10-14/screenshots/vendor-custom-upload.png
- ../../storage/app/qa/vendor-document-types-2026-10-14/screenshots/approved-one-document.png
- ../../storage/app/qa/vendor-document-types-2026-10-14/screenshots/approved-no-documents.png
