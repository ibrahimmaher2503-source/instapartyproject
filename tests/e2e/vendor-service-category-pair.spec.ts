import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test, type Page } from '@playwright/test';

const environment = {
    ...process.env, APP_ENV: 'testing', APP_DEBUG: 'true', DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.resolve('storage/e2e.sqlite'), CACHE_STORE: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'sync',
};
const runPhp = (code: string) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], {
    env: environment, encoding: 'utf8', timeout: 30_000,
}).trim();
const categoryField = (page: Page, field: string) => page.locator('[data-field-wrapper]')
    .filter({ has: page.locator(`label[for="data.${field}"]`) });

async function selectCategory(page: Page, field: string, label: string, id: number) {
    const wrapper = categoryField(page, field);
    const choices = wrapper.locator('.choices[role="combobox"]');
    await choices.click();
    await choices.locator('.choices__list--dropdown [role="option"]').filter({ hasText: label }).click();
    await expect(wrapper.locator(`select[id="data.${field}"]`)).toHaveValue(String(id));
    await page.waitForLoadState('networkidle');
}

test.use({ storageState: { cookies: [], origins: [] }, actionTimeout: 20_000 });

test('vendors roundtrip categories prices and rental dimensions in EN desktop and AR mobile', async ({ page }) => {
    test.setTimeout(240_000);
            const fixture = JSON.parse(runPhp("$user=App\\Modules\\Identity\\Domain\\Models\\User::factory()->phoneVerified()->asVendor()->create(['email'=>'e2e-category-pair@example.test','password'=>'VendorUat123!','email_verified_at'=>now()]); $governorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->where('code','EG-CAI')->firstOrFail(); $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('governorate_id',$governorate->id)->firstOrFail(); $profile=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create(['user_id'=>$user->id,'primary_governorate_id'=>$governorate->id,'primary_city_id'=>$city->id]); foreach(App\\Modules\\Catalog\\Domain\\Enums\\ProductType::cases() as $type) { App\\Modules\\Identity\\Domain\\Models\\VendorApprovedProductType::factory()->forType($type)->create(['vendor_profile_id'=>$profile->id]); $user->givePermissionTo(Spatie\\Permission\\Models\\Permission::findOrCreate('service.update.'.$type->value.'.own','web')); } $ids=[]; foreach(['a','b'] as $key) { $root=App\\Modules\\Catalog\\Domain\\Models\\Category::factory()->create(['parent_id'=>null,'name'=>['en'=>'Pair Main '.strtoupper($key),'ar'=>'رئيسي تجريبي '.strtoupper($key)],'allowed_product_types'=>['rental','sale','digital']]); $child=App\\Modules\\Catalog\\Domain\\Models\\Category::factory()->create(['parent_id'=>$root->id,'name'=>['en'=>'Pair Child '.strtoupper($key),'ar'=>'فرعي تجريبي '.strtoupper($key)],'allowed_product_types'=>['rental','sale','digital']]); $ids[$key]=['main'=>$root->id,'child'=>$child->id]; if($key==='a') { foreach([['rental','capacity','Capacity','السعة','number'],['rental','performer_name','Performer','مقدم الخدمة','text'],['sale','flavor','Flavor','النكهة','select'],['digital','editable','Editable','قابل للتعديل','boolean']] as [$type,$field,$en,$ar,$fieldType]) { App\\Modules\\Catalog\\Domain\\Models\\CategoryFieldSchema::factory()->create(['category_id'=>$child->id,'product_type'=>$type,'field_key'=>$field,'field_label'=>['en'=>$en,'ar'=>$ar],'field_type'=>$fieldType,'options'=>$fieldType==='select'?[['value'=>'vanilla','label'=>['en'=>'Vanilla','ar'=>'فانيليا']]]:null,'is_required'=>true,'is_filterable'=>true,'validation_rules'=>['required']]); } } } echo json_encode($ids);"));
    await page.goto('/vendor-portal/login');
    await page.locator('[wire\\:model="data.email"]').fill('e2e-category-pair@example.test');
    await page.locator('[wire\\:model="data.password"]').fill('VendorUat123!');
    await page.getByRole('button', { name: /sign in|تسجيل الدخول/i }).click();
    await expect(page).toHaveURL(/\/vendor-portal\/?$/);

    for (const locale of ['en', 'ar']) {
        await page.setViewportSize(locale === 'ar' ? { width: 390, height: 844 } : { width: 1440, height: 1000 });
        if (locale === 'ar') {
            await page.goto('/vendor-portal');
            await page.locator('.language-switch-trigger').click();
            await page.locator('button[wire\\:click*="changeLocale"]').filter({ hasText: 'العربية' }).click();
            await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
            await page.waitForLoadState('networkidle');
        }
        for (const type of ['rental', 'sale', 'digital']) {
            const base = `/vendor-portal/vendor-${type}-services`;
            const query = `?locale=${locale}&lang=${locale}`;
            const response = await page.goto(base + query);
            expect(response?.status()).toBe(200);
            await page.locator(`a[href*="${base}/create"]`).click();
            await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
            const child = categoryField(page, 'category_id').locator('select[id="data.category_id"]');
            await expect(child).toBeDisabled();
            const mainLabel = (key: string) => locale === 'ar' ? `رئيسي تجريبي ${key}` : `Pair Main ${key}`;
            const childLabel = (key: string) => locale === 'ar' ? `فرعي تجريبي ${key}` : `Pair Child ${key}`;
            await selectCategory(page, 'main_category_id', mainLabel('A'), fixture.a.main);
            await expect(child).toBeEnabled();
            await selectCategory(page, 'category_id', childLabel('A'), fixture.a.child);
            const dynamicField = type === 'rental' ? 'capacity' : type === 'sale' ? 'flavor' : 'editable';
            const dynamicWrapper = categoryField(page, `category_field_values.${dynamicField}`);
            await expect(dynamicWrapper).toBeVisible();
            if (type === 'rental') await page.locator('[id="data.category_field_values.capacity"]').fill('50');
            if (type === 'rental') await page.locator('[id="data.category_field_values.performer_name"]').fill('Party Performer');
            if (type === 'sale') await page.locator('[id="data.category_field_values.flavor"]').selectOption('vanilla');
            if (type === 'digital') await page.locator('[id="data.category_field_values.editable"]').check();
            await selectCategory(page, 'main_category_id', mainLabel('B'), fixture.b.main);
            await expect(child).toHaveValue('');
            await expect(dynamicWrapper).toHaveCount(0);
            const childChoices = categoryField(page, 'category_id').locator('.choices[role="combobox"]');
            await childChoices.click();
            await expect(childChoices.locator('.choices__list--dropdown [role="option"]').filter({ hasText: childLabel('A') })).toHaveCount(0);
            await childChoices.locator('.choices__list--dropdown [role="option"]').filter({ hasText: childLabel('B') }).click();
            await expect(child).toHaveValue(String(fixture.b.child));
            await page.waitForLoadState('networkidle');
            await expect(categoryField(page, 'category_id')).toContainText(locale === 'ar'
                ? 'هذه الفئة غير مرتبطة بأي مناسبة بعد.' : 'This category is not linked to any occasion yet.');
            const name = `Pair service ${type} ${locale}`;
            await page.locator('[id="data.name.en"]').fill(name);
            await page.locator('form').getByRole('tab', { name: /Arabic|العربية/i }).click();
            await page.locator('[id="data.name.ar"]').fill(`خدمة ${type} ${locale}`);
            await page.locator('[id="data.base_price_minor"]').fill('250');
            const priceField = categoryField(page, 'base_price_minor');
            await expect(priceField).toContainText('EGP');
            await expect(priceField).not.toContainText(locale === 'ar' ? 'قرش' : 'Piastres');
            if (type === 'rental') {
                await page.locator('[id="data.rentalDetail.default_rental_duration_hours"]').fill('4');
                for (const field of ['teardown_time_minutes', 'security_deposit_minor', 'minimum_space_sqm']) {
                    await expect(page.locator(`[id="data.rentalDetail.${field}"]`)).toHaveCount(0);
                }
                for (const [field, value] of Object.entries({ length_m: '4.50', width_m: '3.25', height_m: '2.00' })) {
                    await page.locator(`[id="data.rentalDetail.${field}"]`).fill(value);
                    await expect(categoryField(page, `rentalDetail.${field}`)).toContainText(locale === 'ar' ? 'متر' : 'm');
                }
            } else {
                await expect(page.locator('[id="data.rentalDetail.length_m"]')).toHaveCount(0);
            }
            if (type === 'digital') await page.locator('select[id="data.digitalDetail.delivery_method"]').selectOption('email');
            await page.getByRole('heading', { level: 1 }).scrollIntoViewIfNeeded();
            await page.screenshot({ path: `tests/e2e/.results/vendor-category-pair-${type}-${locale}.png`, fullPage: true });
            await page.getByRole('button', { name: /^(Create|إضافة)$/ }).click();
            await expect(page).toHaveURL(new RegExp(`${base}(\\?.*)?$`), { timeout: 15_000 });
            const service = JSON.parse(runPhp(`$service=App\\Modules\\Catalog\\Domain\\Models\\Service::query()->where('name->en','${name}')->sole(); echo json_encode(['public_id'=>$service->public_id,'category_id'=>$service->category_id,'minor'=>$service->base_price_minor,'currency'=>$service->base_price_currency,'formatted'=>$service->base_price->formatTo('${locale}'==='ar'?'ar_EG':'en_GB')]);`));
            expect(service.category_id).toBe(fixture.b.child);
            expect(service.minor).toBe(25000);
            expect(service.currency).toBe('EGP');
            if (type === 'rental') {
                const dimensions = JSON.parse(runPhp(`$service=App\\Modules\\Catalog\\Domain\\Models\\Service::query()->where('public_id','${service.public_id}')->sole(); echo json_encode($service->rentalDetail->only(['length_m','width_m','height_m'])); $service->rentalDetail->update(['teardown_time_minutes'=>75,'security_deposit_minor'=>45000,'minimum_space_sqm'=>28]);`));
                expect(dimensions).toEqual({ length_m: '4.50', width_m: '3.25', height_m: '2.00' });
            }
            const rowName = locale === 'ar' ? `خدمة ${type} ${locale}` : name;
            await expect(page.getByRole('row').filter({ hasText: rowName })).toContainText(service.formatted);
            await page.goto(`${base}/${service.public_id}/edit${query}`);
            await expect(page.locator('[id="data.base_price_minor"]')).toHaveValue('250.00');
            await expect(categoryField(page, 'main_category_id').locator('select')).toHaveValue(String(fixture.b.main));
            await expect(child).toHaveValue(String(fixture.b.child));
            await selectCategory(page, 'main_category_id', mainLabel('A'), fixture.a.main);
            await expect(child).toHaveValue('');
            await selectCategory(page, 'category_id', childLabel('A'), fixture.a.child);
            const editDynamicField = type === 'rental' ? 'capacity' : type === 'sale' ? 'flavor' : 'editable';
            const editDynamicWrapper = categoryField(page, `category_field_values.${editDynamicField}`);
            await expect(editDynamicWrapper).toBeVisible();
            if (type === 'rental') await page.locator('[id="data.category_field_values.capacity"]').fill('75');
            if (type === 'rental') await page.locator('[id="data.category_field_values.performer_name"]').fill('Updated Performer');
            if (type === 'sale') await page.locator('[id="data.category_field_values.flavor"]').selectOption('vanilla');
            if (type === 'digital') await page.locator('[id="data.category_field_values.editable"]').check();
            await page.locator('[id="data.base_price_minor"]').fill('250.50');
            if (type === 'rental') {
                await expect(page.locator('[id="data.rentalDetail.length_m"]')).toHaveValue('4.50');
                await expect(page.locator('[id="data.rentalDetail.width_m"]')).toHaveValue('3.25');
                await expect(page.locator('[id="data.rentalDetail.height_m"]')).toHaveValue('2.00');
                await page.locator('[id="data.rentalDetail.length_m"]').fill('5.25');
                await page.locator('[id="data.rentalDetail.height_m"]').fill('');
            }
            await page.getByRole('heading', { level: 1 }).scrollIntoViewIfNeeded();
            await page.screenshot({ path: `tests/e2e/.results/vendor-service-price-${type}-${locale}.png`, fullPage: true });
            await page.getByRole('button', { name: /^(Save changes|حفظ التغييرات)$/ }).click();
            await expect(page).toHaveURL(new RegExp(`${base}(\\?.*)?$`), { timeout: 15_000 });
            const edited = JSON.parse(runPhp(`$service=App\\Modules\\Catalog\\Domain\\Models\\Service::query()->where('public_id','${service.public_id}')->sole(); echo json_encode(['category_id'=>$service->category_id,'minor'=>$service->base_price_minor,'formatted'=>$service->base_price->formatTo('${locale}'==='ar'?'ar_EG':'en_GB')]);`));
            expect(edited.category_id).toBe(fixture.a.child);
            expect(edited.minor).toBe(25050);
            const dynamicValues = JSON.parse(runPhp(`$service=App\\Modules\\Catalog\\Domain\\Models\\Service::query()->where('public_id','${service.public_id}')->sole(); echo json_encode($service->category_field_values);`));
            expect(dynamicValues).toHaveProperty(type === 'rental' ? 'capacity' : type === 'sale' ? 'flavor' : 'editable');
            if (type === 'rental') expect(dynamicValues.performer_name).toBe('Updated Performer');
            await expect(page.getByRole('row').filter({ hasText: rowName })).toContainText(edited.formatted);
            await page.goto(`${base}/${service.public_id}/edit${query}`);
            await expect(child).toHaveValue(String(fixture.a.child));
            await expect(page.locator('[id="data.base_price_minor"]')).toHaveValue('250.50');
            if (type === 'rental') {
                await expect(page.locator('[id="data.rentalDetail.length_m"]')).toHaveValue('5.25');
                await expect(page.locator('[id="data.rentalDetail.width_m"]')).toHaveValue('3.25');
                await expect(page.locator('[id="data.rentalDetail.height_m"]')).toHaveValue('');
                await page.getByRole('heading', { level: 1 }).scrollIntoViewIfNeeded();
                await page.screenshot({ path: `tests/e2e/.results/vendor-rental-dimensions-${locale}.png`, fullPage: true });
                const preserved = JSON.parse(runPhp(`$service=App\\Modules\\Catalog\\Domain\\Models\\Service::query()->where('public_id','${service.public_id}')->sole(); echo json_encode($service->rentalDetail->only(['length_m','width_m','height_m','teardown_time_minutes','security_deposit_minor','minimum_space_sqm'])); $service->forceFill(['status'=>'published'])->save();`));
                expect(preserved).toEqual({ length_m: '5.25', width_m: '3.25', height_m: null,
                    teardown_time_minutes: 75, security_deposit_minor: 45000, minimum_space_sqm: 28 });
                await page.goto(`/${locale}/services/${service.public_id}`);
                const attributes = page.locator('.sf-service-attributes');
                await expect(attributes).toContainText(locale === 'ar' ? 'الطول' : 'Length');
                await expect(attributes).toContainText(`5.25 ${locale === 'ar' ? 'متر' : 'm'}`);
                await expect(attributes).toContainText(`3.25 ${locale === 'ar' ? 'متر' : 'm'}`);
                await expect(attributes).toContainText(locale === 'ar' ? 'مقدم الخدمة' : 'Performer');
                await expect(attributes).toContainText('Updated Performer');
                await expect(attributes.locator('dt').filter({ hasText: locale === 'ar' ? /^الارتفاع$/ : /^Height$/ })).toHaveCount(0);
                await attributes.scrollIntoViewIfNeeded();
                await page.keyboard.press('Control+Home');
                await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
                await page.screenshot({ path: `tests/e2e/.results/customer-rental-dimensions-${locale}.png`, fullPage: true });
            }
        }
    }
});
