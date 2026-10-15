import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const runId = Date.now().toString();
const referencePrefix = `QU${runId}`;
const vendorName = `QA Admin UX Vendor ${runId}`;
const customerName = `QA Admin UX Customer ${runId}`;
const customerEmail = `qa-admin-ux-${runId}@example.test`;
const permissions = ['view_any_bookings::monitor', 'view_any_booking', 'view_booking', 'create_booking', 'view_any_user', 'view_user', 'update_customer_profile'];
let addedPermissions: string[] = [];
const fixtureEnvironment = {
    ...process.env,
    APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: path.resolve('storage/e2e.sqlite'), DB_URL: '',
    CACHE_STORE: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array', SCOUT_DRIVER: 'null',
};
function runPhp(code: string): string {
    const wrapped = `try { ${code}\necho "\\nQA_UX_COMMAND_OK"; } catch (\\Throwable $error) { echo "\\nQA_UX_COMMAND_ERROR:".$error::class.":".$error->getMessage(); }`;
    const output = execFileSync('php', ['artisan', 'tinker', `--execute=${wrapped}`], {
        env: fixtureEnvironment, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], timeout: 30_000,
    });
    if (!output.includes('QA_UX_COMMAND_OK')) {
        throw new Error(output.match(/QA_UX_COMMAND_ERROR:([^\r\n]+)/)?.[1] ?? 'The admin UX fixture command did not complete.');
    }
    return output.replace(/\s*QA_UX_COMMAND_OK\s*$/, '');
}
test.beforeAll(() => {
    addedPermissions = JSON.parse(runPhp(`$admin=App\\Modules\\Identity\\Domain\\Models\\User::query()->where('email','uat-admin@example.test')->firstOrFail();
        $added=[];
        foreach (${JSON.stringify(permissions)} as $name) {
            if (!$admin->permissions()->where('name',$name)->exists()) { $added[]=$name; $admin->givePermissionTo(Spatie\\Permission\\Models\\Permission::findOrCreate($name,'web')); }
        }
        echo json_encode($added);`)) as string[];
    runPhp(`DB::transaction(function () {
        $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('is_active',true)->firstOrFail();
        $vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create([
            'business_name'=>['en'=>'${vendorName}','ar'=>'${vendorName} AR'],
            'primary_city_id'=>$city->id, 'primary_governorate_id'=>$city->governorate_id,
        ]);
        App\\Modules\\Identity\\Domain\\Models\\User::factory()->asCustomer()->create([
            'name'=>'${customerName}', 'email'=>'${customerEmail}',
        ]);
        foreach ([1,2] as $index) {
        $booking=App\\Modules\\Booking\\Domain\\Models\\Booking::factory()->create([
            'reference_no'=>'${referencePrefix}'.$index,
            'lifecycle_status'=>App\\Modules\\Booking\\Domain\\Enums\\LifecycleStatus::VendorReview->value,
            'event_starts_at'=>'2026-11-10 10:00:00', 'event_ends_at'=>'2026-11-10 14:00:00',
            'total_minor'=>40000, 'total_currency'=>'EGP', 'submitted_at'=>now('UTC'),
        ]);
        App\\Modules\\Booking\\Domain\\Models\\BookingVendor::factory()->create([
            'booking_id'=>$booking->id,
            'vendor_profile_id'=>$vendor->id,
        ]);
    }
    });`);
});
test.afterAll(() => {
    runPhp(`App\\Modules\\Booking\\Domain\\Models\\Booking::query()->whereIn('reference_no',${JSON.stringify([`${referencePrefix}1`, `${referencePrefix}2`])})->forceDelete();
        $vendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::query()->where('business_name->en','${vendorName}')->first();
        $vendorUser=$vendor?->user;
        $vendor?->forceDelete();
        $vendorUser?->tokens()->delete();
        $vendorUser?->forceDelete();
        App\\Modules\\Identity\\Domain\\Models\\User::query()->where('email','${customerEmail}')->first()?->forceDelete();
        $admin=App\\Modules\\Identity\\Domain\\Models\\User::query()->where('email','uat-admin@example.test')->firstOrFail();
        foreach (${JSON.stringify(addedPermissions)} as $name) { $admin->revokePermissionTo($name); }`);
});

type LivewireComponent = {
    get: (key: string) => unknown;
    set: (key: string, value: unknown) => Promise<void>;
};

declare global {
    interface Window {
        Livewire?: {
            find: (id: string) => LivewireComponent | undefined;
        };
    }
}

