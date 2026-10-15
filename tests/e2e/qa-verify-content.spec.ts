import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test, type Locator, type Page } from '@playwright/test';

const runId = Date.now().toString();
const cmsAlias = `about-qa-${runId.slice(-8)}`;
const cmsEnglishTitle = `QA About ${runId}`;
const cmsArabicTitle = `حول الاختبار ${runId}`;
const cmsEnglishBody = `QA English CMS content ${runId}`;
const cmsArabicBody = `محتوى عربي للاختبار ${runId}`;
const homeName = `QA hero ${runId}`;
const templateKey = `booking.qa_content_${runId.slice(-8)}`;
const campaignScheduledName = `QA scheduled campaign ${runId}`;
const campaignDueName = `QA due campaign ${runId}`;
const cityEnglishName = `QA city ${runId}`;
const cityArabicName = `مدينة اختبار ${runId}`;
const menuName = `QA footer ${runId}`;
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

type CmsSnapshot = {
    id: number;
    public_id: string;
    slug: string;
    public_slug: string | null;
    title: Record<string, string>;
    body: Record<string, string>;
    meta_description: Record<string, string> | null;
    blocks: unknown;
    is_published: boolean;
    published_at: string | null;
    updated_by: number | null;
};

type MenuSnapshot = {
    menu: Record<string, unknown> | null;
    items: Array<Record<string, unknown>>;
};

function runPhp(execute: string): string {
    const wrapped = `try { ${execute}\necho "\\nQA_CONTENT_COMMAND_OK"; } catch (\\Throwable $error) { echo "\\nQA_CONTENT_COMMAND_ERROR:".$error::class; }`;
    const output = execFileSync('php', ['artisan', 'tinker', `--execute=${wrapped}`], {
        env: environment,
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
        timeout: 30_000,
    });
    if (!output.includes('QA_CONTENT_COMMAND_OK')) {
        throw new Error(output.match(/QA_CONTENT_COMMAND_ERROR:([^\r\n]+)/)?.[1] ?? 'The content fixture command did not complete.');
    }
    return output.replace(/\s*QA_CONTENT_COMMAND_OK\s*$/, '');
}

function readJson<T>(execute: string): T {
    const result = runPhp(execute);
    const encoded = result.match(/QA_JSON:([A-Za-z0-9+/=]+)/)?.[1];
    if (!encoded) {
        throw new Error('The local QA fixture did not return its expected snapshot.');
    }

    return JSON.parse(Buffer.from(encoded, 'base64').toString('utf8')) as T;
}

function phpJson(value: unknown): string {
    return Buffer.from(JSON.stringify(value), 'utf8').toString('base64');
}

let cmsSnapshot: CmsSnapshot | null = null;
let cmsFixtureCreated = false;
let homePublicIds: string[] = [];
let menuSnapshot: MenuSnapshot | null = null;
let menuFixtureCreated = false;
let templatePublicId = '';
let templateRecipientId = 0;
let regionPublicIds: string[] = [];
let addedPermissions: string[] = [];

const cmsUpdatePermissions = ['view_any_cms::page', 'view_cms::page', 'update_cms::page'];
const menuPermissions = ['view_any_navigation::menu', 'view_navigation::menu', 'update_navigation::menu'];
const homePermissions = ['view_any_home::block', 'view_home::block', 'update_home::block'];
const templatePermissions = ['view_any_notification::template', 'view_notification::template', 'update_notification::template'];
const campaignPermissions = ['view_any_campaign', 'view_campaign', 'create_campaign'];
const cityPermissions = ['view_any_city', 'view_city', 'create_city', 'update_city', 'delete_city'];
const badgePermissions = ['view_any_trust::badge', 'view_trust::badge', 'create_trust::badge', 'update_trust::badge'];
const requiredPermissions = [
    ...cmsUpdatePermissions, ...menuPermissions, ...homePermissions, ...templatePermissions,
    ...campaignPermissions, ...cityPermissions, ...badgePermissions,
];

