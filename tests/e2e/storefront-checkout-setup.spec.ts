import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test, type Page } from '@playwright/test';

const email = 'e2e-checkout-customer@example.test';
const password = 'CustomerUat123!';
const servicePublicId = '01K8W0E2E0DDDDDDDDDDDDDDDD';
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

test.use({ storageState: { cookies: [], origins: [] } });

function cairoDateTime(hoursFromNow: number): string {
    const parts = new Intl.DateTimeFormat('sv-SE', {
        timeZone: 'Africa/Cairo',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(new Date(Date.now() + hoursFromNow * 60 * 60 * 1000));

    return parts.replace(' ', 'T');
}

async function saveAndContinue(page: Page, locale: 'en' | 'ar', options: { service?: string; startHour?: number; optionalEnd?: boolean; browse?: boolean; checkRecipientPhone?: boolean } = {}): Promise<void> {
    const startHour = options.startHour ?? 48;
    await page.goto(options.browse ? `/${locale}/wizard` : `/${locale}/wizard?service=${options.service ?? servicePublicId}`);
    await page.locator('[data-planner-occasion-option]').first().click();
    const start = page.locator('[name="event_starts_at"]');
    const end = page.locator('[name="event_ends_at"]');
    if (options.optionalEnd) {
        await expect(start).toHaveAttribute('required', '');
        await expect(end).not.toHaveAttribute('required', '');
        await expect(end.locator('..').locator('..')).toContainText(locale === 'ar' ? 'وقت النهاية (اختياري)' : 'Ends at (optional)');
        await page.locator('[data-planner-panel="0"] [data-planner-next]').click();
        await expect(page.locator('[data-planner-error]')).toContainText(locale === 'ar' ? 'أضف وقت بداية المناسبة.' : 'Add the event start time.');
        await start.fill(cairoDateTime(startHour));
        await end.fill(cairoDateTime(startHour - 1));
        await page.locator('[data-planner-panel="0"] [data-planner-next]').click();
        await expect(page.locator('[data-planner-panel="0"]')).toBeVisible();
        await expect(page.locator('[data-planner-error]')).toBeVisible();
        await end.fill('');
        await page.screenshot({ path: `tests/e2e/.results/planner-optional-end-${locale}.png`, fullPage: true });
    } else {
        await start.fill(cairoDateTime(startHour));
        await end.fill(cairoDateTime(startHour + 3));
    }
    await page.locator('[data-planner-panel="0"] [data-planner-next]').click();

    const governorate = page.locator('[data-planner-governorate]');
    await expect(governorate.locator('option')).not.toHaveCount(1, { timeout: 15_000 });
    const cairoOption = governorate.locator('option').filter({ hasText: /Cairo|القاهرة/i }).first();
    await expect(cairoOption).toBeAttached();
    await governorate.selectOption((await cairoOption.getAttribute('value'))!);

    const city = page.locator('[data-planner-city-select]');
    const nasrCityOption = city.locator('option').filter({ hasText: /Nasr City|مدينة نصر/i }).first();
    await expect(nasrCityOption).toBeAttached({ timeout: 15_000 });
    await city.selectOption((await nasrCityOption.getAttribute('value'))!);
    await page.locator('[data-planner-panel="1"] [data-planner-next]').click();

    await page.locator('[name="guest_count"]').fill('25');
    const notes = `${locale === 'ar' ? 'تعليمات المناسبة' : 'Event instructions'}\n${'Use the side entrance. احتفظ بترتيب الطاولات.\n'.repeat(30)}Last instruction.`;
    await expect(page.locator('[name="celebrant_name"]')).toHaveCount(0);
    await page.locator('textarea[name="notes"]').fill(notes);
    await page.screenshot({ path: `tests/e2e/.results/planner-notes-${locale}.png`, fullPage: true });
    if (!options.browse) {
        await page.locator('[name="address[address_line]"]').fill('20 Browser Checkout Street');
        await page.locator('[name="address[recipient_name]"]').fill('Browser Customer');
        const phone = page.locator('[name="address[recipient_phone_e164]"]');
        if (options.checkRecipientPhone) {
            await expect(page.locator('#planner-recipient-phone-hint')).toBeVisible();
            for (const invalidPhone of ['01000000000', 'abc', '']) {
                await phone.fill(invalidPhone);
                await page.locator('[data-planner-panel="2"] button[type="submit"]').click();
                await expect(page.locator('[data-planner-recipient-phone-error]')).toBeVisible();
                await expect(page.locator('[data-planner-recipient-phone-error]')).toContainText('+201000000000');
                await expect(phone).toHaveAttribute('aria-invalid', 'true');
                await expect(phone).toHaveValue(invalidPhone);
                await expect(page.locator('textarea[name="notes"]')).toHaveValue(notes);
            }
            await phone.fill('abc');
            await phone.evaluate((input) => input.removeAttribute('pattern'));
            await page.locator('[data-planner-panel="2"] button[type="submit"]').click();
            await expect(page.locator('[data-planner-panel="2"]')).toBeVisible();
            await expect(city.locator('option')).not.toHaveCount(1, { timeout: 15_000 });
            await expect(page.locator('[data-planner-error]')).toBeVisible();
            await expect(page.locator('[data-planner-recipient-phone-error]')).toBeVisible();
            await expect(phone).toHaveValue('abc');
            await expect(page.locator('[name="address[address_line]"]')).toHaveValue('20 Browser Checkout Street');
            await expect(page.locator('textarea[name="notes"]')).toHaveValue(notes);

            const restoredGovernorate = await governorate.inputValue();
            const restoredCity = await city.inputValue();
            await page.locator('[data-planner-panel="2"] [data-planner-back]').click();
            const otherGovernorateValue = await governorate.locator('option').evaluateAll((items, current) => items.map((item) => (item as HTMLOptionElement).value).find((value) => value !== '' && value !== current), restoredGovernorate);
            expect(otherGovernorateValue).toBeTruthy();
            await governorate.selectOption(otherGovernorateValue!);
            await expect(page.locator('[data-planner-loading]')).toHaveAttribute('data-planner-status', 'idle', { timeout: 15_000 });
            await expect(city).toHaveValue('');
            await expect(page.locator('[data-planner-city]')).toHaveValue('');
            await expect(page.locator('[data-planner-booking-city]')).toHaveValue('');
            await page.locator('[data-planner-panel="1"] [data-planner-next]').click();
            await expect(page.locator('[data-planner-panel="1"]')).toBeVisible();
            await expect(page.locator('[data-planner-error]')).toBeVisible();
            await governorate.selectOption(restoredGovernorate);
            await expect(page.locator('[data-planner-booking-city]')).toHaveValue(restoredCity, { timeout: 15_000 });
            await page.locator('[data-planner-panel="1"] [data-planner-next]').click();
            await expect(page.locator('[data-planner-panel="2"]')).toBeVisible();
        }
        await phone.fill('+201001234567');
    }
    await page.locator('[data-planner-panel="2"] button[type="submit"]').click();

    if (options.browse) {
        await expect(page).toHaveURL(new RegExp(`/${locale}/search\\?`), { timeout: 20_000 });
        expect(new URL(page.url()).searchParams.get('event_ends_at') ?? '').toBe('');
        expect(new URL(page.url()).searchParams.has('notes')).toBe(false);
        await page.goto(`/${locale}/wizard?service=${options.service ?? servicePublicId}`);
        await expect(page.locator('textarea[name="notes"]')).toHaveValue(notes);
    } else {
        await expect(page).toHaveURL(new RegExp(`/${locale}/cart$`), { timeout: 20_000 });
        await expect(page.locator('body')).toContainText(/Browser Checkout|Checkout (sale|rental|digital) service|خدمة .*لاختبار الدفع/i);
        await expect(page.locator('[data-event-notes] p')).toHaveText(notes);
        await page.reload();
        await expect(page.locator('[data-event-notes] p')).toHaveText(notes);
    }
}

test('Save and continue works in English desktop and Arabic mobile checkout', async ({ page }) => {
    test.setTimeout(120_000);
    execFileSync('php', [
        'artisan',
        'tinker',
        `--execute=$customer=App\\Modules\\Identity\\Domain\\Models\\User::factory()->phoneVerified()->asCustomer()->create(['name'=>'Browser Customer','email'=>'${email}','password'=>'${password}','email_verified_at'=>now()]); $governorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->where('code','EG-CAI')->firstOrFail(); $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('governorate_id',$governorate->id)->firstOrFail(); $vendorUser=App\\Modules\\Identity\\Domain\\Models\\User::factory()->phoneVerified()->asVendor()->create(); $vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create(['user_id'=>$vendorUser->id,'business_name'=>['en'=>'Checkout Vendor','ar'=>'مورد اختبار الدفع'],'primary_governorate_id'=>$governorate->id,'primary_city_id'=>$city->id]); $service=App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->sale()->published()->create(['public_id'=>'${servicePublicId}','vendor_profile_id'=>$vendor->id,'name'=>['en'=>'Checkout sale service','ar'=>'خدمة بيع لاختبار الدفع']]); App\\Modules\\Catalog\\Domain\\Models\\ServiceSaleDetail::factory()->create(['service_id'=>$service->id,'stock_quantity'=>5]);`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });

    await page.goto('/en/auth/login');
    await page.locator('[name="login"]').fill(email);
    await page.locator('[name="password"]').fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/en$/);

    await page.setViewportSize({ width: 1440, height: 1000 });
    await saveAndContinue(page, 'en', { checkRecipientPhone: true });

    await page.setViewportSize({ width: 390, height: 844 });
    await saveAndContinue(page, 'ar', { checkRecipientPhone: true });
});

test('start-only events save from the real wizard for rental, sale and digital in both locales', async ({ page }) => {
    test.setTimeout(180_000);
    const ids = { rental: '01K8W0E2E0LLLLLLLLLLLLLLLL', sale: '01K8W0E2E0MMMMMMMMMMMMMMMM', digital: '01K8W0E2E0NNNNNNNNNNNNNNNN' };
    execFileSync('php', ['artisan', 'tinker',
        `--execute=$governorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->where('code','EG-CAI')->firstOrFail(); $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('governorate_id',$governorate->id)->firstOrFail(); $vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create(['primary_governorate_id'=>$governorate->id,'primary_city_id'=>$city->id]); foreach (['rental'=>'${ids.rental}','sale'=>'${ids.sale}','digital'=>'${ids.digital}'] as $type=>$publicId) { $service=App\\Modules\\Catalog\\Domain\\Models\\Service::factory()->$type()->published()->create(['public_id'=>$publicId,'vendor_profile_id'=>$vendor->id,'name'=>['en'=>'Checkout '.$type.' service','ar'=>'خدمة '.$type.' لاختبار الدفع']]); if ($type === 'rental') { App\\Modules\\Catalog\\Domain\\Models\\ServiceRentalDetail::factory()->create(['service_id'=>$service->id,'default_rental_duration_hours'=>6]); } elseif ($type === 'sale') { App\\Modules\\Catalog\\Domain\\Models\\ServiceSaleDetail::factory()->create(['service_id'=>$service->id,'stock_quantity'=>10]); } }`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });
    let caseIndex = 0;
    for (const locale of ['en', 'ar'] as const) {
        for (const type of ['rental', 'sale', 'digital'] as const) {
            const customerEmail = `e2e-optional-end-${locale}-${type}@example.test`;
            execFileSync('php', ['artisan', 'tinker',
                `--execute=App\\Modules\\Identity\\Domain\\Models\\User::factory()->phoneVerified()->asCustomer()->create(['name'=>'Optional end customer','email'=>'${customerEmail}','password'=>'${password}','email_verified_at'=>now()]);`,
            ], { env: environment, stdio: 'inherit', timeout: 30_000 });
            await page.context().clearCookies();
            await page.goto(`/${locale}/auth/login`);
            await page.locator('[name="login"]').fill(customerEmail);
            await page.locator('[name="password"]').fill(password);
            await page.locator('form button[type="submit"]').click();
            await expect(page).toHaveURL(new RegExp(`/${locale}$`));
            await page.setViewportSize(locale === 'ar' ? { width: 390, height: 844 } : { width: 1440, height: 1000 });
            await saveAndContinue(page, locale, { service: ids[type], optionalEnd: true, startHour: 60 + caseIndex++ * 12 });
            execFileSync('php', ['artisan', 'tinker',
                `--execute=$customer=App\\Modules\\Identity\\Domain\\Models\\User::query()->where('email','${customerEmail}')->firstOrFail(); $booking=App\\Modules\\Booking\\Domain\\Models\\Booking::query()->where('customer_id',$customer->id)->sole(); $item=$booking->items()->sole(); if ($booking->event_ends_at !== null) { throw new RuntimeException('Unknown event end was replaced'); } if ('${type}' === 'rental' && (!$item->effective_ends_at || $item->effective_starts_at->diffInHours($item->effective_ends_at) !== 6.0)) { throw new RuntimeException('Rental default window missing'); }`,
            ], { env: environment, stdio: 'inherit', timeout: 30_000 });
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        }
        await saveAndContinue(page, locale, { optionalEnd: true, browse: true });
    }
});
