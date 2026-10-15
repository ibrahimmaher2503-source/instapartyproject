import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test, type Browser, type BrowserContext, type Page } from '@playwright/test';

test.use({ trace: 'retain-on-failure' });

const runId = Date.now().toString();
const customerName = `QA Account Customer ${runId}`;
const customerEmail = `qa-account-customer-${runId}@example.test`;
const customerPassword = 'CustomerUat123!';
const vendorName = `QA Account Vendor ${runId}`;
const vendorSlug = `qa-account-vendor-${runId}`;
const categoryCode = `qa-account-${runId}`;
const serviceName = `QA Account Service ${runId}`;
const inquiryReply = `QA account inquiry reply ${runId}`;
const addressLabel = `QA Home ${runId}`;
const addressLine = `${runId} QA Account Street`;
const savedSearchLabel = `QA Saved Search ${runId}`;
const loyaltyReason = `QA loyalty history ${runId}`;

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

function runPhp(execute: string): string {
    return execFileSync('php', ['artisan', 'tinker', `--execute=${execute}`], {
        env: environment,
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
        timeout: 30_000,
    });
}

function seedFixtures(): void {
    runPhp(`$customer=App\\Modules\\Identity\\Domain\\Models\\User::factory()->phoneVerified()->asCustomer()->create([
            'name'=>'${customerName}', 'email'=>'${customerEmail}', 'password'=>'${customerPassword}',
        ]);
        $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('is_active',true)->firstOrFail();
        $vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create([
            'slug'=>'${vendorSlug}', 'business_name'=>['en'=>'${vendorName}','ar'=>'مورد اختبار ${runId}'],
        ]);
        $category=App\\Modules\\Catalog\\Domain\\Models\\Category::factory()->create([
            'code'=>'${categoryCode}', 'allowed_product_types'=>['digital'],
        ]);
        $service=App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->digital()->published()->create([
            'vendor_profile_id'=>$vendor->id, 'category_id'=>$category->id,
            'name'=>['en'=>'${serviceName}','ar'=>'خدمة اختبار ${runId}'],
        ]);
        $program=App\\Modules\\Loyalty\\Domain\\Models\\LoyaltyProgram::factory()->create([
            'vendor_profile_id'=>$vendor->id,
            'name'=>['en'=>'${vendorName} Rewards','ar'=>'مكافآت ${runId}'],
        ]);
        App\\Modules\\Loyalty\\Domain\\Models\\LoyaltyLedgerEntry::query()->create([
            'public_id'=>(string)Illuminate\\Support\\Str::ulid(),
            'user_id'=>$customer->id, 'vendor_profile_id'=>$vendor->id, 'loyalty_program_id'=>$program->id,
            'direction'=>'earn', 'points'=>47, 'balance_after'=>47,
            'reason'=>['en'=>'${loyaltyReason}','ar'=>'سجل نقاط اختبار ${runId}'],
        ]);
        $inquiry=App\\Modules\\Communication\\Domain\\Models\\VendorInquiry::query()->create([
            'public_id'=>(string)Illuminate\\Support\\Str::ulid(),
            'customer_id'=>$customer->id, 'vendor_profile_id'=>$vendor->id, 'service_id'=>$service->id,
            'customer_name'=>$customer->name, 'service_public_id'=>$service->public_id,
            'service_name_en'=>'${serviceName}', 'service_name_ar'=>'خدمة اختبار ${runId}',
            'vendor_name_en'=>'${vendorName}', 'vendor_name_ar'=>'مورد اختبار ${runId}',
        ]);
        $inquiry->messages()->create([
            'public_id'=>(string)Illuminate\\Support\\Str::ulid(), 'sender_user_id'=>$vendor->user_id,
            'author_role'=>'vendor', 'body'=>'${inquiryReply}',
        ]);
        echo 'QA_ACCOUNT_FIXTURES_READY';`);
}

function cleanupFixtures(): void {
    runPhp(`$customer=App\\Modules\\Identity\\Domain\\Models\\User::query()->where('email','${customerEmail}')->first();
        if ($customer !== null) {
            $inquiryIds=App\\Modules\\Communication\\Domain\\Models\\VendorInquiry::query()->where('customer_id',$customer->id)->pluck('id');
            App\\Modules\\Communication\\Domain\\Models\\VendorInquiryMessage::query()->whereIn('vendor_inquiry_id',$inquiryIds)->delete();
            App\\Modules\\Communication\\Domain\\Models\\VendorInquiry::query()->whereIn('id',$inquiryIds)->delete();
            App\\Modules\\Identity\\Domain\\Models\\CustomerAddress::query()->where('user_id',$customer->id)->forceDelete();
            App\\Modules\\Discovery\\Domain\\Models\\SavedSearch::query()->where('user_id',$customer->id)->delete();
            App\\Modules\\Loyalty\\Domain\\Models\\LoyaltyLedgerEntry::query()->where('user_id',$customer->id)->delete();
            $customer->tokens()->delete();
        }
        $vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::query()->where('slug','${vendorSlug}')->first();
        if ($vendor !== null) {
            App\\Modules\\Loyalty\\Domain\\Models\\LoyaltyProgram::query()->where('vendor_profile_id',$vendor->id)->delete();
            $service=App\\Modules\\Catalog\\Domain\\Models\\Service::query()->where('vendor_profile_id',$vendor->id)->where('name->en','${serviceName}')->first();
            $categoryId=$service?->category_id;
            $service?->forceDelete();
            if ($categoryId !== null) { App\\Modules\\Catalog\\Domain\\Models\\Category::query()->whereKey($categoryId)->forceDelete(); }
            $vendorUser=$vendor->user;
            $vendor->forceDelete();
            $vendorUser?->forceDelete();
        }
        $customer?->forceDelete();`);
}

