import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const runId = Date.now().toString();
const customerEmail = `qa-discovery-customer-${runId}@example.test`;
const vendorEmail = `qa-discovery-vendor-${runId}@example.test`;
const customerName = `QA Discovery Customer ${runId}`;
const vendorName = `QA Discovery Vendor ${runId}`;
const password = 'CustomerUat123!';
const inquiryText = `Please confirm service fit before booking ${runId}.`;
const vendorReply = `This service can support the event requirements ${runId}.`;
const serviceNames = {
    rental: { en: `QA Discovery Rental ${runId}`, ar: `خدمة إيجار اختبار ${runId}` },
    sale: { en: `QA Discovery Sale ${runId}`, ar: `خدمة بيع اختبار ${runId}` },
    digital: { en: `QA Discovery Digital ${runId}`, ar: `خدمة رقمية اختبار ${runId}` },
};
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
    CATALOG_DIGITAL_SERVICES_ENABLED: 'true',
};

type ProductType = keyof typeof serviceNames;
let servicePublicIds: Record<ProductType, string>;
let vendorPublicId = '';

function runPhp(execute: string): string {
    const wrapped = `try { ${execute}\necho "\\nQA_DISCOVERY_COMMAND_OK"; } catch (\\Throwable $error) { echo "\\nQA_DISCOVERY_COMMAND_ERROR:".$error::class.":".$error->getMessage(); }`;
    const output = execFileSync('php', ['artisan', 'tinker', `--execute=${wrapped}`], {
        env: environment,
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
        timeout: 30_000,
    });
    if (!output.includes('QA_DISCOVERY_COMMAND_OK')) {
        const failure = output.match(/QA_DISCOVERY_COMMAND_ERROR:([^\r\n]+)/)?.[1] ?? output.trim();
        throw new Error(`Customer discovery fixture command failed: ${failure}`);
    }

    return output;
}

function readFixtureIds(): void {
    const output = runPhp(`DB::beginTransaction();
        try {
        $customer=App\\Modules\\Identity\\Domain\\Models\\User::factory()->phoneVerified()->asCustomer()->create([
            'name'=>'${customerName}', 'email'=>'${customerEmail}', 'password'=>'${password}',
        ]);
        $customer->forceFill(['email_verified_at'=>now()])->save();
        $vendorUser=App\\Modules\\Identity\\Domain\\Models\\User::factory()->phoneVerified()->asVendor()->create([
            'name'=>'${vendorName}', 'email'=>'${vendorEmail}', 'password'=>'${password}',
        ]);
        $vendorUser->forceFill(['email_verified_at'=>now()])->save();
        $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('is_active',true)->firstOrFail();
        $vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create([
            'user_id'=>$vendorUser->id,
            'primary_governorate_id'=>$city->governorate_id,
            'primary_city_id'=>$city->id,
            'business_name'=>['en'=>'${vendorName}','ar'=>'مورد اختبار ${runId}'],
        ]);
        foreach (App\\Modules\\Catalog\\Domain\\Enums\\ProductType::cases() as $type) {
            App\\Modules\\Identity\\Domain\\Models\\VendorApprovedProductType::query()->create([
                'vendor_profile_id'=>$vendor->id, 'product_type'=>$type, 'approved_at'=>now(),
            ]);
        }
        $occasion=App\\Modules\\Catalog\\Domain\\Models\\Occasion::query()->where('is_active',true)->firstOrFail();
        $services=[];
        foreach (['rental','sale','digital'] as $type) {
            $category=App\\Modules\\Catalog\\Domain\\Models\\Category::factory()->create([
                'code'=>'qa-discovery-${runId}-'.$type, 'allowed_product_types'=>[$type],
            ]);
            $category->occasions()->syncWithoutDetaching([$occasion->id]);
            $factory=match ($type) {
                'rental'=>App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->rental(),
                'sale'=>App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->sale(),
                'digital'=>App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->digital(),
            };
            $service=$factory->published()->create([
                'vendor_profile_id'=>$vendor->id, 'category_id'=>$category->id,
                'name'=>['en'=>'QA Discovery '.ucfirst($type).' ${runId}','ar'=>'خدمة '.($type==='rental'?'إيجار':($type==='sale'?'بيع':'رقمية')).' اختبار ${runId}'],
                'base_price_minor'=>13500, 'base_price_currency'=>'EGP',
            ]);
            match ($type) {
                'rental'=>App\\Modules\\Catalog\\Domain\\Models\\ServiceRentalDetail::factory()->create([
                    'service_id'=>$service->id, 'default_rental_duration_hours'=>6,
                ]),
                'sale'=>App\\Modules\\Catalog\\Domain\\Models\\ServiceSaleDetail::factory()->create([
                    'service_id'=>$service->id, 'stock_quantity'=>10,
                ]),
                'digital'=>App\\Modules\\Catalog\\Domain\\Models\\ServiceDigitalDetail::factory()->create([
                    'service_id'=>$service->id, 'delivery_method'=>'email',
                ]),
            };
            $services[$type]=$service->public_id;
        }
        DB::commit();
        echo 'QA_DISCOVERY_FIXTURES:'.json_encode(['services'=>$services,'vendor'=>$vendor->public_id]);
        } catch (\\Throwable $error) {
            DB::rollBack();
            throw $error;
        }`);
    const fixture = output.match(/QA_DISCOVERY_FIXTURES:(\{[^\r\n]+\})/)?.[1];
    if (!fixture) throw new Error('The customer discovery browser fixtures were not created.');
    const parsed = JSON.parse(fixture) as { services: Record<ProductType, string>; vendor: string };
    servicePublicIds = parsed.services;
    vendorPublicId = parsed.vendor;
}