test('admin favorites and recents persist per signed-in user and discard hidden or external paths', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    const response = await page.goto('/admin/bookings-monitor?lang=en', { waitUntil: 'domcontentloaded' });

    expect(response?.status()).toBe(200);
    const shortcuts = page.locator('[data-admin-nav-shortcuts]');
    await expect(shortcuts).toBeVisible();
    await expect(shortcuts.getByRole('heading', { name: 'Favorites' })).toBeVisible();
    await expect(shortcuts.getByRole('heading', { name: 'Recently opened' })).toBeVisible();

    const storageKey = await shortcuts.getAttribute('data-storage-key');
    expect(storageKey).toMatch(/^instaparty\.admin\.nav\.\d+\.v1$/);
    const userStorageKey = storageKey!;
    await page.evaluate((key) => localStorage.removeItem(key), userStorageKey);
    await page.reload();

    const bookingMonitorLink = page.locator('.fi-sidebar-item').filter({
        has: page.locator('a.fi-sidebar-item-button[href*="/admin/bookings-monitor"]'),
    }).first();
    const favoriteButton = bookingMonitorLink.locator('[data-admin-nav-favorite]');
    await expect(favoriteButton).toBeVisible();
    await favoriteButton.focus();
    await favoriteButton.press('Enter');
    await expect(favoriteButton).toHaveAttribute('aria-pressed', 'true');
    await expect(shortcuts.locator('[data-favorite-items] a[href="/admin/bookings-monitor"]')).toBeVisible();
    await expect(shortcuts.locator('[data-recent-items] a[href="/admin/bookings-monitor"]')).toBeVisible();

    await page.reload();
    await expect(page.locator('.fi-sidebar-item').filter({
        has: page.locator('a.fi-sidebar-item-button[href*="/admin/bookings-monitor"]'),
    }).first().locator('[data-admin-nav-favorite]')).toHaveAttribute('aria-pressed', 'true');

    const otherAccountKey = `${userStorageKey}.other-account`;
    await page.evaluate(({ key, otherKey }) => {
        localStorage.setItem(otherKey, JSON.stringify({ favorites: ['/admin/bookings-monitor'], recent: [] }));
        localStorage.setItem(key, JSON.stringify({
            favorites: ['/admin/not-authorized', 'https://example.invalid/admin/bookings-monitor'],
            recent: ['/admin/not-authorized'],
        }));
    }, { key: userStorageKey, otherKey: otherAccountKey });
    await page.reload();

    await expect(page.locator('[data-favorite-items] a')).toHaveCount(0);
    await expect(page.locator('[data-recent-items] a[href="/admin/not-authorized"]')).toHaveCount(0);
    const sanitized = await page.evaluate((key) => JSON.parse(localStorage.getItem(key) || '{}'), userStorageKey);
    expect(sanitized.favorites).toEqual([]);
    expect(sanitized.recent).not.toContain('/admin/not-authorized');
    await page.evaluate((key) => localStorage.removeItem(key), otherAccountKey);
});

