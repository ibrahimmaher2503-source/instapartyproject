import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const runId = Date.now().toString();
const customerEmail = `qa-customer-enhancements-${runId}@example.test`;
const ticketSubject = `QA customer help ${runId}`;
const categorySlug = `qa-customer-enhancements-${runId}`;
const bookingPublicIds = [0, 1].map((index) => `01${(BigInt(runId) * 10n + BigInt(index)).toString().padStart(24, '0')}`);
const bookingReference = `QA-${runId}`;
const vendorPublicId = `01${(BigInt(runId) * 10n + 2n).toString().padStart(24, '0')}`;
const servicePublicId = `01${(BigInt(runId) * 10n + 3n).toString().padStart(24, '0')}`;
const vendorSlug = `qa-customer-enhancements-${runId}`;
const questionEn = `How can I check refund status ${runId}?`;
const questionAr = `كيف أتابع حالة الاسترداد؟ ${runId}`;
const password = 'CustomerUat123!';
const environment = {
    ...process.env,
    APP_ENV: 'testing',
    APP_DEBUG: 'true',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.resolve('storage/e2e.sqlite'),
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    SCOUT_DRIVER: 'null',
};

let refundPolicyCreatedByTest = false;
let refundPolicyMenuItemIdCreatedByTest: number | null = null;

function runPhp(execute: string, successMarker: string): string {
    const wrapped = `try { ${execute}\necho "\\n${successMarker}"; } catch (\\Throwable $exception) { fwrite(STDERR, 'QA_TINKER_ERROR:'.$exception::class.':'.$exception->getMessage().PHP_EOL); exit(1); }`;
    const result = spawnSync('php', ['artisan', 'tinker', `--execute=${wrapped}`], {
        env: environment,
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
        timeout: 30_000,
    });
    const stdout = result.stdout ?? '';
    const stderr = result.stderr ?? '';
    const output = `${stdout}\n${stderr}`;

    if (result.error || result.status !== 0 || !output.includes(successMarker)) {
        throw new Error(`PHP fixture command did not complete successfully (${successMarker}). ${output.slice(-3000)}`);
    }

    return stdout;
}

