import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const email = 'e2e-booking-vendor@example.test';
const password = 'VendorUat123!';
const bookingVendorPublicId = '01K8W0E2E0CCCCCCCCCCCCCCCC';
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

test('an approved vendor opens an owned booking from the generated row link', async ({ page }) => {
    test.setTimeout(90_000);
    execFileSync('php', [
        'artisan',
        'tinker',
        `--execute=$user=App\\Modules\\Identity\\Domain\\Models\\User::factory()->phoneVerified()->asVendor()->create(['name'=>'E2E Booking Vendor','email'=>'${email}','password'=>'${password}','email_verified_at'=>now()]); $governorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->where('code','EG-CAI')->firstOrFail(); $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('governorate_id',$governorate->id)->firstOrFail(); $vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create(['user_id'=>$user->id,'business_name'=>['en'=>'E2E Booking Vendor','ar'=>'مورد حجز للاختبار'],'primary_governorate_id'=>$governorate->id,'primary_city_id'=>$city->id]); $booking=App\\Modules\\Booking\\Domain\\Models\\Booking::factory()->create(['reference_no'=>'BK-E2E-DETAIL']); App\\Modules\\Booking\\Domain\\Models\\BookingVendor::factory()->create(['public_id'=>'${bookingVendorPublicId}','booking_id'=>$booking->id,'vendor_profile_id'=>$vendor->id]);`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });

    execFileSync('php', ['artisan', 'tinker',
        `--execute=$booking=App\\Modules\\Booking\\Domain\\Models\\Booking::query()->where('reference_no','BK-E2E-DETAIL')->firstOrFail(); app(App\\Modules\\Booking\\Application\\Actions\\AppendCustomerBookingNoteAction::class)->execute($booking,$booking->customer_id,implode(chr(10),['Vendor event instructions','تعليمات خاصة بالمناسبة','Final instruction.']),'ar');`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });

    await page.goto('/vendor-portal/login');
    await page.locator('[wire\\:model="data.email"]').fill(email);
    await page.locator('[wire\\:model="data.password"]').fill(password);
    await page.getByRole('button', { name: /sign in|تسجيل الدخول/i }).click();
    await expect(page).toHaveURL(/\/vendor-portal\/?$/);

    const listResponse = await page.goto('/vendor-portal/vendor-bookings-page');
    expect(listResponse?.status()).toBe(200);
    await expect(page.getByText('BK-E2E-DETAIL')).toBeVisible();

    const detailLink = page.locator(`a[href*="bookingVendor=${bookingVendorPublicId}"]`).first();
    const detailUrl = await detailLink.getAttribute('href');
    expect(detailUrl).toBeTruthy();

    const detailResponse = await page.goto(detailUrl!);
    expect(detailResponse?.status()).toBe(200);
    await expect(page).toHaveURL(new RegExp(`bookingVendor=${bookingVendorPublicId}$`));
    await expect(page.getByText('BK-E2E-DETAIL')).toBeVisible();
    await expect(page.getByText('تعليمات خاصة بالمناسبة')).toBeVisible();
    for (const locale of ['en', 'ar']) {
        await page.setExtraHTTPHeaders({ 'Accept-Language': locale });
        await page.setViewportSize(locale === 'ar' ? { width: 390, height: 844 } : { width: 1440, height: 1000 });
        await page.goto(`${detailUrl}&locale=${locale}&lang=${locale}`);
        await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
        const instructions = page.getByText('تعليمات خاصة بالمناسبة');
        await expect(instructions).toBeVisible();
        await expect(instructions).toContainText('Vendor event instructions');
        await expect(instructions).toContainText('Final instruction.');
        expect(await instructions.evaluate(element => getComputedStyle(element).whiteSpace)).toBe('pre-wrap');
        await page.screenshot({ path: `tests/e2e/.results/vendor-event-notes-${locale}.png`, fullPage: true });
    }
});