test('booking monitor filters save and apply by keyboard, and cards fit a 390px viewport in both directions', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const response = await page.goto('/admin/bookings-monitor?lang=en', { waitUntil: 'domcontentloaded' });

    expect(response?.status()).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');

    const savedViews = page.locator('[data-booking-monitor-saved-views]');
    await expect(savedViews).toBeVisible();
    const savedViewsKey = await savedViews.getAttribute('data-storage-key');
    expect(savedViewsKey).toMatch(/^instaparty\.admin\.bookings-monitor\.views\.\d+\.v1$/);
    const viewsStorageKey = savedViewsKey!;
    await page.evaluate((key) => localStorage.removeItem(key), viewsStorageKey);

    const table = page.locator('.fi-ta');
    const filtersButton = table.getByRole('button', { name: /^Filter\b/i }).first();
    await filtersButton.focus();
    await filtersButton.press('Enter');
    const dateFrom = page.locator('[id="tableFilters.event_date.from"]');
    await expect(dateFrom).toBeVisible();
    await expect(page.getByLabel('Minimum total (EGP)')).toBeVisible();
    await expect(page.locator('[id="tableFilters.vendor_id.value"]')).toBeAttached();
    await expect(page.locator('.fi-ta-filters').getByText('Vendor', { exact: true })).toBeVisible();
    await dateFrom.click();
    const calendar = page.locator('.fi-fo-date-time-picker-panel:visible').last();
    await calendar.locator('select').selectOption('10');
    await calendar.locator('input[type="number"]').fill('2026');
    await calendar.getByRole('option', { name: '1', exact: true }).click();
    await page.getByLabel('Minimum total (EGP)').fill('300.00');
    await page.getByLabel('Maximum total (EGP)').fill('500.00');
    const vendorChoices = page.locator('.fi-ta-filters .choices').last();
    await vendorChoices.locator('.choices__inner').click();
    await vendorChoices.locator('input.choices__input--cloned').fill(vendorName);
    const vendorOption = vendorChoices.getByRole('option', { name: vendorName, exact: true });
    await expect(vendorOption).toBeVisible();
    await vendorOption.click();

    const livewireId = await page.evaluate(() => document
        .querySelector('[data-booking-monitor-saved-views]')
        ?.closest('[wire\\:id]')
        ?.getAttribute('wire:id') ?? null);
    expect(livewireId).toBeTruthy();
    await expect.poll(async () => page.evaluate((id) => window.Livewire?.find(id!)?.get('tableFilters'), livewireId!))
        .toMatchObject({
            event_date: { from: expect.stringMatching(/^2026-11-01(?: 00:00:00)?$/) },
            total_range: { min: '300.00', max: '500.00' },
            vendor_id: { value: expect.any(String) },
        });

    await page.keyboard.press('Escape');
    const viewName = savedViews.locator('input[name="name"]');
    await viewName.fill('November review queue');
    await viewName.press('Enter');
    const savedViewButton = savedViews.locator('[data-saved-view-items] button').filter({ hasText: 'November review queue' }).first();
    await expect(savedViewButton).toBeVisible();
    await page.reload();
    await expect(page.locator('[data-saved-view-items] button').filter({ hasText: 'November review queue' })).toBeVisible();
    const reloadedLivewireId = await page.evaluate(() => document
        .querySelector('[data-booking-monitor-saved-views]')
        ?.closest('[wire\\:id]')
        ?.getAttribute('wire:id') ?? null);
    expect(reloadedLivewireId).toBeTruthy();

    await page.locator('[data-saved-view-items] button').filter({ hasText: 'November review queue' }).first().focus();
    await page.locator('[data-saved-view-items] button').filter({ hasText: 'November review queue' }).first().press('Enter');
    await expect(page.locator('[data-saved-view-status]')).toHaveText('View applied.');
    await expect.poll(async () => page.evaluate((id) => window.Livewire?.find(id!)?.get('tableFilters'), reloadedLivewireId!))
        .toMatchObject({
            event_date: { from: expect.stringMatching(/^2026-11-01(?: 00:00:00)?$/) },
            total_range: { min: '300.00', max: '500.00' },
        });

    const mobileLayout = await page.evaluate(() => ({
        viewportWidth: window.innerWidth,
        documentWidth: document.documentElement.scrollWidth,
        gridColumns: getComputedStyle(document.querySelector('.fi-ta-content-grid')!).gridTemplateColumns,
        cards: [...document.querySelectorAll<HTMLElement>('.fi-ta-record')].map((card) => ({
            width: card.clientWidth,
            contentWidth: card.scrollWidth,
            actions: [...card.querySelectorAll<HTMLElement>('.fi-ac')].map((actions) => ({
                left: actions.getBoundingClientRect().left,
                right: actions.getBoundingClientRect().right,
                visible: getComputedStyle(actions).display !== 'none',
            })),
        })),
    }));
    expect(mobileLayout.documentWidth).toBeLessThanOrEqual(mobileLayout.viewportWidth);
    expect(mobileLayout.gridColumns).not.toBe('none');
    expect(mobileLayout.gridColumns.split(' ').length).toBe(1);
    expect(mobileLayout.cards.length).toBeGreaterThanOrEqual(2);
    for (const card of mobileLayout.cards) {
        expect(card.contentWidth).toBeLessThanOrEqual(card.width + 1);
        for (const actions of card.actions) {
            expect(actions.visible).toBe(true);
            expect(actions.left).toBeGreaterThanOrEqual(0);
            expect(actions.right).toBeLessThanOrEqual(mobileLayout.viewportWidth + 1);
        }
    }

    await page.goto('/admin/bookings-monitor?lang=ar', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('[data-booking-monitor-saved-views]').getByRole('heading', { name: 'العروض المحفوظة' })).toBeVisible();
    await expect(page.getByRole('button', { name: /مرشح|تصفية/i }).first()).toBeVisible();

    const badgeContrasts = await page.evaluate(() => {
        const originalDark = document.documentElement.classList.contains('dark');
        const tones = ['success', 'danger', 'warning', 'info'];
        const rgba = (color: string): { channels: number[]; alpha: number } => {
            const values = color.match(/[\d.]+/g)?.map(Number) ?? [];
            return { channels: values.slice(0, 3), alpha: values[3] ?? 1 };
        };
        const blend = (foreground: string, background: string): string => {
            const front = rgba(foreground);
            const back = rgba(background);
            const channels = front.channels.map((channel, index) => Math.round((channel * front.alpha) + (back.channels[index] * (1 - front.alpha))));
            return `rgb(${channels.join(' ')})`;
        };
        const ratio = (foreground: string, background: string): number => {
            const values = (color: string): number[] => rgba(color).channels;
            const luminance = (color: string): number => {
                const channels = values(color).map((channel) => {
                    const value = channel / 255;
                    return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
                });
                return (0.2126 * channels[0]) + (0.7152 * channels[1]) + (0.0722 * channels[2]);
            };

            const first = luminance(foreground);
            const second = luminance(background);

            return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05);
        };

        const panel = document.createElement('div');
        panel.className = 'fi-panel-admin';
        panel.style.position = 'fixed';
        panel.style.insetInlineStart = '-10000px';
        const probe = document.createElement('div');
        probe.className = 'fi-ta-record bg-white dark:bg-gray-900';
        panel.append(probe);
        for (const tone of tones) {
            const badge = document.createElement('span');
            badge.className = `fi-badge fi-color-custom fi-color-${tone} bg-custom-50 text-custom-600`;
            badge.textContent = tone;
            probe.append(badge);
        }
        document.body.append(panel);

        const results: { mode: string; tone: string; foreground: string; background: string; ratio: number }[] = [];
        for (const dark of [false, true]) {
            document.documentElement.classList.toggle('dark', dark);
            for (const badge of probe.querySelectorAll<HTMLElement>('.fi-badge')) {
                const style = getComputedStyle(badge);
                const tone = [...badge.classList].find((name) => name.startsWith('fi-color-') && name !== 'fi-color-custom')?.replace('fi-color-', '') ?? 'unknown';
                const surface = getComputedStyle(probe).backgroundColor;
                const background = blend(style.backgroundColor, surface);
                results.push({ mode: dark ? 'dark' : 'light', tone, foreground: style.color, background, ratio: ratio(style.color, background) });
            }
        }

        document.documentElement.classList.toggle('dark', originalDark);
        panel.remove();

        return results;
    });
    expect(badgeContrasts).toHaveLength(8);
    for (const contrast of badgeContrasts) {
        expect(contrast.ratio, `${contrast.mode} ${contrast.tone} badge contrast ${contrast.foreground} on ${contrast.background}`).toBeGreaterThanOrEqual(4.5);
    }
});