function seedFixtures(): void {
    const policyState = runPhp(`DB::transaction(function () {
        $policyMissing = !App\\Modules\\Shared\\Domain\\Models\\CmsPage::query()->where('slug','refund-policy')->exists();
        $footer = App\\Modules\\Shared\\Domain\\Models\\NavigationMenu::query()->where('slot','footer_secondary')->first();
        $policyMenuItemMissing = $footer === null || !$footer->items()->where('target_value','refund-policy')->exists();
        if ($policyMissing) { app(Database\\Seeders\\RefundPolicyCmsPageSeeder::class)->run(); }
        $footer = App\\Modules\\Shared\\Domain\\Models\\NavigationMenu::query()->where('slot','footer_secondary')->first();
        $policyMenuItem = $policyMenuItemMissing && $footer !== null ? $footer->items()->where('target_value','refund-policy')->first() : null;
        echo 'QA_POLICY_STATE:'.json_encode(['created'=>$policyMissing,'menu_item_id'=>$policyMenuItem?->id]);
    });`, 'QA_POLICY_COMMAND_OK');
    const policyStateMatch = policyState.match(/QA_POLICY_STATE:(\{[^\r\n]+\})/);
    if (!policyStateMatch) {
        throw new Error('Could not determine whether the canonical refund policy was created for this browser fixture.');
    }
    const policyStateValue = JSON.parse(policyStateMatch[1]) as { created: boolean; menu_item_id: number | null };
    refundPolicyCreatedByTest = policyStateValue.created;
    refundPolicyMenuItemIdCreatedByTest = policyStateValue.menu_item_id;

    const faqCategoryName = `QA Customer Help ${runId}`;
    const faqCategoryNameAr = `مساعدة العميل ${runId}`;
    const faqAnswerEn = `Use the payment status shown on your invoice. Reference ${runId}.`;
    const faqAnswerAr = `راجع حالة الدفع الظاهرة في الفاتورة. مرجع ${runId}.`;
    const execute = `DB::transaction(function () {
        $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('is_active',true)->firstOrFail();
        $governorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->findOrFail($city->governorate_id);
        $customer=App\\Modules\\Identity\\Domain\\Models\\User::factory()->phoneVerified()->asCustomer()->create([
            'name'=>'QA Customer Enhancements ${runId}',
            'email'=>'${customerEmail}',
            'password'=>'${password}',
        ]);
        $draftBooking=App\\Modules\\Booking\\Domain\\Models\\Booking::factory()->draft()->create([
            'public_id'=>'${bookingPublicIds[0]}',
            'customer_id'=>$customer->id,
        ]);
        App\\Modules\\Booking\\Domain\\Models\\Booking::factory()->confirmed()->create([
            'public_id'=>'${bookingPublicIds[1]}',
            'reference_no'=>'${bookingReference}',
            'customer_id'=>$customer->id,
            'subtotal_minor'=>10000,
            'total_minor'=>10000,
        ]);
        $vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create([
            'public_id'=>'${vendorPublicId}',
            'slug'=>'${vendorSlug}',
            'primary_city_id'=>$city->id,
            'primary_governorate_id'=>$governorate->id,
        ]);
        $category=App\\Modules\\Catalog\\Domain\\Models\\Category::factory()->create([
            'code'=>'qa-customer-enhancements-${runId}',
            'allowed_product_types'=>['sale'],
        ]);
        $service=App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->sale()->published()->create([
            'public_id'=>'${servicePublicId}',
            'vendor_profile_id'=>$vendor->id,
            'category_id'=>$category->id,
            'moderated_by'=>$customer->id,
        ]);
        $draftVendor=App\\Modules\\Booking\\Domain\\Models\\BookingVendor::factory()->create([
            'booking_id'=>$draftBooking->id, 'vendor_profile_id'=>$vendor->id,
        ]);
        App\\Modules\\Booking\\Domain\\Models\\BookingItem::factory()->sale()->create([
            'booking_vendor_id'=>$draftVendor->id, 'service_id'=>$service->id,
            'quantity'=>1, 'unit_price_minor'=>10000, 'unit_price_currency'=>'EGP',
            'line_total_minor'=>10000, 'line_total_currency'=>'EGP',
        ]);
        $media=$vendor->media()->create([
            'collection_name'=>'portfolio',
            'name'=>'QA portfolio ${runId}',
            'file_name'=>'portfolio-${runId}.png',
            'mime_type'=>'image/png',
            'disk'=>'public',
            'size'=>68,
            'manipulations'=>[],
            'custom_properties'=>[],
            'generated_conversions'=>[],
            'responsive_images'=>[],
        ]);
        $faqCategory=App\\Modules\\Support\\Domain\\Models\\FaqCategory::query()->create([
            'slug'=>'${categorySlug}',
            'name'=>['en'=>'${faqCategoryName}','ar'=>'${faqCategoryNameAr}'],
            'is_active'=>true,
        ]);
        $faqItem=$faqCategory->items()->create([
            'question'=>['en'=>'${questionEn}','ar'=>'${questionAr}'],
            'answer'=>['en'=>'${faqAnswerEn}','ar'=>'${faqAnswerAr}'],
            'is_active'=>true,
        ]);
        Illuminate\\Support\\Facades\\Storage::disk('public')->put($media->getPathRelativeToRoot(), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/2s8AAAAASUVORK5CYII='));
    });`;

    runPhp(execute, 'QA_CUSTOMER_FIXTURE_SEED_OK');
    runPhp(`$faqFromPublicQuery=app(App\\Modules\\Support\\Infrastructure\\Repositories\\EloquentFaqRepository::class)
        ->getAllActiveCategories()
        ->first(fn ($candidate) => $candidate->slug === '${categorySlug}');
        if ($faqFromPublicQuery === null || !$faqFromPublicQuery->items->contains(fn ($item) => $item->getTranslation('question', 'en') === '${questionEn}' && $item->getTranslation('question', 'ar') === '${questionAr}')) {
            throw new RuntimeException('Customer enhancement FAQ fixture was not returned by the public active FAQ query.');
        }`, 'QA_CUSTOMER_PUBLIC_FAQ_QUERY_OK');
}