function cairoFutureDateTime(hoursAhead: number): string {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Africa/Cairo',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(new Date(Date.now() + hoursAhead * 60 * 60 * 1000));
    const part = (type: Intl.DateTimeFormatPartTypes): string => parts.find((item) => item.type === type)?.value ?? '';

    return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`;
}

async function selectFilamentOption(page: Page, label: string, option: string): Promise<void> {
    const field = page.getByLabel(label).last();
    if (await field.isVisible() && await field.evaluate(element => element.tagName === 'SELECT')) {
        await field.selectOption({ label: option });
        return;
    }
    await field.locator('xpath=ancestor::*[contains(@class, "choices")][1]').click();
    const changed = label === 'Region' ? page.waitForResponse(response => response.url().includes('/livewire/update')
        && response.request().postDataJSON()?.components?.some((component: { updates?: Record<string, unknown> }) =>
            Object.hasOwn(component.updates ?? {}, 'data.region_id')), { timeout: 20_000 }) : null;
    await page.getByRole('option', { name: option, exact: true }).filter({ visible: true }).click();
    if (changed) expect((await changed).status()).toBe(200);
}

async function saveChanges(page: Page): Promise<void> {
    const response = page.waitForResponse(response => response.url().includes('/livewire/update')
        && (response.request().postData() ?? '').includes('"method":"save"'), { timeout: 20_000 });
    await page.getByRole('button', { name: /save changes/i }).click();
    expect((await response).status()).toBe(200);
    await expect(page.getByRole('button', { name: /save changes/i })).toBeEnabled({ timeout: 15_000 });
}

async function confirmAction(page: Page): Promise<void> {
    const modal = page.locator('.fi-modal-window:visible').last();
    await expect(modal).toBeVisible();
    await modal.getByRole('button', { name: /^(Confirm|تأكيد)$/i }).click();
}

async function seedBrowserFixtures(): Promise<void> {
    const permissionResult = readJson<{ added: string[] }>(`$admin=App\\Modules\\Identity\\Domain\\Models\\User::query()->where('email','uat-admin@example.test')->firstOrFail(); $added=[]; foreach (${JSON.stringify(requiredPermissions)} as $name) { $permission=Spatie\\Permission\\Models\\Permission::findOrCreate($name,'web'); if (!$admin->hasPermissionTo($permission)) { $admin->givePermissionTo($permission); $added[]=$name; } } echo 'QA_JSON:'.base64_encode(json_encode(['added'=>$added]));`);
    addedPermissions = permissionResult.added;

    cmsSnapshot = readJson<CmsSnapshot | null>(`$page=App\\Modules\\Shared\\Domain\\Models\\CmsPage::query()->where('slug','about')->first(); echo 'QA_JSON:'.base64_encode(json_encode($page===null ? null : ['id'=>$page->id,'public_id'=>$page->public_id,'slug'=>$page->slug->value,'public_slug'=>$page->public_slug,'title'=>$page->getTranslations('title'),'body'=>$page->getTranslations('body'),'meta_description'=>$page->getTranslations('meta_description'),'blocks'=>$page->blocks,'is_published'=>$page->is_published,'published_at'=>$page->published_at?->toISOString(),'updated_by'=>$page->updated_by]));`);
    if (cmsSnapshot === null) {
        cmsFixtureCreated = true;
        cmsSnapshot = readJson<CmsSnapshot>(`$page=App\\Modules\\Shared\\Domain\\Models\\CmsPage::create(['slug'=>'about','title'=>['en'=>'About QA fixture','ar'=>'صفحة تعريف للاختبار'],'body'=>['en'=>'<p>About QA fixture.</p>','ar'=>'<p>صفحة تعريف للاختبار.</p>'],'is_published'=>true,'published_at'=>now()]); echo 'QA_JSON:'.base64_encode(json_encode(['id'=>$page->id,'public_id'=>$page->public_id,'slug'=>$page->slug->value,'public_slug'=>$page->public_slug,'title'=>$page->getTranslations('title'),'body'=>$page->getTranslations('body'),'meta_description'=>$page->getTranslations('meta_description'),'blocks'=>$page->blocks,'is_published'=>$page->is_published,'published_at'=>$page->published_at?->toISOString(),'updated_by'=>$page->updated_by]));`);
    }

    const homeFixtures = readJson<{ public_ids: string[] }>(`$blocks=[]; $position=((int)(App\\Modules\\Shared\\Domain\\Models\\HomeBlock::query()->min('position') ?? 0))-2; foreach ([['${homeName}','QA hero original ${runId}','بطل اختبار أصلي ${runId}'],['QA second hero ${runId}','QA second hero ${runId}','بطل اختبار ثان ${runId}']] as [$name,$english,$arabic]) { $block=app(App\\Modules\\Shared\\Application\\Actions\\SaveHomeBlockAction::class)->execute(null,['block_type'=>'hero_carousel','name'=>$name,'position'=>$position++,'is_visible'=>true,'payload'=>['slides'=>[['headline'=>['en'=>$english,'ar'=>$arabic],'image_url'=>'https://example.test/qa-hero-${runId}.jpg','cta_label'=>['en'=>'Open QA','ar'=>'افتح الاختبار'],'cta_url'=>'/qa-hero-${runId}']]]]); $blocks[]=$block->public_id; } echo 'QA_JSON:'.base64_encode(json_encode(['public_ids'=>$blocks]));`);
    homePublicIds = homeFixtures.public_ids;

    menuSnapshot = readJson<MenuSnapshot>(`$menu=App\\Modules\\Shared\\Domain\\Models\\NavigationMenu::query()->where('slot','footer_secondary')->first(); $items=$menu===null ? [] : App\\Modules\\Shared\\Domain\\Models\\NavigationMenuItem::query()->where('menu_id',$menu->id)->orderBy('id')->get()->map(fn ($item)=>$item->getAttributes())->all(); echo 'QA_JSON:'.base64_encode(json_encode(['menu'=>$menu?->getAttributes(),'items'=>$items]));`);
    menuFixtureCreated = menuSnapshot.menu === null;
    runPhp(`app(App\\Modules\\Shared\\Application\\Actions\\SaveNavigationMenuAction::class)->execute(App\\Modules\\Shared\\Domain\\Enums\\NavigationSlot::FooterSecondary,'${menuName}',[ ['label'=>['en'=>'QA first link ${runId}','ar'=>'الرابط الأول للاختبار ${runId}'],'target_type'=>'external_url','target_value'=>'https://example.test/qa-first-${runId}','is_visible'=>true,'opens_in_new_tab'=>false,'children'=>[['label'=>['en'=>'QA child link ${runId}','ar'=>'الرابط الفرعي للاختبار ${runId}'],'target_type'=>'internal_path','target_value'=>'/qa-child-${runId}','is_visible'=>true,'opens_in_new_tab'=>false]]], ['label'=>['en'=>'QA second link ${runId}','ar'=>'الرابط الثاني للاختبار ${runId}'],'target_type'=>'external_url','target_value'=>'https://example.test/qa-second-${runId}','is_visible'=>true,'opens_in_new_tab'=>false], ['label'=>['en'=>'QA About link ${runId}','ar'=>'صفحة الاختبار ${runId}'],'target_type'=>'cms_page','target_value'=>'about','is_visible'=>true,'opens_in_new_tab'=>false], ['label'=>['en'=>'QA hidden link ${runId}','ar'=>'رابط مخفي ${runId}'],'target_type'=>'internal_path','target_value'=>'/qa-hidden-${runId}','is_visible'=>false,'opens_in_new_tab'=>false] ]);`);

    const template = readJson<{ public_id: string }>(`$template=App\\Modules\\Communication\\Domain\\Models\\NotificationTemplate::query()->create(['event_key'=>'${templateKey}','channel'=>'in_app','audience'=>'customer','body'=>['en'=>'Old QA booking {{recipient_name}}','ar'=>'حجز اختبار قديم {{recipient_name}}'],'subject'=>['en'=>'Old QA subject','ar'=>'عنوان اختبار قديم'],'variables'=>['recipient_name'=>'Recipient display name'],'is_active'=>true]); echo 'QA_JSON:'.base64_encode(json_encode(['public_id'=>$template->public_id]));`);
    templatePublicId = template.public_id;
    templateRecipientId = readJson<{ id: number }>(`$user=App\\Modules\\Identity\\Domain\\Models\\User::factory()->asCustomer()->create(['preferred_locale'=>'ar','name'=>'QA notification recipient ${runId}']); echo 'QA_JSON:'.base64_encode(json_encode(['id'=>$user->id]));`).id;

    const regions = readJson<{ public_ids: string[] }>(`$governorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->where('code','EG-CAI')->firstOrFail(); $regions=[]; foreach (['${runId}-one','${runId}-two'] as $suffix) { $region=App\\Modules\\Geography\\Domain\\Models\\Region::factory()->create(['governorate_id'=>$governorate->id,'name'=>['en'=>'QA Region '.$suffix,'ar'=>'منطقة اختبار '.$suffix]]); $regions[]=$region->public_id; } echo 'QA_JSON:'.base64_encode(json_encode(['public_ids'=>$regions]));`);
    regionPublicIds = regions.public_ids;
}

function restoreCms(): void {
    if (cmsSnapshot === null) return;
    if (cmsFixtureCreated) {
        runPhp(`App\\Modules\\Shared\\Domain\\Models\\CmsPage::query()->whereKey(${cmsSnapshot.id})->delete();`);
        return;
    }

    const snapshot = phpJson(cmsSnapshot);
    runPhp(`$data=json_decode(base64_decode('${snapshot}'),true); $page=App\\Modules\\Shared\\Domain\\Models\\CmsPage::query()->findOrFail($data['id']); $page->setTranslations('title',$data['title']); $page->setTranslations('body',$data['body']); $page->setTranslations('meta_description',$data['meta_description'] ?? []); $page->forceFill(['public_slug'=>$data['public_slug'],'blocks'=>$data['blocks'],'is_published'=>$data['is_published'],'published_at'=>$data['published_at'],'updated_by'=>$data['updated_by']])->save();`);
}

function restoreMenu(): void {
    if (menuSnapshot === null) return;
    if (menuFixtureCreated) {
        runPhp(`$menu=App\\Modules\\Shared\\Domain\\Models\\NavigationMenu::query()->where('slot','footer_secondary')->first(); if ($menu!==null) { $menu->items()->delete(); $menu->delete(); }`);
        return;
    }

    const snapshot = phpJson(menuSnapshot);
    runPhp(`$data=json_decode(base64_decode('${snapshot}'),true); $menu=App\\Modules\\Shared\\Domain\\Models\\NavigationMenu::query()->findOrFail($data['menu']['id']); App\\Modules\\Shared\\Domain\\Models\\NavigationMenuItem::query()->where('menu_id',$menu->id)->delete(); $menu->forceFill(['name'=>$data['menu']['name']])->save(); foreach ($data['items'] as $item) { Illuminate\\Support\\Facades\\DB::table('navigation_menu_items')->insert($item); } foreach (App\\Modules\\Shared\\Domain\\Enums\\NavigationSlot::cases() as $slot) { Illuminate\\Support\\Facades\\Cache::forget('theme:menus:'.$slot->value.':en'); Illuminate\\Support\\Facades\\Cache::forget('theme:menus:'.$slot->value.':ar'); }`);
}

function cleanupBrowserFixtures(): void {
    if (homePublicIds.length > 0) {
        runPhp(`App\\Modules\\Shared\\Domain\\Models\\HomeBlock::query()->whereIn('public_id',${JSON.stringify(homePublicIds)})->delete();`);
    }
    runPhp(`App\\Modules\\Communication\\Domain\\Models\\NotificationDispatch::query()->whereHas('template',fn($query)=>$query->where('event_key','${templateKey}'))->delete(); Illuminate\\Support\\Facades\\DB::table('notifications')->where('notifiable_type',App\\Modules\\Identity\\Domain\\Models\\User::class)->where('notifiable_id',${templateRecipientId})->delete(); App\\Modules\\Communication\\Domain\\Models\\NotificationTemplate::query()->where('event_key','${templateKey}')->delete(); App\\Modules\\Identity\\Domain\\Models\\User::query()->whereKey(${templateRecipientId})->delete(); App\\Modules\\Communication\\Domain\\Models\\CampaignRecipient::query()->whereHas('run.campaign',fn($query)=>$query->whereIn('name',['${campaignScheduledName}','${campaignDueName}']))->delete(); App\\Modules\\Communication\\Domain\\Models\\CampaignRun::query()->whereHas('campaign',fn($query)=>$query->whereIn('name',['${campaignScheduledName}','${campaignDueName}']))->delete(); App\\Modules\\Communication\\Domain\\Models\\Campaign::query()->whereIn('name',['${campaignScheduledName}','${campaignDueName}'])->delete(); App\\Modules\\Geography\\Domain\\Models\\City::query()->where('name->en','like','${cityEnglishName}%')->delete(); App\\Modules\\Geography\\Domain\\Models\\Region::query()->where('name->en','like','QA Region ${runId}-%')->delete(); App\\Modules\\Shared\\Domain\\Models\\HomeBlock::query()->whereIn('name',['${homeName}','QA second hero ${runId}'])->delete(); App\\Modules\\TrustSafety\\Domain\\Models\\TrustBadge::query()->where('name->en','QA Trust Badge ${runId}')->delete();`);
    restoreMenu();
    restoreCms();
    if (cmsSnapshot === null && cmsFixtureCreated) {
        runPhp(`App\\Modules\\Shared\\Domain\\Models\\CmsPage::query()->where('slug','about')->delete();`);
    }
    if (addedPermissions.length > 0) {
        runPhp(`$admin=App\\Modules\\Identity\\Domain\\Models\\User::query()->where('email','uat-admin@example.test')->first(); if ($admin!==null) { foreach (${JSON.stringify(addedPermissions)} as $name) { $permission=Spatie\\Permission\\Models\\Permission::query()->where('name',$name)->where('guard_name','web')->first(); if ($permission!==null) { $admin->revokePermissionTo($permission); } } }`);
    }
}

function richEditors(page: Page): Locator {
    return page.locator('trix-editor, [contenteditable]');
}

test.describe('QA verification: content, campaigns, templates and geography', () => {
    test.setTimeout(60_000);
    test.beforeAll(async () => {
        await seedBrowserFixtures();
    });

    test.afterAll(async () => {
        cleanupBrowserFixtures();
    });

    test('creates one valid bilingual Trust Badge after a delayed double-click', async ({ page }) => {
        await page.goto('/admin/trust-badges/create?lang=en');
        const englishTab = page.getByRole('tabpanel', { name: /^Name \(English\)\*/ });
        const englishName = englishTab.getByRole('textbox', { name: /^Name \(English\)\*/ });
        const englishDescription = englishTab.getByRole('textbox', { name: /^Description \(English\)\*/ });
        await expect(englishName).toBeVisible();
        await englishName.fill(`QA Trust Badge ${runId}`);
        await englishDescription.fill(`English QA badge ${runId}`);
        await page.getByRole('tab', { name: 'Arabic' }).click();
        const arabicTab = page.getByRole('tabpanel', { name: /^Name \(Arabic\)\*/ });
        const arabicName = arabicTab.getByRole('textbox', { name: /^Name \(Arabic\)\*/ });
        const arabicDescription = arabicTab.getByRole('textbox', { name: /^Description \(Arabic\)\*/ });
        await expect(arabicName).toBeVisible();
        await arabicName.fill(`شارة اختبار ${runId}`);
        await arabicDescription.fill(`وصف الشارة ${runId}`);
        await page.getByLabel('Badge level').fill('3');

        let delayFirstUpdate = true;
        await page.route('**/livewire/update', async (route) => {
            if (delayFirstUpdate) {
                delayFirstUpdate = false;
                await new Promise((resolve) => setTimeout(resolve, 350));
            }
            await route.continue();
        });
        const createResponse = page.waitForResponse((response) => response.url().endsWith('/livewire/update'));
        await page.getByRole('button', { name: /^create$/i }).dblclick({ delay: 35 });
        await createResponse;
        await expect(page).toHaveURL(/\/admin\/trust-badges\/[^/]+\/edit(?:\?lang=en)?$/);
        await page.unroute('**/livewire/update');
        await expect(englishName).toHaveValue(`QA Trust Badge ${runId}`);

        const badge = readJson<{ public_id: string }>(`$badge=App\\Modules\\TrustSafety\\Domain\\Models\\TrustBadge::query()->where('name->en','QA Trust Badge ${runId}')->sole(); if ($badge->level!==3 || $badge->getTranslation('name','ar',false)!=='شارة اختبار ${runId}') { throw new RuntimeException('Trust badge values did not persist.'); } echo 'QA_JSON:'.base64_encode(json_encode(['public_id'=>$badge->public_id]));`);
        await expect(page).toHaveURL(new RegExp(`/admin/trust-badges/${badge.public_id}/edit(?:\\?lang=en)?$`));
        await page.goBack();
        await expect(page).toHaveURL(/\/admin\/trust-badges\/create(?:\?[^#]*)?$/);
        const retryEnglishTab = page.getByRole('tabpanel', { name: /^Name \(English\)\*/ });
        await retryEnglishTab.getByRole('textbox', { name: /^Name \(English\)\*/ }).fill(`QA Trust Badge ${runId}`);
        await retryEnglishTab.getByRole('textbox', { name: /^Description \(English\)\*/ }).fill(`English QA badge ${runId}`);
        await page.getByRole('tab', { name: 'Arabic' }).click();
        const retryArabicTab = page.getByRole('tabpanel', { name: /^Name \(Arabic\)\*/ });
        await retryArabicTab.getByRole('textbox', { name: /^Name \(Arabic\)\*/ }).fill(`شارة اختبار ${runId}`);
        await retryArabicTab.getByRole('textbox', { name: /^Description \(Arabic\)\*/ }).fill(`وصف الشارة ${runId}`);
        await page.getByLabel('Badge level').fill('3');
        await page.getByRole('button', { name: /^create$/i }).click();
        await expect(page).toHaveURL(new RegExp(`/admin/trust-badges/${badge.public_id}/edit(?:\\?lang=en)?$`));
        const badgeAfterRetry = readJson<{ public_id: string; count: number }>(`$badge=App\\Modules\\TrustSafety\\Domain\\Models\\TrustBadge::query()->where('name->en','QA Trust Badge ${runId}')->sole(); $count=App\\Modules\\TrustSafety\\Domain\\Models\\TrustBadge::query()->where('name->en','QA Trust Badge ${runId}')->count(); echo 'QA_JSON:'.base64_encode(json_encode(['public_id'=>$badge->public_id,'count'=>$count]));`);
        expect(badgeAfterRetry.public_id).toBe(badge.public_id);
        expect(badgeAfterRetry.count).toBe(1);
        await page.reload();
        await expect(englishName).toHaveValue(`QA Trust Badge ${runId}`);
    });

    test('edits a bilingual CMS page, keeps its canonical URL and propagates the public alias', async ({ page }) => {
        if (!cmsSnapshot?.is_published) test.skip(true, 'The isolated About page must already be published for public-page verification.');
        await page.goto(`/admin/cms-pages/${cmsSnapshot.public_id}/edit?lang=en`);
        await page.locator('input[id="data.public_slug"]').fill(cmsAlias);
        await page.locator('input[id="data.title.en"]').fill(cmsEnglishTitle);
        await richEditors(page).nth(0).fill(cmsEnglishBody);
        await page.locator('[role="tab"]').nth(1).click();
        await page.locator('input[id="data.title.ar"]').fill(cmsArabicTitle);
        await richEditors(page).nth(1).fill(cmsArabicBody);
        await saveChanges(page);

        await page.goto(`/en/p/${cmsAlias}`);
        await expect(page.locator('main').first()).toContainText(cmsEnglishTitle);
        await expect(page.locator('main').first()).toContainText(cmsEnglishBody);
        await page.goto(`/ar/p/${cmsAlias}`);
        await expect(page.locator('main').first()).toContainText(cmsArabicTitle);
        await expect(page.locator('main').first()).toContainText(cmsArabicBody);
        await page.goto(`/en/p/${cmsSnapshot.slug}`);
        await expect(page.locator('main').first()).toContainText(cmsEnglishTitle);

        await page.goto('/en');
        const cmsMenuLink = page.locator(`footer nav[aria-label="${menuName}"]`).getByRole('link', { name: `QA About link ${runId}`, exact: true });
        await expect(cmsMenuLink).toHaveAttribute('href', new RegExp(`/en/p/${cmsAlias}$`));

        await page.goto('/admin/cms-pages?lang=en');
        const row = page.locator('tr').filter({ hasText: cmsEnglishTitle }).first();
        if (await row.getByRole('button', { name: /unpublish/i }).count()) {
            await row.getByRole('button', { name: /unpublish/i }).click();
            await confirmAction(page);
            await expect(page.getByText(/unpublished/i).last()).toBeVisible();
            await page.goto(`/en/p/${cmsAlias}`);
            await expect(page).toHaveURL(/\/en\/p\//);
            expect((await page.request.get(page.url())).status()).toBe(404);

            await page.goto('/admin/cms-pages?lang=en');
            const draft = page.locator('tr').filter({ hasText: cmsEnglishTitle }).first();
            await draft.getByRole('button', { name: /publish/i }).click();
            await confirmAction(page);
            await page.goto(`/en/p/${cmsAlias}`);
            await expect(page.locator('main').first()).toContainText(cmsEnglishBody);
        }
    });

    test('edits a homepage hero block and checks content, target, order and visibility in both locales', async ({ page }) => {
        await page.goto(`/admin/home-blocks/${homePublicIds[0]}/edit?lang=en`);
        await page.locator('input[id="data.name"]').fill(`${homeName} edited`);
        await page.getByLabel('Headline (EN)').fill(`QA hero edited ${runId}`);
        await page.getByLabel('Headline (AR)').fill(`بطل اختبار معدل ${runId}`);
        await page.getByLabel('CTA destination').fill(`/qa-home-edited-${runId}`);
        await saveChanges(page);

        await page.goto('/en');
        await expect(page.locator('h1').first()).toContainText(`QA hero edited ${runId}`);
        await expect(page.getByRole('link', { name: /open qa/i }).first()).toHaveAttribute('href', new RegExp(`/en/qa-home-edited-${runId}`));
        await page.goto('/ar');
        await expect(page.locator('h1').first()).toContainText(`بطل اختبار معدل ${runId}`);

        await page.goto(`/admin/home-blocks/${homePublicIds[0]}/edit?lang=en`);
        await page.locator('input[id="data.position"]').fill('2147483000');
        await page.getByRole('button', { name: /save changes/i }).click();
        await page.setViewportSize({ width: 1365, height: 900 });
        await page.goto('/en');
        await expect(page.locator('h1').first()).toContainText(`QA second hero ${runId}`);

        await page.goto(`/admin/home-blocks/${homePublicIds[0]}/edit?lang=en`);
        const visibility = page.getByRole('switch').first();
        await expect(visibility).toHaveAttribute('aria-checked', 'true');
        await visibility.click();
        await page.getByRole('button', { name: /save changes/i }).click();
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/ar');
        await expect(page.locator('h1').first()).toContainText(`بطل اختبار ثان ${runId}`);
    });

    test('edits the bilingual footer menu and verifies safe links on desktop and mobile', async ({ page }) => {
        await page.goto('/admin/navigation-menus?lang=en');
        const row = page.locator('tr').filter({ hasText: menuName }).first();
        await expect(row).toBeVisible();
        await row.getByRole('link', { name: /edit/i }).click();
        await page.locator('input[id="data.name"]').fill(menuName);
        await page.getByRole('tab', { name: 'English' }).first().click();
        await page.locator('input[id$=".label.en"]').first().fill(`QA first edited ${runId}`);
        await page.getByRole('tab', { name: 'العربية' }).first().click();
        await page.locator('input[id$=".label.ar"]').first().fill(`الرابط الأول المعدل ${runId}`);
        await page.getByLabel('External URL').first().fill(`https://example.test/qa-safe-${runId}`);
        await saveChanges(page);

        await page.setViewportSize({ width: 1365, height: 900 });
        await page.goto('/en');
        const desktopFooter = page.locator('footer').getByRole('link', { name: `QA first edited ${runId}`, exact: true });
        await expect(desktopFooter).toHaveAttribute('href', `https://example.test/qa-safe-${runId}`);
        const desktopMenu = page.locator(`footer nav[aria-label="${menuName}"]`);
        await expect(desktopMenu).toContainText(`QA second link ${runId}`);
        await expect(desktopMenu).toContainText(`QA child link ${runId}`);
        await expect(desktopMenu).toContainText(`QA About link ${runId}`);
        await expect(desktopMenu).not.toContainText(`QA hidden link ${runId}`);
        await expect(desktopMenu.getByRole('link', { name: `QA child link ${runId}`, exact: true }))
            .toHaveAttribute('href', new RegExp(`/en/qa-child-${runId}$`));
        await expect(desktopMenu.locator('a').nth(0)).toContainText(`QA first edited ${runId}`);
        await expect(desktopMenu.locator('a').nth(1)).toContainText(`QA child link ${runId}`);
        await expect(desktopMenu.locator('a').nth(2)).toContainText(`QA second link ${runId}`);

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/ar');
        await page.locator('footer').scrollIntoViewIfNeeded();
        const mobileMenu = page.locator(`footer nav[aria-label="${menuName}"]`);
        await expect(mobileMenu.getByRole('link', { name: `الرابط الأول المعدل ${runId}`, exact: true }))
            .toHaveAttribute('href', `https://example.test/qa-safe-${runId}`);
        await expect(mobileMenu).toContainText(`الرابط الثاني للاختبار ${runId}`);
        await expect(mobileMenu).toContainText(`الرابط الفرعي للاختبار ${runId}`);
        await expect(mobileMenu).not.toContainText(`رابط مخفي ${runId}`);
        await expect(mobileMenu.locator('a').nth(0)).toContainText(`الرابط الأول المعدل ${runId}`);
        await expect(mobileMenu.locator('a').nth(1)).toContainText(`الرابط الفرعي للاختبار ${runId}`);
        await expect(mobileMenu.locator('a').nth(2)).toContainText(`الرابط الثاني للاختبار ${runId}`);
    });

    test('creates, schedules, cancels and safely completes a zero-recipient campaign', async ({ page }) => {
        const createCampaign = async (name: string, locale: string): Promise<void> => {
            await page.goto('/admin/campaigns/create?lang=en');
            await page.locator('input[id="data.name"]').fill(name);
            await selectFilamentOption(page, 'Channel', 'Email');
            await selectFilamentOption(page, 'Target locale', 'Both (EN + AR)');
            await page.locator('input[id="data.scheduled_at"]').fill(cairoFutureDateTime(12));
            const filters = page.locator('.fi-fo-key-value');
            const rows = filters.locator('input');
            await filters.getByRole('button', { name: /add (entry|row)/i }).click();
            await rows.nth(0).fill('preferred_locale');
            await rows.nth(1).fill(locale);
            await page.locator('input[id="data.subject.en"]').fill('QA campaign subject');
            await page.locator('textarea[id="data.body.en"]').fill('QA test-only campaign body');
            await page.getByRole('tab').nth(1).click();
            await page.locator('input[id="data.subject.ar"]').fill('عنوان حملة للاختبار');
            await page.locator('textarea[id="data.body.ar"]').fill('محتوى حملة تجريبي آمن');
            await page.getByRole('button', { name: /^create$/i }).click();
            await expect(page).toHaveURL(/\/admin\/campaigns\/?$/);
            await expect(page.locator('tr').filter({ hasText: name }).first()).toBeVisible();
        };

        await createCampaign(campaignScheduledName, 'qa-never-matches');
        let row = page.locator('tr').filter({ hasText: campaignScheduledName }).first();
        await expect(row).toContainText(/scheduled/i);
        await row.getByRole('button', { name: /cancel/i }).click();
        await confirmAction(page);
        await expect(page.locator('tr').filter({ hasText: campaignScheduledName }).first()).toContainText(/cancelled/i);

        await createCampaign(campaignDueName, `no-customer-${runId}`);
        runPhp(`$campaign=App\\Modules\\Communication\\Domain\\Models\\Campaign::query()->where('name','${campaignDueName}')->firstOrFail(); $campaign->scheduled_at=now()->subMinute(); $campaign->save();`);
        runPhp(`Illuminate\\Support\\Facades\\Artisan::call('communication:dispatch-due-campaigns'); $campaign=App\\Modules\\Communication\\Domain\\Models\\Campaign::query()->where('name','${campaignDueName}')->firstOrFail(); $run=$campaign->runs()->sole(); if ($campaign->status!==App\\Modules\\Communication\\Domain\\Enums\\CampaignStatus::Completed || $run->recipients_total!==0 || App\\Modules\\Communication\\Domain\\Models\\NotificationDispatch::query()->where('reference_type',App\\Modules\\Communication\\Domain\\Models\\Campaign::class)->where('reference_id',$campaign->id)->exists()) { throw new RuntimeException('The isolated no-recipient campaign did not complete safely.'); }`);
        await page.goto('/admin/campaigns?lang=en');
        row = page.locator('tr').filter({ hasText: campaignDueName }).first();
        await expect(row).toContainText(/completed/i);
    });

    test('edits and renders the saved event template into the local in-app notification sink', async ({ page }) => {
        await page.goto(`/admin/notification-templates/${templatePublicId}/edit?lang=en`);
        await page.locator('textarea[id="data.subject"]').fill('QA booking update {{recipient_name}}');
        await page.locator('textarea[id="data.body"]').fill('QA booking {{recipient_name}} confirmed.');
        await saveChanges(page);

        await page.locator('select').filter({ has: page.locator('option[value="ar"]') }).selectOption('ar');
        await expect(page.locator('textarea[id="data.body"]')).toHaveValue('حجز اختبار قديم {{recipient_name}}');
        await page.locator('textarea[id="data.subject"]').fill('تحديث الحجز لـ {{recipient_name}}');
        await page.locator('textarea[id="data.body"]').fill('حجز {{recipient_name}} مؤكد.');
        await saveChanges(page);

        const dispatch = readJson<{ body: string; subject: string; locale: string; status: string; translations: Record<string, string> }>(`$user=App\\Modules\\Identity\\Domain\\Models\\User::query()->findOrFail(${templateRecipientId}); $dispatch=app(App\\Modules\\Communication\\Application\\Actions\\DispatchNotificationAction::class)->execute(new App\\Modules\\Communication\\Application\\DTOs\\DispatchNotificationDTO(eventKey:'${templateKey}',channel:App\\Modules\\Communication\\Domain\\Enums\\NotificationChannel::InApp,audience:App\\Modules\\Communication\\Domain\\Enums\\NotificationAudience::Customer,eventCategory:App\\Modules\\Communication\\Domain\\Enums\\EventCategory::Booking,userId:$user->id,context:['recipient_name'=>'<QA recipient>'])); if ($dispatch===null) { throw new RuntimeException('The local template event did not create a dispatch.'); } $template=App\\Modules\\Communication\\Domain\\Models\\NotificationTemplate::query()->where('event_key','${templateKey}')->sole(); echo 'QA_JSON:'.base64_encode(json_encode(['body'=>$dispatch->refresh()->context['body'],'subject'=>$dispatch->context['subject'],'locale'=>$dispatch->locale,'status'=>$dispatch->status->value,'translations'=>$template->getTranslations('body')]));`);
        expect(dispatch.locale).toBe('ar');
        expect(dispatch.body).toBe('حجز <QA recipient> مؤكد.');
        expect(dispatch.subject).toBe('تحديث الحجز لـ <QA recipient>');
        expect(dispatch.translations.en).toBe('QA booking {{recipient_name}} confirmed.');
        expect(dispatch.translations.ar).toBe('حجز {{recipient_name}} مؤكد.');
        expect(dispatch.status).toBe('sent');
        expect(dispatch.body).not.toContain('{{');
    });

    test('creates, edits and removes a bilingual city through the region selector', async ({ page }) => {
        const regionNames = [`QA Region ${runId}-one`, `QA Region ${runId}-two`];
        await page.goto('/admin/cities/create?lang=en');
        await selectFilamentOption(page, 'Region', regionNames[0]);
        await page.locator('input[id="data.name.en"]').fill(cityEnglishName);
        await page.getByRole('tab', { name: 'Arabic', exact: true }).click();
        await page.locator('input[id="data.name.ar"]').fill(cityArabicName);
        await page.locator('input[id="data.sort_order"]').fill('3');
        await page.getByRole('button', { name: /^create$/i }).click();
        await expect(page).toHaveURL(/\/admin\/cities\/[0-9A-HJKMNP-TV-Z]{26}\/edit/);
        await page.goto('/admin/cities?lang=en');
        let row = page.locator('tr').filter({ hasText: cityEnglishName }).first();
        await expect(row).toBeVisible();

        const city = readJson<{ public_id: string; region_id: number; governorate_id: number }>(`$city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('name->en','${cityEnglishName}')->firstOrFail(); echo 'QA_JSON:'.base64_encode(json_encode(['public_id'=>$city->public_id,'region_id'=>$city->region_id,'governorate_id'=>$city->governorate_id]));`);
        const firstRegion = regionPublicIds[0];
        const secondRegion = regionPublicIds[1];
        const initialCities = await page.request.get(`/api/v1/customer/regions/${firstRegion}/cities`);
        expect(initialCities.status()).toBe(200);
        expect((await initialCities.json()).data.some((item: { public_id: string }) => item.public_id === city.public_id)).toBeTruthy();

        await row.getByRole('link', { name: /edit/i }).click();
        await selectFilamentOption(page, 'Region', regionNames[1]);
        await page.locator('input[id="data.name.en"]').fill(`${cityEnglishName} edited`);
        await page.getByRole('tab', { name: 'Arabic', exact: true }).click();
        await page.locator('input[id="data.name.ar"]').fill(`${cityArabicName} معدلة`);
        await saveChanges(page);

        const oldRegionCities = await page.request.get(`/api/v1/customer/regions/${firstRegion}/cities`);
        const newRegionCities = await page.request.get(`/api/v1/customer/regions/${secondRegion}/cities`);
        expect((await oldRegionCities.json()).data.some((item: { public_id: string }) => item.public_id === city.public_id)).toBeFalsy();
        expect((await newRegionCities.json()).data.some((item: { public_id: string }) => item.public_id === city.public_id)).toBeTruthy();

        await page.goto('/admin/cities?lang=en');
        row = page.locator('tr').filter({ hasText: `${cityEnglishName} edited` }).first();
        await row.getByRole('button', { name: /delete/i }).click();
        await confirmAction(page);
        await expect(page.locator('tr').filter({ hasText: `${cityEnglishName} edited` })).toHaveCount(0);
        const removedCities = await page.request.get(`/api/v1/customer/regions/${secondRegion}/cities`);
        expect((await removedCities.json()).data.some((item: { public_id: string }) => item.public_id === city.public_id)).toBeFalsy();
    });
});