async function loginCustomer(page: Page): Promise<void> {
    await page.goto('/en/auth/login');
    await page.locator('input[name="login"]').fill(customerEmail);
    await page.locator('input[name="password"]').fill(customerPassword);
    await page.locator('form[data-auth-form] button[type="submit"]').click();
    await expect(page).toHaveURL(/\/en\/?$/);
}

async function freshCustomerPage(browser: Browser): Promise<{ context: BrowserContext; page: Page }> {
    const context = await browser.newContext({
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8139',
        locale: 'en-GB',
        timezoneId: 'Africa/Cairo',
        storageState: { cookies: [], origins: [] },
    });

    return { context, page: await context.newPage() };
}

test.beforeAll(() => seedFixtures());
test.afterAll(() => cleanupFixtures());

test('manages customer addresses, saved searches, loyalty history, and real inquiry conversations', async ({ browser }) => {
    test.setTimeout(120_000);
    const { context, page } = await freshCustomerPage(browser);

    try {
        await loginCustomer(page);

        const addressesResponse = await page.goto('/en/account/addresses');
        expect(addressesResponse?.status()).toBe(200);
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
        await expect(page.getByRole('link', { name: 'Saved searches' })).toBeVisible();
        const city = page.locator('select[name="city_id"]');
        await city.selectOption({ index: 1 });
        await page.locator('input[name="label"]').fill(addressLabel);
        await page.locator('input[name="address_line"]').fill(addressLine);
        await page.locator('input[name="recipient_name"]').fill(customerName);
        await page.locator('input[name="recipient_phone_e164"]').fill('+201001234567');
        await page.locator('input[name="is_default"]').check();
        await page.getByRole('button', { name: /add address/i }).click();
        await expect(page.getByText(addressLine)).toBeVisible();
        await expect(page.getByText(/default address/i)).toBeVisible();

        const updatedAddress = `${addressLine} updated`;
        const edit = page.locator('details').filter({ has: page.locator(`input[value="${addressLine}"]`) });
        await edit.locator('summary').click();
        await edit.locator('input[name="address_line"]').fill(updatedAddress);
        await edit.getByRole('button', { name: /save/i }).click();
        await expect(page.getByText(updatedAddress)).toBeVisible();

        const searchResponse = await page.goto('/en/search?q=chairs');
        expect(searchResponse?.status()).toBe(200);
        const saveSearchForm = page.locator('form[action*="/account/saved-searches"]');
        await expect(saveSearchForm).toBeVisible();
        await saveSearchForm.locator('input[name="label"]').fill(savedSearchLabel);
        await saveSearchForm.getByRole('button').click();
        await expect(page).toHaveURL(/\/en\/account\/saved-searches\/?$/);
        await expect(page.getByRole('heading', { name: savedSearchLabel })).toBeVisible();
        await page.getByRole('link', { name: /run this search/i }).click();
        await expect(page).toHaveURL(/\/en\/search\?.*q=chairs/);
        await expect(page.locator('input[name="q"]')).toHaveValue('chairs');
        await page.goto('/en/account/saved-searches');
        await page.locator('article').filter({ hasText: savedSearchLabel }).getByRole('button').click();
        await expect(page.getByText(/no saved searches/i)).toBeVisible();

        const loyaltyResponse = await page.goto('/en/account/loyalty');
        expect(loyaltyResponse?.status()).toBe(200);
        await expect(page.locator('body')).toContainText('47');
        await expect(page.getByText(loyaltyReason)).toBeVisible();

        const chatsResponse = await page.goto('/en/account/chats');
        expect(chatsResponse?.status()).toBe(200);
        const inquiryLink = page.getByRole('link', { name: new RegExp(serviceName) });
        await expect(inquiryLink).toBeVisible();
        await inquiryLink.click();
        await expect(page).toHaveURL(/\/en\/account\/inquiries\/[0-9A-HJKMNP-TV-Z]{26}$/);
        await expect(page.getByText(inquiryReply)).toBeVisible();

        const arabicAddressesResponse = await page.goto('/ar/account/addresses');
        expect(arabicAddressesResponse?.status()).toBe(200);
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.getByRole('link', { name: 'عمليات البحث المحفوظة' })).toBeVisible();

        for (const route of ['/ar/account/saved-searches', '/ar/account/loyalty', '/ar/account/chats']) {
            const response = await page.goto(route);
            expect(response?.status()).toBe(200);
            await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        }
        await expect(page.getByText(serviceName)).toBeVisible();
    } finally {
        await context.close();
    }
});