function cleanupFixtures(): void {
    const execute = `$ticketIds=App\\Modules\\Support\\Domain\\Models\\SupportTicket::query()->where('subject','${ticketSubject}')->pluck('id');
        App\\Modules\\Communication\\Domain\\Models\\AdminInboxItem::query()->where('source_type','support_ticket')->whereIn('source_id',$ticketIds)->delete();
        App\\Modules\\Support\\Domain\\Models\\SupportTicket::query()->whereIn('id',$ticketIds)->delete();
        $category=App\\Modules\\Support\\Domain\\Models\\FaqCategory::query()->where('slug','${categorySlug}')->first();
        if ($category !== null) { $category->items()->delete(); $category->delete(); }
        App\\Modules\\Booking\\Domain\\Models\\Booking::query()->whereIn('public_id',${JSON.stringify(bookingPublicIds)})->get()->each(fn ($booking) => $booking->forceDelete());
        $service=App\\Modules\\Catalog\\Domain\\Models\\Service::query()->where('public_id','${servicePublicId}')->first();
        $categoryId=$service?->category_id;
        $service?->forceDelete();
        $vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::query()->where('public_id','${vendorPublicId}')->first();
        $vendorUserId=$vendor?->user_id;
        if ($vendor !== null) { $vendor->clearMediaCollection('portfolio'); $vendor->forceDelete(); }
        if ($categoryId !== null) { App\\Modules\\Catalog\\Domain\\Models\\Category::query()->whereKey($categoryId)->forceDelete(); }
        if ($vendorUserId !== null) { App\\Modules\\Identity\\Domain\\Models\\User::query()->whereKey($vendorUserId)->forceDelete(); }
        $customer=App\\Modules\\Identity\\Domain\\Models\\User::query()->where('email','${customerEmail}')->first();
        $customer?->tokens()->delete();
        $customer?->forceDelete();
        if (${refundPolicyMenuItemIdCreatedByTest ?? 'null'} !== null) {
            App\\Modules\\Shared\\Domain\\Models\\NavigationMenuItem::query()->whereKey(${refundPolicyMenuItemIdCreatedByTest ?? 'null'})->delete();
        }
        if (${refundPolicyCreatedByTest ? 'true' : 'false'}) {
            App\\Modules\\Shared\\Domain\\Models\\CmsPage::query()->where('slug','refund-policy')->delete();
            Illuminate\\Support\\Facades\\Cache::forget('theme:menus:footer_secondary:en');
            Illuminate\\Support\\Facades\\Cache::forget('theme:menus:footer_secondary:ar');
        }`;

    runPhp(execute, 'QA_CUSTOMER_FIXTURE_CLEANUP_OK');
}

test.beforeAll(() => {
    seedFixtures();
});

test.afterAll(() => {
    cleanupFixtures();
});