function cleanupFixtures(): void {
    if (!vendorPublicId || !servicePublicIds) return;
    const serviceIds = JSON.stringify(Object.values(servicePublicIds));

    runPhp(`$customer=App\\Modules\\Identity\\Domain\\Models\\User::query()->where('email','${customerEmail}')->first();
        $vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::query()->where('public_id','${vendorPublicId}')->first();
        $inquiryIds=App\\Modules\\Communication\\Domain\\Models\\VendorInquiry::query()
            ->where('customer_id',$customer?->id)->where('vendor_profile_id',$vendor?->id)->pluck('id');
        App\\Modules\\Communication\\Domain\\Models\\VendorInquiryMessage::query()->whereIn('vendor_inquiry_id',$inquiryIds)->delete();
        App\\Modules\\Communication\\Domain\\Models\\VendorInquiry::query()->whereIn('id',$inquiryIds)->delete();
        App\\Modules\\Catalog\\Domain\\Models\\Service::query()->whereIn('public_id',${serviceIds})->forceDelete();
        foreach (['rental','sale','digital'] as $type) {
            $service=App\\Modules\\Catalog\\Domain\\Models\\Service::query()
                ->where('name->en','QA Discovery '.ucfirst($type).' ${runId}')->first();
            $category=App\\Modules\\Catalog\\Domain\\Models\\Category::query()->where('code','qa-discovery-${runId}-'.$type)->first();
            $service?->forceDelete();
            $category?->occasions()->detach();
            $category?->delete();
        }
        if ($vendor !== null) {
            App\\Modules\\Identity\\Domain\\Models\\VendorApprovedProductType::query()->where('vendor_profile_id',$vendor->id)->delete();
            $vendorUser=$vendor->user;
            $vendor->forceDelete();
            $vendorUser?->forceDelete();
        }
        $customer?->forceDelete();`);
}

async function loginCustomer(page: import('@playwright/test').Page): Promise<void> {
    await page.goto('/en/auth/login');
    await page.locator('input[name="login"]').fill(customerEmail);
    await page.locator('input[name="password"]').fill(password);
    await page.locator('form[data-auth-form] button[type="submit"]').click();
    await expect(page).toHaveURL(/\/en\/?$/);
}

function cairoDateTime(hoursAhead: number): string {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Africa/Cairo', year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(new Date(Date.now() + hoursAhead * 60 * 60 * 1000));
    const value = (type: Intl.DateTimeFormatPartTypes): string => parts.find((part) => part.type === type)?.value ?? '';
    return `${value('year')}-${value('month')}-${value('day')}T${value('hour')}:${value('minute')}`;
}

function addHours(value: string, hours: number): string {
    const date = new Date(`${value}:00Z`);
    date.setUTCMinutes(date.getUTCMinutes() + hours * 60);
    return date.toISOString().slice(0, 16);
}

test.beforeAll(() => readFixtureIds());
test.afterAll(() => cleanupFixtures());