test('the language switch exposes native locale names, changes direction, and keeps phone numbers LTR in Arabic', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    const response = await page.goto('/admin/bookings-monitor?lang=en', { waitUntil: 'domcontentloaded' });

    expect(response?.status()).toBe(200);
    const languageTrigger = page.locator('.language-switch-trigger').first();
    await expect(languageTrigger).toBeVisible();
    await languageTrigger.click();
    const arabicChoice = page.getByRole('button', { name: 'العربية' });
    await expect(arabicChoice).toBeVisible();
    await arabicChoice.click();
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('body')).toContainText('متابعة التفاوض');

    await page.locator('.language-switch-trigger').first().click();
    const englishChoice = page.getByRole('button', { name: 'English' });
    await expect(englishChoice).toBeVisible();
    await englishChoice.click();
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');

    const customersResponse = await page.goto('/admin/customers?lang=ar', { waitUntil: 'domcontentloaded' });
    expect(customersResponse?.status()).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await page.locator('input[type="search"]').first().fill(customerEmail);
    const customerLink = page.getByRole('link', { name: customerName, exact: true });
    await expect(customerLink).toBeVisible();
    await customerLink.click();
    const editProfile = page.getByRole('button', { name: 'تعديل الملف الشخصي' });
    await expect(editProfile).toBeVisible();
    await editProfile.click();
    const phone = page.getByLabel('رقم الهاتف');
    await expect(phone).toBeVisible();
    const phoneDirection = await phone.evaluate((input) => ({
        direction: getComputedStyle(input).direction,
        unicodeBidi: getComputedStyle(input).unicodeBidi,
        textAlign: getComputedStyle(input).textAlign,
    }));
    console.info('QA_PHONE_STYLES', await phone.evaluate((input) => {
        const rules: string[] = [];
        const visit = (list: CSSRuleList) => {
            for (const rule of list) {
                if (rule instanceof CSSStyleRule && (rule.style.direction || rule.style.textAlign)) {
                    try { if (input.matches(rule.selectorText)) rules.push(rule.cssText); } catch {}
                }
                if ('cssRules' in rule) visit((rule as CSSGroupingRule).cssRules);
            }
        };
        for (const sheet of document.styleSheets) { try { visit(sheet.cssRules); } catch {} }
        return { attributes: input.outerHTML, rules };
    }));
    expect(phoneDirection).toEqual({ direction: 'ltr', unicodeBidi: 'isolate', textAlign: 'left' });
});