test('shows real bilingual help, policy, and customer invoice flows', async ({ browser }) => {
    test.setTimeout(120_000);
    const context = await browser.newContext({
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8139',
        locale: 'en-GB',
        timezoneId: 'Africa/Cairo',
        storageState: { cookies: [], origins: [] },
    });
    const page = await context.newPage();

    try {
        const helpResponse = await page.goto('/en/help');
        expect(helpResponse?.status()).toBe(200);
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
        await expect(page.locator('[data-faq-item]').filter({ hasText: questionEn })).toBeVisible();

        const ticketForm = page.locator('[data-support-ticket-form]');
        await ticketForm.locator('input[name="subject"]').fill(ticketSubject);
        await ticketForm.locator('input[name="email"]').fill(`qa-support-${runId}@example.test`);
        await ticketForm.locator('textarea[name="body"]').fill(`Please help verify customer support flow ${runId}.`);
        await ticketForm.locator('button[type="submit"]').click();
        const ticketSuccess = page.locator('[data-support-ticket-success]');
        await expect(ticketSuccess).toBeVisible();
        await expect(ticketSuccess).toContainText(/\b[0-9A-HJKMNP-TV-Z]{26}\b/);
        expect(runPhp(`$count=App\\Modules\\Support\\Domain\\Models\\SupportTicket::query()->where('subject','${ticketSubject}')->where('email','qa-support-${runId}@example.test')->count(); echo 'SUPPORT_TICKET_COUNT:'.$count;`, 'QA_SUPPORT_TICKET_QUERY_OK')).toContain('SUPPORT_TICKET_COUNT:1');

        const arabicHelpResponse = await page.goto('/ar/help');
        expect(arabicHelpResponse?.status()).toBe(200);
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.locator('[data-faq-item]').filter({ hasText: questionAr })).toBeVisible();
        await expect(page.locator('[data-support-ticket-form]')).toBeVisible();

        for (const locale of ['en', 'ar']) {
            const serviceResponse = await page.goto(`/${locale}/services/${servicePublicId}`);
            expect(serviceResponse?.status()).toBe(200);
            await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
            const servicePortfolio = page.locator('[data-vendor-portfolio]');
            await expect(servicePortfolio).toBeVisible();
            await expect(servicePortfolio).toContainText(locale === 'ar' ? 'معرض أعمال البائع' : 'Vendor portfolio');
            await expect(servicePortfolio.locator('img')).toHaveCount(1);
            await expect(servicePortfolio.locator('img')).toHaveAttribute('src', new RegExp(runId));

            const vendorResponse = await page.goto(`/${locale}/vendors/${vendorSlug}`);
            expect(vendorResponse?.status()).toBe(200);
            const vendorPortfolio = page.locator('[data-vendor-portfolio]');
            await expect(vendorPortfolio).toBeVisible();
            await expect(vendorPortfolio).toContainText(locale === 'ar' ? 'معرض أعمال البائع' : 'Vendor portfolio');
            await expect(vendorPortfolio.locator('img')).toHaveCount(1);
            await expect(vendorPortfolio.locator('img')).toHaveAttribute('src', new RegExp(runId));
        }

        await page.goto('/en/auth/login');
        await page.locator('input[name="login"]').fill(customerEmail);
        await page.locator('input[name="password"]').fill(password);
        await page.locator('form[data-auth-form] button[type="submit"]').click();
        await expect(page).toHaveURL(/\/en\/?$/);

        const cartResponse = await page.goto('/en/cart');
        expect(cartResponse?.status()).toBe(200);
        await expect(page.locator('[data-refund-policy]')).toBeVisible();
        await expect(page.locator('[data-refund-policy] a')).toHaveAttribute('href', /\/en\/p\/refund-policy$/);

        const detailResponse = await page.goto(`/en/account/bookings/${bookingPublicIds[1]}`);
        expect(detailResponse?.status()).toBe(200);
        const invoiceLink = page.locator('[data-booking-invoice]');
        await expect(invoiceLink).toBeVisible();
        await expect(invoiceLink).toHaveAttribute('href', new RegExp(`/en/account/bookings/${bookingPublicIds[1]}/invoice$`));
        await invoiceLink.click();
        await expect(page).toHaveURL(new RegExp(`/en/account/bookings/${bookingPublicIds[1]}/invoice$`));
        await expect(page.locator('body')).toContainText(bookingReference);
        await expect(page.locator('body')).toContainText('Unpaid');
        await expect(page.getByRole('button', { name: /print invoice/i })).toBeVisible();

        const arabicCartResponse = await page.goto('/ar/cart');
        expect(arabicCartResponse?.status()).toBe(200);
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.locator('[data-refund-policy] a')).toHaveAttribute('href', /\/ar\/p\/refund-policy$/);

        const arabicDetailResponse = await page.goto(`/ar/account/bookings/${bookingPublicIds[1]}`);
        expect(arabicDetailResponse?.status()).toBe(200);
        await page.locator('[data-booking-invoice]').click();
        await expect(page).toHaveURL(new RegExp(`/ar/account/bookings/${bookingPublicIds[1]}/invoice$`));
        await expect(page.locator('body')).toContainText(bookingReference);
        await expect(page.locator('body')).toContainText('غير مدفوع');
    } finally {
        await context.close();
    }
});