test('checks estimates and availability for rental, sale, and digital services in English and Arabic', async ({ browser }) => {
    test.setTimeout(180_000);
    const context = await browser.newContext({
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8139',
        locale: 'en-GB',
        timezoneId: 'Africa/Cairo',
        storageState: { cookies: [], origins: [] },
    });
    const page = await context.newPage();

    try {
        await loginCustomer(page);

        for (const locale of ['en', 'ar'] as const) {
            for (const type of ['rental', 'sale', 'digital'] as const) {
                const query = type === 'rental'
                    ? `?quantity=1&event_starts_at=${encodeURIComponent(cairoDateTime(72))}&rental_duration_hours=6&timezone=Africa%2FCairo`
                    : '?quantity=1';
                const response = await page.goto(`/${locale}/services/${servicePublicIds[type]}${query}`);
                expect(response?.status()).toBe(200);
                await expect(page.locator('html')).toHaveAttribute('lang', locale);
                await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');

                const estimate = page.locator('[data-service-estimate-total]');
                const originalEstimate = await estimate.innerText();
                if (type !== 'rental') {
                    const quantity = page.locator('[data-service-estimate-quantity]');
                    await quantity.fill('3');
                    const bookingLink = page.locator('[data-service-wizard-link]');
                    await expect(bookingLink).toHaveAttribute('aria-disabled', 'true');
                    await expect(page.locator('[data-service-estimate-refresh-status]')).toContainText(
                        await page.locator('[data-service-estimate]').getAttribute('data-estimate-refresh-message') ?? '',
                    );
                    const currentUrl = page.url();
                    await bookingLink.click();
                    expect(page.url()).toBe(currentUrl);

                    await page.locator('[data-service-estimate] button[type="submit"]').click();
                    await expect(page).toHaveURL(/quantity=3/);
                    await expect(page.locator('[data-service-estimate-total]')).not.toHaveText(originalEstimate);
                    await expect(page.locator('[data-service-wizard-link]')).toHaveAttribute('aria-disabled', 'false');
                }

                const form = page.locator('[data-service-estimate]');
                const expectedAvailability = await form.getAttribute(`data-availability-available-${type}`);
                await page.locator('[data-service-check-availability]').click();
                await expect(page.locator('[data-service-availability-status]')).toHaveText(expectedAvailability ?? '');
            }
        }

        const start = cairoDateTime(96);
        const rental = servicePublicIds.rental;
        await page.goto(`/en/services/${rental}?quantity=1&event_starts_at=${encodeURIComponent(cairoDateTime(72))}&rental_duration_hours=36&timezone=Africa%2FCairo`);
        await page.locator('[data-service-wizard-link]').click();
        const wizardStart = page.locator('[data-planner-form] input[name="event_starts_at"]');
        const wizardEnd = page.locator('[data-planner-form] input[name="event_ends_at"]');
        await expect(wizardEnd).toHaveValue(addHours(cairoDateTime(72), 36));
        await expect(page.locator('[data-planner-form] input[name="quantity"]')).toHaveValue('1');
        await wizardStart.fill(start);
        await expect(wizardEnd).toHaveValue(addHours(start, 36));
    } finally {
        await context.close();
    }
});

test('lets a customer ask the vendor before booking and exchange a reply in the vendor inbox', async ({ browser }) => {
    test.setTimeout(120_000);
    const customerContext = await browser.newContext({
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8139',
        locale: 'en-GB', timezoneId: 'Africa/Cairo', storageState: { cookies: [], origins: [] },
    });
    const vendorContext = await browser.newContext({
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8139',
        locale: 'en-GB', timezoneId: 'Africa/Cairo', storageState: { cookies: [], origins: [] },
    });
    const customerPage = await customerContext.newPage();
    const vendorPage = await vendorContext.newPage();

    try {
        await loginCustomer(customerPage);
        await customerPage.goto(`/en/services/${servicePublicIds.rental}`);
        await customerPage.getByRole('link', { name: /ask the vendor/i }).click();
        await expect(customerPage).toHaveURL(/\/en\/services\/[^/]+\/inquiries\/create$/);
        await customerPage.locator('#vendor-inquiry-body').fill(inquiryText);
        await customerPage.getByRole('button', { name: 'Send question' }).click();
        await expect(customerPage).toHaveURL(/\/en\/account\/inquiries\/[0-9A-HJKMNP-TV-Z]{26}$/);
        const inquiryUrl = customerPage.url();
        await expect(customerPage.locator('body')).toContainText(inquiryText);
        await expect(customerPage.locator('body')).toContainText(serviceNames.rental.en);

        await vendorPage.goto('/vendor-portal/login');
        await vendorPage.locator('[wire\\:model="data.email"]').fill(vendorEmail);
        await vendorPage.locator('[wire\\:model="data.password"]').fill(password);
        await vendorPage.getByRole('button', { name: /sign in/i }).click();
        await expect(vendorPage).toHaveURL(/\/vendor-portal\/?$/);
        await vendorPage.goto('/vendor-portal/vendor-inquiries');
        const inquiryRow = vendorPage.getByText(serviceNames.rental.en, { exact: true });
        await expect(inquiryRow).toBeVisible();
        await inquiryRow.click();
        await expect(vendorPage.locator('body')).toContainText(inquiryText);
        await vendorPage.locator('textarea[wire\\:model="body"]').fill(vendorReply);
        await vendorPage.getByRole('button', { name: 'Send reply' }).click();
        await expect(vendorPage.locator('body')).toContainText(vendorReply);

        await customerPage.goto(inquiryUrl);
        await expect(customerPage.locator('body')).toContainText(vendorReply);
    } finally {
        await customerContext.close();
        await vendorContext.close();
    }
});
