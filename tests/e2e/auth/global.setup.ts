import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test as setup } from '@playwright/test';

const email = 'uat-admin@example.test';
const password = 'UatTest123!';
const authFile = path.resolve('tests/e2e/auth/.auth-state.json');
const environment = {
    ...process.env,
    APP_ENV: 'testing',
    APP_DEBUG: 'true',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.resolve('storage/e2e.sqlite'),
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync',
};

const runPhp = (_label: string, args: string[]) => execFileSync('php', args, { env: environment, stdio: 'inherit', timeout: 30_000 });

setup('create isolated database and authenticate admin', async ({ page }) => {
    setup.setTimeout(120_000);
    runPhp('migrate', ['artisan', 'migrate:fresh', '--force']);
    runPhp('seed-admin', ['artisan', 'e2e:seed-admin', `--email=${email}`, `--password=${password}`]);
    runPhp('geography-seeder', ['artisan', 'db:seed',
        '--class=App\\Modules\\Geography\\Database\\Seeders\\EgyptGeographySeeder', '--force']);
    runPhp('catalog-seeder', ['artisan', 'db:seed',
        '--class=App\\Modules\\Catalog\\Database\\Seeders\\CatalogSeeder', '--force']);
    const permissionSetup = [
        'view_any_occasion', 'create_occasion', 'view_any_category', 'create_category',
        'view_any_loyalty::program', 'create_loyalty::program',
        'view_any_rental::service', 'create_rental::service',
        'view_any_package::recommendation', 'create_package::recommendation',
        'view_any_booking::modification', 'view_any_reconciliation::run',
        'view_any_notification::template', 'create_notification::template',
        'view_any_notification::dispatch', 'view_any_campaign',
        'view_any_saved::search', 'view_any_search::log',
        'view_any_role', 'view_role', 'update_role',
        'view_any_vendor::profile', 'view_vendor::profile',
        'view_any_vendor::approved::product::type',
        'view_any_sale::service', 'view_sale::service', 'publish_sale_service',
        'service.create.sale.own', 'service.update.sale.own',
        'service.delete.sale.own', 'service.publish.sale.own',
        'approve_vendor_profile', 'approve_vendor_for_type',
        'view_any_service::inventory::reservation',
        'view_any_vendor::document::filament', 'view_vendor::document::filament',
        'update_vendor::document::filament', 'review_vendor_documents',
        'view_any_excel::import', 'view_excel::import',
        'view_any_support::ticket', 'view_support::ticket', 'update_support::ticket',
        'view_any_gateway::webhook::log', 'view_gateway::webhook::log',
        'view_any_withdrawals::queue', 'view_withdrawals::queue', 'withdrawal.view_audit',
        'chat_moderation.view',
    ];
    runPhp('permissions-tinker', ['artisan', 'tinker', `--execute=foreach (['super_admin','admin','support','finance','vendor','customer'] as $roleName) { Spatie\\Permission\\Models\\Role::findOrCreate($roleName,'web'); } $role=Spatie\\Permission\\Models\\Role::findByName('super_admin','web'); foreach (${JSON.stringify(permissionSetup)} as $name) { $permission=Spatie\\Permission\\Models\\Permission::firstOrCreate(['name'=>$name,'guard_name'=>'web']); $role->givePermissionTo($permission); } $vendorRole=Spatie\\Permission\\Models\\Role::findByName('vendor','web'); $vendorRole->givePermissionTo(['view_any_category','view_any_rental::service','service.update.sale.own']);`]);
    execFileSync('php', ['artisan', 'tinker', "--execute=App\\Modules\\Subscriptions\\Domain\\Models\\SubscriptionPlan::query()->firstOrCreate(['plan_code'=>'free'], ['public_id'=>(string) Illuminate\\Support\\Str::ulid(), 'name'=>['en'=>'Free','ar'=>'مجاني'], 'description'=>['en'=>'Free vendor tier','ar'=>'الباقة المجانية للمورد'], 'monthly_price_minor'=>0, 'monthly_price_currency'=>'EGP', 'yearly_price_minor'=>0, 'yearly_price_currency'=>'EGP', 'is_default'=>true, 'is_published'=>true, 'display_order'=>1]);"], {
        env: environment,
        stdio: 'inherit',
    });
    execFileSync('php', ['artisan', 'tinker', '--execute=App\\Modules\\Reviews\\Domain\\Models\\ServiceReview::factory()->create([\'body\'=>\'E2E oldest pending review\',\'created_at\'=>now()->subDays(10),\'updated_at\'=>now()->subDays(10)]); App\\Modules\\Reviews\\Domain\\Models\\ServiceReview::factory()->create([\'body\'=>\'E2E newer pending review\',\'created_at\'=>now()->subDay(),\'updated_at\'=>now()->subDay()]); App\\Modules\\Communication\\Domain\\Models\\ChatModerationFlag::factory()->create([\'created_at\'=>now()->subDays(8)]); $user=App\\Modules\\Identity\\Domain\\Models\\User::where(\'email\',\'uat-admin@example.test\')->firstOrFail(); App\\Modules\\Discovery\\Domain\\Models\\SavedSearch::factory()->create([\'user_id\'=>$user->id,\'label\'=>\'E2E readable filters\',\'filters\'=>[\'product_types\'=>[\'rental\'],\'city\'=>\'Nasr City\',\'max_price_minor\'=>300000]]); App\\Modules\\Discovery\\Domain\\Models\\SearchLog::factory()->create([\'user_id\'=>$user->id,\'query\'=>\'\',\'filters\'=>[\'product_type\'=>\'rental\']]);'], {
        env: environment,
        stdio: 'inherit',
    });
    execFileSync('php', ['artisan', 'tinker', '--execute=$customer=App\\Modules\\Identity\\Domain\\Models\\User::factory()->create([\'name\'=>\'E2E Inventory Customer\',\'email\'=>\'e2e-inventory@example.test\']); $governorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->where(\'code\',\'EG-CAI\')->firstOrFail(); $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where(\'name->en\',\'Nasr City\')->firstOrFail(); $vendorUser=App\\Modules\\Identity\\Domain\\Models\\User::factory()->phoneVerified()->asVendor()->create(); $vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->create([\'user_id\'=>$vendorUser->id,\'primary_governorate_id\'=>$governorate->id,\'primary_city_id\'=>$city->id,\'business_name\'=>[\'en\'=>\'E2E Document Vendor\',\'ar\'=>\'مورد مستند الاختبار\']]); $service=App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->sale()->published()->create([\'public_id\'=>\'01K8W0E2E0AAAAAAAAAAAAAAAA\',\'vendor_profile_id\'=>$vendor->id,\'name\'=>[\'en\'=>\'E2E Readable Inventory Service\',\'ar\'=>\'خدمة مخزون واضحة\']]); App\\Modules\\Catalog\\Domain\\Models\\ServiceInventoryReservation::factory()->expired()->create([\'service_id\'=>$service->id,\'user_id\'=>$customer->id,\'product_type\'=>\'sale\',\'reserved_starts_at\'=>null,\'reserved_ends_at\'=>null]); Illuminate\\Support\\Facades\\Storage::disk(\'local\')->put(\'vendors/e2e-review.pdf\',\'E2E document\'); App\\Modules\\Identity\\Domain\\Models\\VendorDocument::factory()->create([\'vendor_profile_id\'=>$vendor->id,\'file_path\'=>\'vendors/e2e-review.pdf\',\'file_name\'=>\'هوية-المورد.pdf\',\'expires_at\'=>today()->addMonth()]);'], {
        env: environment,
        stdio: 'inherit',
    });
    execFileSync('php', ['artisan', 'tinker', '--execute=$admin=App\\Modules\\Identity\\Domain\\Models\\User::where(\'email\',\'uat-admin@example.test\')->firstOrFail(); $vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::query()->latest(\'id\')->firstOrFail(); $import=App\\Modules\\Catalog\\Domain\\Models\\ExcelImport::query()->updateOrCreate([\'original_filename\'=>\'E2E import failure.xlsx\'],[\'public_id\'=>(string) Illuminate\\Support\\Str::ulid(),\'vendor_profile_id\'=>$vendor->id,\'product_type\'=>\'sale\',\'status\'=>\'failed\',\'stored_path\'=>\'imports/e2e/failure.xlsx\',\'total_rows\'=>1,\'imported_rows\'=>0,\'error_rows\'=>1]); App\\Modules\\Catalog\\Domain\\Models\\ExcelImportError::query()->updateOrCreate([\'excel_import_id\'=>$import->id,\'row_number\'=>2],[\'field\'=>\'name_en\',\'row_data\'=>null,\'message\'=>[\'en\'=>\'The file could not be processed.\',\'ar\'=>\'تعذر معالجة الملف.\']]); App\\Modules\\Support\\Domain\\Models\\SupportTicket::query()->updateOrCreate([\'subject\'=>\'E2E support ticket\'],[\'public_id\'=>(string) Illuminate\\Support\\Str::ulid(),\'user_id\'=>null,\'email\'=>\'e2e-support@example.test\',\'body\'=>\'E2E support request context.\',\'status\'=>\'in_progress\',\'assigned_to\'=>$admin->id]); App\\Modules\\Payments\\Domain\\Models\\GatewayWebhookLog::query()->delete(); App\\Modules\\Payments\\Domain\\Models\\GatewayWebhookLog::factory()->create([\'event_type\'=>\'payment.captured\',\'signature_valid\'=>false,\'payload\'=>[\'pan\'=>\'4111111111111111\',\'email\'=>\'customer@example.test\'],\'processed_at\'=>null,\'processing_error\'=>\'invalid_signature\']); App\\Modules\\Payments\\Domain\\Models\\GatewayWebhookLog::factory()->create([\'event_type\'=>\'payment.captured\',\'signature_valid\'=>true,\'payload\'=>[\'gateway_ref\'=>\'E2E duplicate\'],\'processed_at\'=>now(),\'processing_error\'=>\'duplicate_idempotent_replay\']); App\\Modules\\Settlement\\Domain\\Models\\Withdrawal::factory()->rejected()->create([\'vendor_profile_id\'=>$vendor->id,\'requested_by_user_id\'=>$vendor->user_id,\'requested_amount_minor\'=>65000,\'requested_amount_currency\'=>\'EGP\',\'requested_at\'=>now()->subDays(3),\'admin_payment_note\'=>[\'en\'=>[\'unexpected\'=>\'nested value\'],\'ar\'=>[\'unexpected\'=>\'قيمة متداخلة\']]]); App\\Modules\\Payments\\Domain\\Models\\Refund::factory()->create([\'amount_minor\'=>65000,\'amount_currency\'=>\'EGP\',\'reason_code\'=>App\\Modules\\Payments\\Domain\\Enums\\RefundReasonCode::CustomerRequest,\'reason_notes\'=>[\'en\'=>\'E2E refund request\',\'ar\'=>\'طلب استرداد للاختبار\'],\'status\'=>App\\Modules\\Payments\\Domain\\Enums\\RefundStatus::Pending]);'], {
        env: environment,
        stdio: 'inherit',
    });

    await page.goto('/admin/login', { waitUntil: 'domcontentloaded', timeout: 30_000 });
    await page.locator('[wire\\:model="data.email"]').fill(email);
    await page.locator('[wire\\:model="data.password"]').fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/admin$/, { timeout: 15_000 });
    await page.context().storageState({ path: authFile });
});
