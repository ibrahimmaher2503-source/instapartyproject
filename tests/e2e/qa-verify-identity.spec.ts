import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test, type BrowserContext, type Locator, type Page } from '@playwright/test';

test.use({ trace: 'retain-on-failure' });

const runId = Date.now().toString();
const approvalNames = [
    `QA E2E Identity Queue ${runId}`,
    `QA E2E Identity Review ${runId}`,
    `QA E2E Identity Profiles ${runId}`,
    `QA E2E Identity Ineligible ${runId}`,
];
const customerEmail = `qa-identity-${runId}@example.test`;
const customerName = `QA Identity Customer ${runId}`;
const customerPassword = 'CustomerUat123!';
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

async function boundedStep<T>(label: string, timeout: number, action: () => Promise<T>): Promise<T> {
    console.log(`QA_IDENTITY_STEP_START ${label}`);
    try {
        const result = await test.step(label, action, { timeout });
        console.log(`QA_IDENTITY_STEP_END ${label}`);

        return result;
    } catch (error) {
        console.log(`QA_IDENTITY_STEP_FAILED ${label}`);
        throw error;
    }
}

function seedFixtures(): void {
    const names = approvalNames.map((name) => `'${name}'`).join(',');
    const execute = `$names=[${names}];
        $governorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->where('code','EG-CAI')->firstOrFail();
        $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('governorate_id',$governorate->id)->firstOrFail();
        foreach ($names as $index=>$businessName) {
            $user=App\\Modules\\Identity\\Domain\\Models\\User::factory()->phoneVerified()->asVendor()->create([
                'name'=>'QA E2E Approval Vendor',
                'email'=>'qa-identity-${runId}-'.$index.'@example.test',
            ]);
            $profile=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->pending()->create([
                'user_id'=>$user->id,
                'business_name'=>['en'=>$businessName,'ar'=>$businessName.' AR'],
                'address_line'=>['en'=>'QA address','ar'=>'عنوان اختبار'],
                'business_type'=>'individual',
                'national_id'=>'29901011234567',
                'primary_governorate_id'=>$governorate->id,
                'primary_city_id'=>$city->id,
                'bank_name'=>'QA Bank',
                'bank_account_holder'=>'QA E2E Approval Vendor',
                'bank_iban'=>'EG380019000500000000263180002',
            ]);
            if ($index < 3) {
                App\\Modules\\Identity\\Domain\\Models\\VendorBusinessHour::factory()->create(['vendor_profile_id'=>$profile->id]);
            }
            App\\Modules\\Identity\\Domain\\Models\\VendorCoverageArea::factory()->create([
                'vendor_profile_id'=>$profile->id,
                'city_id'=>$city->id,
            ]);
            foreach ([App\\Modules\\Identity\\Domain\\Enums\\DocumentType::NationalId, App\\Modules\\Identity\\Domain\\Enums\\DocumentType::IbanProof] as $type) {
                App\\Modules\\Identity\\Domain\\Models\\VendorDocument::factory()->approved()->create([
                    'vendor_profile_id'=>$profile->id,
                    'doc_type'=>$type,
                    'expires_at'=>today(),
                    'is_critical'=>true,
                ]);
            }
        }
        $admin=App\\Modules\\Identity\\Domain\\Models\\User::query()->where('email','uat-admin@example.test')->firstOrFail();
        foreach (['view_any_vendor::profile','view_vendor::profile','approve_vendor_profile'] as $permissionName) {
            $admin->givePermissionTo(Spatie\\Permission\\Models\\Permission::findOrCreate($permissionName,'web'));
        }`;

    runPhp(execute);
}

function cleanupFixtures(): void {
    const names = approvalNames.map((name) => `'${name}'`).join(',');
    const execute = `$profiles=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::query()->whereIn('business_name->en',[${names}])->get();
        foreach ($profiles as $profile) {
            $user=$profile->user;
            App\\Modules\\Identity\\Domain\\Models\\VendorComplianceEvent::query()->where('vendor_profile_id',$profile->id)->delete();
            $profile->documents()->delete();
            $profile->businessHours()->delete();
            $profile->coverageAreas()->delete();
            $profile->approvedProductTypes()->delete();
            $profile->forceDelete();
            $user?->tokens()->delete();
            $user?->forceDelete();
        }
        $customer=App\\Modules\\Identity\\Domain\\Models\\User::query()->where('email','${customerEmail}')->first();
        $customer?->tokens()->delete();
        $customer?->forceDelete();
        $admin=App\\Modules\\Identity\\Domain\\Models\\User::query()->where('email','uat-admin@example.test')->first();
        if ($admin !== null) {
            foreach (['view_any_vendor::profile','view_vendor::profile','approve_vendor_profile'] as $permissionName) {
                $permission=Spatie\\Permission\\Models\\Permission::query()->where('name',$permissionName)->where('guard_name','web')->first();
                if ($permission !== null) { $admin->revokePermissionTo($permission); }
            }
        }`;

    runPhp(execute);
}

