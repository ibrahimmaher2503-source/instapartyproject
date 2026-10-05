import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const environment = {
    ...process.env,
    APP_ENV: 'testing',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.resolve('storage/e2e.sqlite'),
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync',
};
const vendorId = '01K8W0E2E0KKKKKKKKKKKKKKKK';
const slug = 'gallery-vendor-stable';

test('vendor gallery loads real owned images and keeps old/name-edited links working in EN/AR', async ({ page }) => {
    test.setTimeout(150_000);
    execFileSync('php', ['artisan', 'tinker',
        `--execute=config(['filesystems.disks.public.root'=>public_path('storage')]); $governorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->where('code','EG-CAI')->firstOrFail(); $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('governorate_id',$governorate->id)->firstOrFail(); $vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create(['public_id'=>'${vendorId}','slug'=>'${slug}','business_name'=>['en'=>'Gallery Vendor','ar'=>'مورد المعرض'],'primary_governorate_id'=>$governorate->id,'primary_city_id'=>$city->id]); $other=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create(['slug'=>'gallery-other-vendor','primary_governorate_id'=>$governorate->id,'primary_city_id'=>$city->id]); App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create(['slug'=>'gallery-empty-vendor','primary_governorate_id'=>$governorate->id,'primary_city_id'=>$city->id]); foreach ([$vendor,$vendor,$other] as $index=>$owner) { $bytes=file_get_contents(public_path('images/ChatGPT Image Aug 12, 2026, 06_31_27 AM ('.[5,1,4][$index].').png')); $fileName='e2e-gallery-'.Illuminate\\Support\\Str::ulid().'.png'; $media=$owner->media()->create(['collection_name'=>'portfolio','name'=>$fileName,'file_name'=>$fileName,'mime_type'=>'image/png','disk'=>'public','size'=>strlen($bytes),'manipulations'=>[],'custom_properties'=>[],'generated_conversions'=>[],'responsive_images'=>[]]); Illuminate\\Support\\Facades\\Storage::disk('public')->put($media->id.'/'.$fileName,$bytes); }`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });

    for (const locale of ['en', 'ar']) {
        await page.setViewportSize(locale === 'ar' ? { width: 390, height: 844 } : { width: 1440, height: 1000 });
        await page.goto(`/${locale}/vendors/${vendorId}?source=old-link`);
        await expect(page).toHaveURL(new RegExp(`/${locale}/vendors/${slug}\\?source=old-link$`));
        await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
        const gallery = page.locator('[data-vendor-gallery]');
        await expect(gallery.locator('h2')).toHaveText(locale === 'ar' ? 'معرض المورد' : 'Vendor gallery');
        expect(await gallery.evaluate(element => getComputedStyle(element).backgroundColor)).toBe('rgba(0, 0, 0, 0)');
        const image = gallery.locator('[data-service-gallery-main]');
        const thumbnails = gallery.locator('[data-service-gallery-image]');
        await expect(thumbnails).toHaveCount(2);
        await expect(image).toBeVisible();
        await expect.poll(() => image.evaluate((element: HTMLImageElement) => element.complete && element.naturalWidth > 0)).toBe(true);
        const first = (await thumbnails.nth(0).getAttribute('data-service-gallery-image'))!;
        const second = (await thumbnails.nth(1).getAttribute('data-service-gallery-image'))!;
        await gallery.locator('[data-gallery-step="1"]').click();
        await expect(image).toHaveAttribute('src', second);
        await gallery.locator('[data-gallery-step="1"]').click();
        await expect(image).toHaveAttribute('src', first);
        await gallery.locator('[data-gallery-step="-1"]').click();
        await expect(image).toHaveAttribute('src', second);
        await thumbnails.nth(0).click();
        await expect(image).toHaveAttribute('src', first);
        await gallery.locator('[data-gallery-step="1"]').focus();
        await page.keyboard.press('Enter');
        await expect(image).toHaveAttribute('src', second);
        await expect.poll(() => image.evaluate((element: HTMLImageElement) => element.complete && element.naturalWidth > 0)).toBe(true);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        await page.screenshot({ path: `tests/e2e/.results/vendor-gallery-${locale}.png`, fullPage: true });

        await page.goto(`/${locale}/vendors/gallery-other-vendor`);
        const foreign = await page.locator('[data-vendor-gallery] [data-service-gallery-main]').getAttribute('src');
        expect([first, second]).not.toContain(foreign);
        await expect(page.locator('[data-gallery-step]')).toHaveCount(0);
        await page.goto(`/${locale}/vendors/gallery-empty-vendor`);
        await expect(page.locator('[data-vendor-gallery]')).toHaveCount(0);

        await page.goto(`/${locale}/vendors?q=Gallery%20Vendor`);
        await page.locator(`.sf-vendors-page a[href$="/vendors/${slug}"]`).first().click();
        await expect(page).toHaveURL(new RegExp(`/${locale}/vendors/${slug}$`));
    }

    execFileSync('php', ['artisan', 'tinker',
        `--execute=$vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::query()->where('public_id','${vendorId}')->firstOrFail(); app(App\\Modules\\Identity\\Application\\Actions\\UpdateVendorProfileAction::class)->execute($vendor,['business_name'=>['en'=>'Renamed Gallery Vendor','ar'=>'مورد المعرض الجديد']]); if ($vendor->slug !== '${slug}') { throw new RuntimeException('Name edit changed public slug'); }`,
    ], { env: environment, stdio: 'inherit', timeout: 30_000 });
    await page.goto(`/ar/vendors/${vendorId}`);
    await expect(page).toHaveURL(new RegExp(`/ar/vendors/${slug}$`));
    await expect(page.locator('h1')).toHaveText('مورد المعرض الجديد');
});