async function confirmApproval(page: Page, trigger: Locator, surface: string): Promise<number> {
    const startedAt = performance.now();
    await trigger.click();

    const confirmationCopy = surface === 'queue-list'
        ? page.getByText('Are you sure you would like to do this?', { exact: true })
        : page.getByRole('heading', { name: 'Approve this vendor?', exact: true });
    const dialog = page.locator('.fi-modal-window:visible').filter({ has: confirmationCopy }).last();
    await expect(dialog).toBeVisible({ timeout: 15_000 });
    const confirm = dialog.getByRole('button', { name: /^(Confirm|تأكيد)$/i });
    await expect(confirm).toBeEnabled();
    const readyMs = Math.round(performance.now() - startedAt);
    console.log(`QA_IDENTITY_APPROVAL_MODAL_READY ${surface} ${readyMs}ms`);

    await confirm.click();
    const notification = page.locator('.fi-no').filter({ hasText: 'Vendor profile approved' }).last();
    await expect(notification).toBeVisible({ timeout: 15_000 });

    return readyMs;
}

async function assertApprovalState(name: string, approved: boolean): Promise<void> {
    const expectedClass = approved
        ? 'App\\Modules\\Identity\\Domain\\States\\VendorApprovalStatus\\ApprovedState'
        : 'App\\Modules\\Identity\\Domain\\States\\VendorApprovalStatus\\PendingState';
    const execute = `$profile=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::query()->where('business_name->en','${name}')->firstOrFail();
        if (!($profile->approval_status instanceof ${expectedClass})) { throw new RuntimeException('Unexpected approval status'); }
        if ($profile->approval_status instanceof App\\Modules\\Identity\\Domain\\States\\VendorApprovalStatus\\ApprovedState && $profile->approvedTypes()->whereNull('revoked_at')->exists()) { throw new RuntimeException('Profile approval granted an unrequested product type'); }`;
    runPhp(execute);
}

test.beforeAll(() => {
    seedFixtures();
});

test.afterAll(() => {
    cleanupFixtures();
});

test('approves eligible vendors through each admin UI path', async ({ page }) => {
    test.setTimeout(180_000);

    const measuredModalTimes: Record<string, number> = {};

    await test.step('approve from the pending vendor queue', async () => {
        const response = await page.goto('/admin/vendor-approval-queue');
        expect(response?.status()).toBe(200);
        const row = page.locator('tr').filter({ hasText: approvalNames[0] }).first();
        await expect(row).toBeVisible();
        measuredModalTimes.queue = await confirmApproval(page, row.getByRole('button', { name: /^approve$/i }), 'queue-list');
        await assertApprovalState(approvalNames[0], true);

        const profilesResponse = await page.goto('/admin/vendor-profiles');
        expect(profilesResponse?.status()).toBe(200);
        const approvedRow = page.locator('tr').filter({ hasText: approvalNames[0] }).first();
        await expect(approvedRow).toContainText(/approved/i);
    });

    await test.step('approve from the review detail page', async () => {
        await page.goto('/admin/vendor-approval-queue');
        const row = page.locator('tr').filter({ hasText: approvalNames[1] }).first();
        await expect(row).toBeVisible();
        await row.getByText(approvalNames[1], { exact: true }).click();
        await expect(page).toHaveURL(/\/admin\/vendor-approval-queue\/[^/]+$/);
        measuredModalTimes.review = await confirmApproval(page, page.getByRole('button', { name: /approve profile/i }), 'review-detail');
        await expect(page).toHaveURL(/\/admin\/vendor-approval-queue\/?$/);
        await assertApprovalState(approvalNames[1], true);

        await page.goto('/admin/vendor-profiles');
        await expect(page.locator('tr').filter({ hasText: approvalNames[1] }).first()).toContainText(/approved/i);
    });

    await test.step('approve from the vendor profiles list', async () => {
        await page.goto('/admin/vendor-profiles');
        const row = page.locator('tr').filter({ hasText: approvalNames[2] }).first();
        await expect(row).toBeVisible();
        measuredModalTimes.profiles = await confirmApproval(page, row.getByRole('button', { name: /^approve$/i }), 'vendor-profiles-list');
        await assertApprovalState(approvalNames[2], true);

        await page.reload();
        await expect(page.locator('tr').filter({ hasText: approvalNames[2] }).first()).toContainText(/approved/i);
    });

    await test.step('show an actionable eligibility error without changing pending state', async () => {
        await page.goto('/admin/vendor-profiles');
        const row = page.locator('tr').filter({ hasText: approvalNames[3] }).first();
        await expect(row).toBeVisible();
        await row.getByRole('button', { name: /^approve$/i }).click();
        const dialog = page.locator('.fi-modal-window:visible')
            .filter({ has: page.getByRole('heading', { name: 'Approve this vendor?', exact: true }) })
            .last();
        await expect(dialog).toBeVisible({ timeout: 15_000 });
        await dialog.getByRole('button', { name: /^(Confirm|تأكيد)$/i }).click();
        const failure = page.locator('.fi-no').filter({ hasText: 'Vendor approval could not be completed' }).last();
        await expect(failure).toContainText('At least one business-hours entry is required.');
        await assertApprovalState(approvalNames[3], false);
    });

    console.log(`QA_IDENTITY_APPROVAL_MODAL_TIMINGS ${JSON.stringify(measuredModalTimes)}`);
});

test('registers and activates a customer with the local OTP stub', async ({ browser }) => {
    test.setTimeout(180_000);

    let customerContext: BrowserContext | undefined;

    try {
        const context = await boundedStep('create isolated customer context', 10_000, () => browser.newContext({
            baseURL: 'http://127.0.0.1:8139',
            locale: 'en-GB',
            timezoneId: 'Africa/Cairo',
            storageState: { cookies: [], origins: [] },
        }));
        customerContext = context;
        const customerPage = await boundedStep('open registration page', 15_000, () => context.newPage());
        const registrationResponse = await boundedStep('load registration form', 20_000, () => customerPage.goto('/en/auth/register'));
        expect(registrationResponse?.status()).toBe(200);
        await boundedStep('confirm guest registration page', 10_000, () => expect(customerPage).toHaveURL(/\/en\/auth\/register$/));

        await boundedStep('fill customer name', 10_000, () => customerPage.locator('input[name="name"]').fill(customerName));
        await boundedStep('fill customer phone', 10_000, () => customerPage.locator('input[name="phone_e164"]').fill(`+2010${runId.slice(-8)}`));
        await boundedStep('fill customer email', 10_000, () => customerPage.locator('input[name="email"]').fill(customerEmail));
        await boundedStep('fill customer password fields', 10_000, async () => {
            await customerPage.locator('input[name="password"]').fill(customerPassword);
            await customerPage.locator('input[name="password_confirmation"]').fill(customerPassword);
        });
        await boundedStep('accept registration terms', 10_000, () => customerPage.locator('input[name="accepted_terms"]').check());
        await boundedStep('submit registration form', 20_000, () => customerPage.locator('form[action*="/auth/register"] button[type="submit"]').click());
        await boundedStep('reach phone verification page', 15_000, () => expect(customerPage).toHaveURL(/\/en\/auth\/verify$/));

        await boundedStep('request local verification code', 15_000, () => customerPage.getByRole('button', { name: /resend/i }).click());
        const otpDigits = customerPage.locator('[data-otp-digit]');
        await boundedStep('show six verification fields', 15_000, () => expect(otpDigits).toHaveCount(6));
        await boundedStep('enter local verification code', 15_000, async () => {
            for (const [index, digit] of [...'000000'].entries()) {
                await otpDigits.nth(index).fill(digit);
            }
        });
        await boundedStep('submit verification code', 20_000, () => customerPage.getByRole('button', { name: /verify|confirm/i }).click());
        await boundedStep('reach activated storefront', 15_000, () => expect(customerPage).toHaveURL(/\/en\/?$/));

        const accountResponse = await boundedStep('open customer account page', 20_000, () => customerPage.goto('/en/account/profile'));
        expect(accountResponse?.status()).toBe(200);
        await boundedStep('confirm customer account identity', 10_000, () => expect(customerPage.locator('input[name="name"]')).toHaveValue(customerName));

        const apiLogin = await boundedStep('complete API login', 20_000, () => customerPage.evaluate(async ({ email, password }) => {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
            const login = await fetch('/api/v1/login', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                body: JSON.stringify({ login: email, password, device_name: 'qa-identity-e2e' }),
            });
            const payload = await login.json() as { data?: { token?: string } };

            return { status: login.status, tokenPresent: Boolean(payload.data?.token), csrfHeaderAvailable: Boolean(csrfToken) };
        }, { email: customerEmail, password: customerPassword }));
        expect(apiLogin).toEqual({ status: 200, tokenPresent: true, csrfHeaderAvailable: true });
    } finally {
        await customerContext?.close();
    }

    test.info().annotations.push({
        type: 'Provider boundary',
        description: 'The browser registration used the local testing OTP stub. It does not prove SMS provider delivery or production activation.',
    });
});
