import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

function latestVendorVerificationUrl(email: string): string | null {
    const logPath = path.resolve('storage/logs/laravel.log');
    const contents = readFileSync(logPath, 'utf8');
    const emailIndex = contents.lastIndexOf(email);

    if (emailIndex < 0) {
        return null;
    }

    const mailBlock = contents.slice(emailIndex, emailIndex + 30_000);
    const match = mailBlock.match(/https?:\/\/[^\s<>"']+\/vendor-portal\/email-verification\/verify\/[^\s<>"']+/);

    return match?.[0]?.replace(/[),.;]+$/, '') ?? null;
}

async function waitForVendorVerificationUrl(email: string): Promise<string | null> {
    const deadline = Date.now() + 15_000;

    while (Date.now() < deadline) {
        const url = latestVendorVerificationUrl(email);

        if (url !== null) {
            return url;
        }

        await new Promise((resolve) => setTimeout(resolve, 250));
    }

    return latestVendorVerificationUrl(email);
}

test('full marketplace journey records the real browser gates', async ({ browser, page: adminPage }) => {
    test.setTimeout(180_000);

    const vendorContext = await browser.newContext();
    const customerContext = await browser.newContext();
    const vendorPage = await vendorContext.newPage();
    const customerPage = await customerContext.newPage();
    const runId = Date.now().toString(36);
    const vendorEmail = `vendor-${runId}@example.test`;
    const vendorPassword = 'VendorUat123!';
    const vendorBusinessName = `Marketplace Business ${runId}`;
    const customerEmail = `customer-${runId}@example.test`;
    const customerPassword = 'CustomerUat123!';
    let servicePublicId: string | null = null;
    let vendorToken: string | null = null;
    let bookingPublicId: string | null = null;
    let bookingVendorPublicId: string | null = null;
    const uploadResponseDiagnostics: Array<{
        status: number;
        responseKeys: string[];
        hasErrors: boolean;
        errorKeys: string[];
    }> = [];
    const uploadResponseReads: Promise<void>[] = [];

    vendorPage.on('response', (response) => {
        const requestBody = response.request().postData();

        if (requestBody === null || !requestBody.includes('_finishUpload')) {
            return;
        }

        const readResponse = (async () => {
            let responseKeys: string[] = [];
            let hasErrors = false;
            let errorKeys: string[] = [];

            try {
                const payload = await response.json();

                if (payload !== null && typeof payload === 'object' && !Array.isArray(payload)) {
                    responseKeys = Object.keys(payload).sort();
                    hasErrors = payload.errors !== undefined && payload.errors !== null;
                    errorKeys = payload.errors !== null && typeof payload.errors === 'object'
                        ? Object.keys(payload.errors).sort()
                        : [];
                }
            } catch {
                // Keep diagnostics limited to status and JSON shape when a response is not JSON.
            }

            uploadResponseDiagnostics.push({
                status: response.status(),
                responseKeys,
                hasErrors,
                errorKeys,
            });
        })();

        uploadResponseReads.push(readResponse);
    });

    await test.step('register a new vendor in the real browser', async () => {
        const response = await vendorPage.goto('/vendor-portal/register');
        expect(response?.status()).toBe(200);

        await vendorPage.locator('input[wire\\:model="data.name"]').fill(`Marketplace Vendor ${runId}`);
        await vendorPage.locator('input[wire\\:model="data.email"]').fill(vendorEmail);
        await vendorPage.locator('input[wire\\:model="data.phone_e164"]').fill(`+2010${Date.now().toString().slice(-8)}`);
        await vendorPage.locator('input[wire\\:model="data.password"]').fill(vendorPassword);
        await vendorPage.locator('input[wire\\:model="data.passwordConfirmation"]').fill(vendorPassword);
        await vendorPage.locator('input[wire\\:model="data.business_name_en"]').fill(vendorBusinessName);
        await vendorPage.locator('input[wire\\:model="data.business_name_ar"]').fill(`شركة اختبار ${runId}`);

        const selects = vendorPage.locator('select');
        await selects.nth(0).selectOption('individual');
        const cairoGovernorate = selects.nth(1).locator('option').filter({ hasText: /Cairo|القاهرة/i }).first();
        await expect(cairoGovernorate).toBeAttached({ timeout: 15_000 });
        const cairoGovernorateValue = await cairoGovernorate.getAttribute('value');
        expect(cairoGovernorateValue).toBeTruthy();
        await Promise.all([
            vendorPage.waitForResponse((networkResponse) => networkResponse.request().method() === 'POST'
                && networkResponse.url().includes('/livewire/update')),
            selects.nth(1).selectOption(cairoGovernorateValue!),
        ]);
        const nasrCity = selects.nth(2).locator('option').filter({ hasText: /Nasr City|مدينة نصر/i }).first();
        await expect(nasrCity).toBeAttached({ timeout: 15_000 });
        const nasrCityValue = await nasrCity.getAttribute('value');
        expect(nasrCityValue).toBeTruthy();
        await selects.nth(2).selectOption(nasrCityValue!);
        const signUpButton = vendorPage.getByRole('button', { name: /create account|sign up|إنشاء حساب/i });
        await expect(signUpButton).toBeEnabled({ timeout: 15_000 });
        await Promise.all([
            vendorPage.waitForResponse((networkResponse) => networkResponse.request().method() === 'POST'
                && networkResponse.url().includes('/livewire/update')),
            signUpButton.click(),
        ]);

        await expect(vendorPage).toHaveURL(/\/vendor-portal\/email-verification\/prompt$/, { timeout: 15_000 });
        await expect(vendorPage.getByText(/Registration submitted!|تم تقديم طلب التسجيل!/i)).toBeVisible();

        const verificationUrl = await waitForVendorVerificationUrl(vendorEmail);
        console.log('VENDOR_EMAIL_CAPTURE_RESULT', Boolean(verificationUrl));
        expect(verificationUrl).toBeTruthy();

        const parsedVerificationUrl = new URL(verificationUrl!);
        const browserOrigin = new URL(vendorPage.url()).origin;
        const verificationTarget = `${browserOrigin}${parsedVerificationUrl.pathname}${parsedVerificationUrl.search}`;
        const verificationResponse = await vendorPage.goto(verificationTarget);
        expect(verificationResponse?.status()).toBe(200);
        await expect(vendorPage).not.toHaveURL(/email-verification\/prompt$/);
    });

    await test.step('verify the same vendor phone in the real browser', async () => {
        const response = await vendorPage.goto('/vendor-portal/vendor-phone-verification-page');
        expect(response?.status()).toBe(200);
        await expect(vendorPage.locator('body')).toContainText(/phone|هاتف/i);

        await vendorPage.getByRole('button', { name: /send verification code|إرسال رمز التحقق/i }).click();
        const code = vendorPage.locator('input[wire\\:model="data.code"]');
        await expect(code).toBeVisible();
        await code.fill('000000');
        await vendorPage.getByRole('button', { name: /verify phone number|تحقق من رقم الهاتف/i }).click();
        await expect(vendorPage.locator('body')).toContainText(/verified|تم التحقق/i);
    });

    await test.step('complete the same vendor profile and banking form', async () => {
        const response = await vendorPage.goto('/vendor-portal/vendor-profile-page');
        expect(response?.status()).toBe(200);
        const profileResponseDiagnostics: Array<Record<string, unknown>> = [];
        const profileResponseReads: Promise<void>[] = [];
        const profileRequestDiagnostics: Array<Record<string, unknown>> = [];
        const profileTrackedPaths = [
            'data.address_line_en',
            'data.address_line_ar',
            'data.national_id',
            'data.bank_name',
            'data.bank_account_holder',
            'data.bank_iban',
            'data.bank_swift',
        ];
        let profileRequestSequence = 0;
        let profilePendingRequests = 0;
        let profileStateAfterFields: Record<string, unknown> = {};
        let profileStateBeforeSave: Record<string, unknown> = {};
        let profileStateAfterSave: Record<string, unknown> = {};
        let profileStateAfterReload: Record<string, unknown> = {};
        const profileRequestEntries = new Map<import('@playwright/test').Request, Record<string, unknown>>();
        const profileLivewireTimeline: Array<Record<string, unknown>> = [];
        const profileTimelineReads: Promise<void>[] = [];
        const resultsDir = path.resolve('tests/e2e/.results');
        const profileTimelineArtifact = path.join(resultsDir, 'profile-livewire-timeline.json');
        const describeValue = (value: unknown): Record<string, unknown> => {
            const type = Array.isArray(value)
                ? 'array'
                : value === null
                    ? 'null'
                    : typeof value;
            const nonEmpty = value !== null
                && value !== undefined
                && (typeof value === 'string'
                    ? value.trim().length > 0
                    : Array.isArray(value)
                        ? value.length > 0
                        : typeof value === 'object'
                            ? Object.keys(value as Record<string, unknown>).length > 0
                            : true);

            return {
                type,
                nonEmpty,
                length: typeof value === 'string' || Array.isArray(value) ? value.length : null,
            };
        };
        const decodeSnapshotValue = (value: unknown): unknown => {
            if (Array.isArray(value) && value.length > 0) {
                return value[0];
            }

            return value;
        };
        const describeSnapshotData = (snapshot: unknown): Record<string, unknown> | null => {
            if (typeof snapshot !== 'string') {
                return null;
            }

            try {
                const decoded = JSON.parse(snapshot) as Record<string, unknown>;
                const data = decoded.data;

                if (data === null || typeof data !== 'object' || Array.isArray(data)) {
                    return null;
                }

                return Object.fromEntries(Object.entries(data as Record<string, unknown>)
                    .sort(([left], [right]) => left.localeCompare(right))
                    .map(([key, value]) => [key, describeValue(decodeSnapshotValue(value))]));
            } catch {
                return null;
            }
        };
        const describeParams = (params: unknown): Record<string, unknown> | Record<string, unknown>[] => {
            if (Array.isArray(params)) {
                return {
                    type: 'array',
                    length: params.length,
                    items: params.map((param) => describeValue(param)),
                };
            }

            if (params !== null && typeof params === 'object') {
                return {
                    type: 'object',
                    keys: Object.keys(params as Record<string, unknown>).sort(),
                    valueTypes: Object.fromEntries(Object.entries(params as Record<string, unknown>)
                        .sort(([left], [right]) => left.localeCompare(right))
                        .map(([key, value]) => [key, describeValue(value)])),
                };
            }

            return describeValue(params);
        };
        const profileRequestMatches = (request: import('@playwright/test').Request): boolean =>
            request.method() === 'POST' && request.url().includes('/livewire/update');
        const requestHasUpdate = (request: import('@playwright/test').Request, path: string): boolean => {
            if (!profileRequestMatches(request)) {
                return false;
            }

            try {
                const postData = request.postData();
                const payload = postData === null ? null : JSON.parse(postData) as Record<string, unknown>;
                const components = payload?.components;

                return Array.isArray(components) && components.some((component) => {
                    if (component === null || typeof component !== 'object') {
                        return false;
                    }

                    const updates = (component as Record<string, unknown>).updates;

                    return updates !== null
                        && typeof updates === 'object'
                        && !Array.isArray(updates)
                        && Object.prototype.hasOwnProperty.call(updates, path);
                });
            } catch {
                return false;
            }
        };
        const requestHasCall = (request: import('@playwright/test').Request, method: string): boolean => {
            if (!profileRequestMatches(request)) {
                return false;
            }

            try {
                const postData = request.postData();
                const payload = postData === null ? null : JSON.parse(postData) as Record<string, unknown>;
                const components = payload?.components;

                return Array.isArray(components) && components.some((component) => {
                    if (component === null || typeof component !== 'object') {
                        return false;
                    }

                    const calls = (component as Record<string, unknown>).calls;

                    return Array.isArray(calls) && calls.some((call) => call !== null
                        && typeof call === 'object'
                        && (call as Record<string, unknown>).method === method);
                });
            } catch {
                return false;
            }
        };
        const profileRequestHandler = (request: import('@playwright/test').Request): void => {
            if (!profileRequestMatches(request)) {
                return;
            }

            profilePendingRequests += 1;
            const sequence = ++profileRequestSequence;
            const summary: Record<string, unknown> = {
                sequence,
                requestedAt: new Date().toISOString(),
                updates: [],
                calls: [],
                hasSnapshot: false,
            };
            const postData = request.postData();

            try {
                const payload = postData === null ? null : JSON.parse(postData) as Record<string, unknown>;
                const components = payload?.components;

                if (Array.isArray(components)) {
                    summary.components = components.map((component) => {
                        if (component === null || typeof component !== 'object') {
                            return { updates: [], calls: [], hasSnapshot: false };
                        }

                        const componentRecord = component as Record<string, unknown>;
                        const updates = componentRecord.updates;
                        const calls = componentRecord.calls;
                        const updateEntries = updates !== null && typeof updates === 'object' && !Array.isArray(updates)
                            ? Object.entries(updates as Record<string, unknown>)
                                .sort(([left], [right]) => left.localeCompare(right))
                                .map(([path, value]) => ({ path, value: describeValue(value) }))
                            : [];
                        const callEntries = Array.isArray(calls)
                            ? calls.map((call) => {
                                const callRecord = call !== null && typeof call === 'object'
                                    ? call as Record<string, unknown>
                                    : {};

                                return {
                                    method: typeof callRecord.method === 'string' ? callRecord.method : null,
                                    params: describeParams(callRecord.params),
                                };
                            })
                            : [];

                        return {
                            updates: updateEntries,
                            calls: callEntries,
                            hasSnapshot: typeof componentRecord.snapshot === 'string',
                        };
                    });
                    summary.updates = (summary.components as Array<Record<string, unknown>>)
                        .flatMap((component) => component.updates as Array<Record<string, unknown>>);
                    summary.calls = (summary.components as Array<Record<string, unknown>>)
                        .flatMap((component) => component.calls as Array<Record<string, unknown>>);
                    summary.hasSnapshot = (summary.components as Array<Record<string, unknown>>)
                        .some((component) => component.hasSnapshot === true);
                }
            } catch {
                summary.parseError = true;
            }

            profileRequestDiagnostics.push(summary);
            profileRequestEntries.set(request, summary);
        };
        const profileRequestFailedHandler = (request: import('@playwright/test').Request): void => {
            if (profileRequestMatches(request)) {
                profilePendingRequests = Math.max(0, profilePendingRequests - 1);
            }
        };
        const profileResponseHandler = (networkResponse: import('@playwright/test').Response): void => {
            const request = networkResponse.request();

            if (!profileRequestMatches(request)) {
                return;
            }

            profilePendingRequests = Math.max(0, profilePendingRequests - 1);
            const requestEntry = profileRequestEntries.get(request);

            profileTimelineReads.push((async (): Promise<void> => {
                const stateBeforeResponse = await inspectProfileState();

                try {
                    await networkResponse.body();
                } catch {
                    // Retain the sanitized state snapshot when response body reading fails.
                }

                await new Promise((resolve) => setTimeout(resolve, 0));
                const stateAfterResponse = await inspectProfileState();
                const timelineEntry = {
                    sequence: requestEntry?.sequence ?? null,
                    requestedAt: requestEntry?.requestedAt ?? null,
                    responseAt: new Date().toISOString(),
                    status: networkResponse.status(),
                    updatePaths: ((requestEntry?.updates as Array<Record<string, unknown>> | undefined) ?? [])
                        .map((update) => update.path)
                        .filter((pathValue): pathValue is string => typeof pathValue === 'string')
                        .sort(),
                    callMethods: ((requestEntry?.calls as Array<Record<string, unknown>> | undefined) ?? [])
                        .map((call) => call.method)
                        .filter((method): method is string => typeof method === 'string'),
                    stateBeforeResponse,
                    stateAfterResponse,
                };
                profileLivewireTimeline.push(timelineEntry);
                mkdirSync(resultsDir, { recursive: true });
                writeFileSync(profileTimelineArtifact, JSON.stringify({
                    events: profileLivewireTimeline,
                }, null, 2));
                console.log('PROFILE_LIVEWIRE_TIMELINE', JSON.stringify(timelineEntry));
            })());

            profileResponseReads.push((async (): Promise<void> => {
                let responseKeys: string[] = [];
                let errorKeys: string[] = [];
                let componentDiagnostics: Array<Record<string, unknown>> = [];

                try {
                    const payload = await networkResponse.json();

                    if (payload !== null && typeof payload === 'object') {
                        responseKeys = Object.keys(payload).sort();
                        const errors = (payload as Record<string, unknown>).errors;

                        if (errors !== null && typeof errors === 'object') {
                            errorKeys = Object.keys(errors as Record<string, unknown>).sort();
                        }

                        const components = (payload as Record<string, unknown>).components;

                        if (Array.isArray(components)) {
                            componentDiagnostics = components.map((component) => {
                                if (component === null || typeof component !== 'object') {
                                    return { effectsKeys: [], snapshotDataShape: null };
                                }

                                const componentRecord = component as Record<string, unknown>;
                                const effects = componentRecord.effects;

                                return {
                                    effectsKeys: effects !== null && typeof effects === 'object'
                                        ? Object.keys(effects as Record<string, unknown>).sort()
                                        : [],
                                    snapshotDataShape: describeSnapshotData(componentRecord.snapshot),
                                };
                            });
                        }
                    }
                } catch {
                    // Ignore non-JSON Livewire responses while retaining status only.
                }

                profileResponseDiagnostics.push({
                    status: networkResponse.status(),
                    responseKeys,
                    errorKeys,
                    hasErrors: errorKeys.length > 0,
                    componentDiagnostics,
                });
            })());
        };
        vendorPage.on('request', profileRequestHandler);
        vendorPage.on('requestfailed', profileRequestFailedHandler);
        vendorPage.on('response', profileResponseHandler);

        const inspectProfileState = async (): Promise<Record<string, unknown>> => vendorPage.evaluate(async () => {
            const fieldIds = [
                'data.business_name_en',
                'data.business_name_ar',
                'data.address_line_en',
                'data.address_line_ar',
                'data.national_id',
                'data.bank_name',
                'data.bank_account_holder',
                'data.bank_iban',
                'data.bank_swift',
                'data.primary_governorate_id',
                'data.primary_city_id',
            ];
            const relevantKeys = [
                'business_name_en',
                'business_name_ar',
                'address_line_en',
                'address_line_ar',
                'national_id',
                'bank_name',
                'bank_account_holder',
                'bank_iban',
                'bank_swift',
                'primary_governorate_id',
                'primary_city_id',
            ];
            const hasValue = (value: unknown): boolean => {
                if (value === null || value === undefined) {
                    return false;
                }

                if (typeof value === 'string') {
                    return value.trim().length > 0;
                }

                if (Array.isArray(value)) {
                    return value.length > 0;
                }

                if (typeof value === 'object') {
                    return Object.keys(value as Record<string, unknown>).length > 0;
                }

                return true;
            };
            const valueType = (value: unknown): string => Array.isArray(value)
                ? 'array'
                : value === null
                    ? 'null'
                    : typeof value;
            const inputs = Object.fromEntries(fieldIds.map((id) => {
                const input = document.querySelector(`[id="${id}"]`) as HTMLInputElement | HTMLSelectElement | null;
                const value = input?.value ?? '';

                return [id, {
                    present: input !== null,
                    type: input?.type ?? null,
                    nonEmpty: value.trim().length > 0,
                    length: value.length,
                    masked: /[•*]{3,}/.test(value),
                }];
            }));
            const selects = Object.fromEntries(['data.primary_governorate_id', 'data.primary_city_id'].map((id) => {
                const select = document.querySelector(`[id="${id}"]`) as HTMLSelectElement | null;
                const option = select?.selectedOptions?.[0];

                return [id, {
                    selected: select?.value?.length > 0,
                    optionTextPresent: (option?.textContent?.trim().length ?? 0) > 0,
                }];
            }));
            const activeTabs = Array.from(document.querySelectorAll('[role="tab"][aria-selected="true"]'))
                .map((element) => element.textContent?.trim() ?? '')
                .filter(Boolean);
            const livewire = (window as any).Livewire;
            const components = typeof livewire?.all === 'function' ? livewire.all() : [];
            let data: Record<string, unknown> = {};

            for (const component of components) {
                try {
                    const candidate = await Promise.resolve(component?.$wire?.get?.('data'));

                    if (candidate !== null && typeof candidate === 'object') {
                        data = candidate as Record<string, unknown>;
                        break;
                    }
                } catch {
                    // Continue to the next component when a non-form component has no data state.
                }
            }

            const livewireData = Object.fromEntries(Object.keys(data).sort().map((key) => [key, {
                type: valueType(data[key]),
                nonEmpty: hasValue(data[key]),
            }]));
            const relevantLivewireData = Object.fromEntries(relevantKeys.map((key) => [key, livewireData[key] ?? {
                type: 'missing',
                nonEmpty: false,
            }]));

            return {
                inputs,
                selects,
                activeTabs,
                livewireKeys: Object.keys(data).sort(),
                livewireData: relevantLivewireData,
                loadingMarkerCount: document.querySelectorAll('[wire\\:loading]').length,
            };
        });
        const inspectCityGate = async (): Promise<Record<string, boolean>> => vendorPage.evaluate(async () => {
            const city = document.querySelector('[id="data.primary_city_id"]') as HTMLSelectElement | null;
            const livewire = (window as any).Livewire;
            const components = typeof livewire?.all === 'function' ? livewire.all() : [];
            let data: Record<string, unknown> = {};

            for (const component of components) {
                try {
                    const candidate = await Promise.resolve(component?.$wire?.get?.('data'));

                    if (candidate !== null && typeof candidate === 'object') {
                        data = candidate as Record<string, unknown>;
                        break;
                    }
                } catch {
                    // Continue to the next component when no form state is available.
                }
            }

            const cityValue = data.primary_city_id;

            return {
                domCityNonEmpty: (city?.value?.trim().length ?? 0) > 0,
                wireCityNonEmpty: cityValue !== null
                    && cityValue !== undefined
                    && String(cityValue).trim().length > 0,
            };
        });

        const governorate = vendorPage.locator('select[id="data.primary_governorate_id"]');
        const cairoOption = governorate.locator('option').filter({ hasText: /Cairo|القاهرة/i }).first();
        await expect(cairoOption).toBeAttached({ timeout: 15_000 });
        const cairoValue = await cairoOption.getAttribute('value');
        expect(cairoValue).toBeTruthy();
        await Promise.all([
            vendorPage.waitForResponse((networkResponse) => requestHasUpdate(
                networkResponse.request(),
                'data.primary_governorate_id',
            )),
            governorate.selectOption(cairoValue!),
        ]);
        const city = vendorPage.locator('select[id="data.primary_city_id"]');
        const nasrCityOption = city.locator('option').filter({ hasText: /Nasr City|مدينة نصر/i }).first();
        await expect(nasrCityOption).toBeAttached({ timeout: 15_000 });
        const nasrCityValue = await nasrCityOption.getAttribute('value');
        expect(nasrCityValue).toBeTruthy();
        await city.selectOption(nasrCityValue!);
        await expect(city).toHaveValue(nasrCityValue!);

        // Governorate is live and re-renders the dependent city field. Fill every
        // deferred profile field only after that render has settled, so the live
        // update cannot discard the identity and banking inputs before submit.
        await vendorPage.locator('[id="data.business_name_en"]').fill(vendorBusinessName);
        await vendorPage.locator('[id="data.address_line_en"]').fill('10 Marketplace Street');
        const arabicTab = vendorPage.locator('form').getByRole('tablist', { name: 'translations', exact: true }).getByRole('tab', { name: 'العربية', exact: true });
        await arabicTab.click();
        await vendorPage.locator('[id="data.business_name_ar"]').fill(`شركة ${runId}`);
        await vendorPage.locator('[id="data.address_line_ar"]').fill('10 شارع السوق');
        await vendorPage.locator('[id="data.national_id"]').fill('29801011234567');
        await vendorPage.locator('[id="data.bank_name"]').fill('E2E Test Bank');
        await vendorPage.locator('[id="data.bank_account_holder"]').fill(`Marketplace Vendor ${runId}`);
        await vendorPage.locator('[id="data.bank_iban"]').fill('EG380019000500000000263180002');
        await vendorPage.locator('[id="data.bank_swift"]').fill('TESTEGCA');
        await vendorPage.locator('[id="data.bank_swift"]').press('Tab');
        profileStateAfterFields = await inspectProfileState();
        console.log('PROFILE_STATE_AFTER_FIELDS', JSON.stringify(profileStateAfterFields));
        await vendorPage.waitForLoadState('networkidle', { timeout: 10_000 }).catch(() => undefined);
        await expect.poll(() => profilePendingRequests, { timeout: 15_000 }).toBe(0);
        profileStateBeforeSave = await inspectProfileState();
        console.log('PROFILE_STATE_BEFORE_SAVE', JSON.stringify(profileStateBeforeSave));
        let cityGate = await inspectCityGate();

        if (!cityGate.domCityNonEmpty || !cityGate.wireCityNonEmpty) {
            await city.selectOption(nasrCityValue!);
            await expect(city).toHaveValue(nasrCityValue!);
            await city.press('Tab');
            await expect.poll(async () => (await inspectCityGate()).wireCityNonEmpty, { timeout: 5_000 }).toBe(true);
            cityGate = await inspectCityGate();
        }

        console.log('PROFILE_CITY_GATE', JSON.stringify(cityGate));
        expect(cityGate.domCityNonEmpty).toBe(true);
        expect(cityGate.wireCityNonEmpty).toBe(true);

        const profileForm = vendorPage.locator('form.fi-form[wire\\:submit="save"]');
        await expect(profileForm).toHaveCount(1);
        const nativeValidation = await profileForm.evaluate((form) => {
            const invalidFields = Array.from(form.querySelectorAll('input, select, textarea'))
                .filter((field) => field instanceof HTMLInputElement
                    || field instanceof HTMLSelectElement
                    || field instanceof HTMLTextAreaElement)
                .filter((field) => !(field as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement).checkValidity())
                .map((field) => ({
                    name: field.getAttribute('name'),
                    id: field.getAttribute('id'),
                    validationMessage: (field as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement).validationMessage,
                }));

            return { valid: invalidFields.length === 0, invalidFields };
        });
        console.log('PROFILE_NATIVE_VALIDATION', JSON.stringify(nativeValidation));
        expect(nativeValidation.valid).toBe(true);

        const saveButton = profileForm.locator('button[type="submit"]');
        await expect(saveButton).toHaveCount(1);
        await expect(saveButton).toBeEnabled();
        const saveRequestPromise = vendorPage.waitForRequest((request) => requestHasCall(request, 'save'));
        const saveResponsePromise = vendorPage.waitForResponse((networkResponse) =>
            requestHasCall(networkResponse.request(), 'save'));
        await saveButton.click();
        await saveRequestPromise;
        const saveResponse = await saveResponsePromise;
        console.log('PROFILE_SAVE_CALL_OBSERVED', JSON.stringify({ status: saveResponse.status() }));
        expect(saveResponse.status()).toBe(200);
        await expect(vendorPage.locator('body')).toContainText(/Profile saved|تم حفظ الملف الشخصي/i, { timeout: 10_000 });
        await expect(vendorPage.locator('[id="data.business_name_en"]')).toHaveValue(vendorBusinessName);
        await vendorPage.waitForLoadState('networkidle', { timeout: 10_000 }).catch(() => undefined);
        await Promise.allSettled(profileResponseReads);
        await Promise.allSettled(profileTimelineReads);
        console.log('PROFILE_SAVE_RESPONSES', JSON.stringify(profileResponseDiagnostics));
        await expect.poll(() => profilePendingRequests, { timeout: 15_000 }).toBe(0);
        profileStateAfterSave = await inspectProfileState();
        console.log('PROFILE_STATE_AFTER_SAVE', JSON.stringify(profileStateAfterSave));
        await vendorPage.goto('/vendor-portal/vendor-profile-page');
        await vendorPage.waitForLoadState('networkidle', { timeout: 10_000 }).catch(() => undefined);
        profileStateAfterReload = await inspectProfileState();
        console.log('PROFILE_STATE_AFTER_RELOAD', JSON.stringify(profileStateAfterReload));
        const saveRequests = profileRequestDiagnostics
            .filter((request) => (request.calls as Array<Record<string, unknown>> | undefined)
                ?.some((call) => typeof call.method === 'string' && /save|submit|update/i.test(call.method)))
            .map((request) => ({ sequence: request.sequence, calls: request.calls }));
        const saveSequence = saveRequests[0]?.sequence;
        const fieldUpdateOrder = Object.fromEntries(profileTrackedPaths.map((trackedPath) => {
            const sequences = profileRequestDiagnostics
                .filter((request) => (request.updates as Array<Record<string, unknown>> | undefined)
                    ?.some((update) => update.path === trackedPath))
                .map((request) => request.sequence);

            return [trackedPath, {
                sequences,
                beforeOrSameSave: typeof saveSequence === 'number'
                    ? sequences.filter((sequence) => sequence <= saveSequence)
                    : [],
            }];
        }));
        const profileDiagnosticArtifact = path.join(resultsDir, 'profile-persistence-diagnostic.json');
        mkdirSync(resultsDir, { recursive: true });
        writeFileSync(profileDiagnosticArtifact, JSON.stringify({
            requestDiagnostics: profileRequestDiagnostics,
            responseDiagnostics: profileResponseDiagnostics,
            saveRequests,
            fieldUpdateOrder,
            states: {
                afterFields: profileStateAfterFields,
                beforeSave: profileStateBeforeSave,
                afterSave: profileStateAfterSave,
                afterReload: profileStateAfterReload,
            },
        }, null, 2));
        console.log('PROFILE_DIAGNOSTIC_ARTIFACT', profileDiagnosticArtifact);
        vendorPage.off('request', profileRequestHandler);
        vendorPage.off('requestfailed', profileRequestFailedHandler);
        vendorPage.off('response', profileResponseHandler);
    });

    if (process.env.E2E_PROFILE_DIAGNOSTIC_ONLY === '1') {
        await test.step('inspect the same vendor approval checklist after profile save', async () => {
            const response = await adminPage.goto('/admin/vendor-approval-queue');
            expect(response?.status()).toBe(200);

            if (/\/admin\/login$/.test(adminPage.url())) {
                await adminPage.locator('input[wire\\:model="data.email"]').fill('uat-admin@example.test');
                await adminPage.locator('input[wire\\:model="data.password"]').fill('UatTest123!');
                await adminPage.getByRole('button', { name: /sign in/i }).click();
                await expect(adminPage).toHaveURL(/\/admin\/vendor-approval-queue$/, { timeout: 15_000 });
            }

            const vendorEntry = adminPage.getByText(vendorBusinessName, { exact: true }).first();
            await expect(vendorEntry).toBeVisible();
            await vendorEntry.click();
            await expect(adminPage).toHaveURL(/\/admin\/vendor-approval-queue\/[^/]+$/);
            const checklistStatuses = await adminPage.evaluate(() => Array.from(document.querySelectorAll('body *'))
                .map((element) => element.textContent?.trim() ?? '')
                .filter((text) => /^(Complete|Incomplete|مكتمل|غير مكتمل)$/.test(text))
                .filter((text, index, values) => values.indexOf(text) === index));
            console.log('ADMIN_CHECKLIST_STATUSES', JSON.stringify(checklistStatuses));
        });

        return;
    }

    await test.step('upload the same vendor required documents through the real browser', async () => {
        const response = await vendorPage.goto('/vendor-portal/vendor-documents-page');
        expect(response?.status()).toBe(200);

        const fixturePath = path.resolve('test_national_id.pdf');

        for (const documentType of ['national_id', 'iban_proof']) {
            await vendorPage.getByRole('button', { name: /upload document|رفع مستند/i }).click();

            const modalWindow = vendorPage.locator('.fi-modal-window:visible')
                .filter({ has: vendorPage.getByRole('heading', { name: /^(Upload Document|رفع مستند)$/i }) })
                .last();
            await expect(modalWindow).toBeVisible();
            await modalWindow.getByRole('combobox', { name: /document type|نوع المستند/i }).selectOption(documentType);
            const fileInput = modalWindow.locator('input.filepond--browser[type="file"]');
            await expect(fileInput).toHaveCount(1);
            const finishUploadResponsePromise = vendorPage.waitForResponse((networkResponse) => {
                const requestBody = networkResponse.request().postData();

                return networkResponse.request().method() === 'POST'
                    && requestBody !== null
                    && requestBody.includes('_finishUpload');
            }, { timeout: 30_000 });
            await fileInput.setInputFiles(fixturePath);

            // Submit only after Livewire has completed FilePond's _finishUpload
            // request and committed the temporary child UUID into mounted action
            // state. Filament's visible completion text is not stable.
            try {
                await Promise.race([
                    finishUploadResponsePromise.then((finishUploadResponse) => {
                        expect(finishUploadResponse.status()).toBe(200);
                    }),
                    vendorPage.waitForFunction(() => {
                        const uploadDialog = Array.from(document.querySelectorAll('.fi-modal-window'))
                            .find((element) => Array.from(element.querySelectorAll('h1, h2, h3, h4, h5, h6, [role="heading"]'))
                                .some((heading) => ['Upload Document', 'رفع مستند'].includes(heading.textContent?.trim() ?? '')) &&
                                getComputedStyle(element).display !== 'none' &&
                                getComputedStyle(element).visibility !== 'hidden' &&
                                element.getClientRects().length > 0);

                        if (!uploadDialog) {
                            return false;
                        }

                        const livewire = (window as any).Livewire;
                        const components = typeof livewire?.all === 'function' ? livewire.all() : [];

                        return components.some((component: any) => {
                            let mountedActions: any;

                            try {
                                mountedActions = component?.$wire?.get?.('mountedActionsData');
                            } catch {
                                mountedActions = undefined;
                            }

                            const file = mountedActions?.[0]?.file;

                            return Array.isArray(file)
                                ? file.length > 0
                                : file !== null && file !== undefined && file !== '' &&
                                    (typeof file !== 'object' || Object.keys(file).length > 0);
                        });
                    }, undefined, { timeout: 20_000 }),
                ]);
            } catch (error) {
                await Promise.allSettled(uploadResponseReads);

                const uploadState = await vendorPage.evaluate(() => {
                    const uploadDialog = Array.from(document.querySelectorAll('.fi-modal-window'))
                        .find((element) => Array.from(element.querySelectorAll('h1, h2, h3, h4, h5, h6, [role="heading"]'))
                            .some((heading) => ['Upload Document', 'رفع مستند'].includes(heading.textContent?.trim() ?? '')) &&
                            getComputedStyle(element).display !== 'none' &&
                            getComputedStyle(element).visibility !== 'hidden' &&
                            element.getClientRects().length > 0);
                    const input = uploadDialog?.querySelector('input[type="file"]') as HTMLInputElement | null;
                    const pond = input === null
                        ? null
                        : (window as any).FilePond?.find?.(input);
                    const livewire = (window as any).Livewire;
                    const components = typeof livewire?.all === 'function' ? livewire.all() : [];
                    const mountedActionStates = components.map((component: any) => {
                        let mountedActions: any;

                        try {
                            mountedActions = component?.$wire?.get?.('mountedActionsData');
                        } catch {
                            mountedActions = undefined;
                        }

                        if (mountedActions === undefined) {
                            return null;
                        }

                        const firstAction = mountedActions?.[0];
                        const file = firstAction?.file;

                        return {
                            wireId: component?.id ?? null,
                            mountedType: Array.isArray(mountedActions) ? 'array' : typeof mountedActions,
                            mountedLength: Array.isArray(mountedActions) ? mountedActions.length : null,
                            actionKeys: firstAction !== null && typeof firstAction === 'object'
                                ? Object.keys(firstAction).sort()
                                : [],
                            fileType: Array.isArray(file) ? 'array' : typeof file,
                            fileLength: Array.isArray(file) ? file.length : null,
                            fileKeys: file !== null && typeof file === 'object' && !Array.isArray(file)
                                ? Object.keys(file).sort()
                                : [],
                        };
                    }).filter(Boolean);

                    return {
                        wireIds: components.map((component: any) => component?.id ?? null),
                        mountedActionStates,
                        fileInputCount: input?.files?.length ?? 0,
                        filePondFiles: typeof pond?.getFiles === 'function'
                            ? pond.getFiles().map((file: any) => ({
                                status: file.status,
                                hasServerId: Boolean(file.serverId),
                                sourceType: typeof file.source,
                            }))
                            : [],
                    };
                });

                console.log('UPLOAD_STATE_DIAGNOSTIC', JSON.stringify({
                    uploadState,
                    uploadResponses: uploadResponseDiagnostics,
                }));

                throw error;
            }
            await modalWindow.getByRole('button', { name: /^(Submit|إرسال)$/i }).click();
            await expect(modalWindow).toBeHidden({ timeout: 15_000 });
            const documentLabel = documentType === 'national_id'
                ? /National ID|بطاقة الهوية الوطنية/i
                : /IBAN Proof|إثبات رقم IBAN/i;
            const documentRow = vendorPage.locator('tr')
                .filter({ hasText: documentLabel })
                .filter({ hasText: 'test_national_id.pdf' })
                .first();
            await expect(documentRow).toBeVisible();
            await expect(documentRow).toContainText(/pending review|قيد المراجعة/i);
        }

        test.info().annotations.push({
            type: 'PASS',
            description: 'The same vendor uploaded national_id and iban_proof through the vendor documents action using the local PDF fixture.',
        });
    });

    await test.step('approve the same vendor documents through the Admin UI', async () => {
        const response = await adminPage.goto('/admin/vendor-document-filaments');
        expect(response?.status()).toBe(200);

        if (/\/admin\/login$/.test(adminPage.url())) {
            await adminPage.locator('input[wire\\:model="data.email"]').fill('uat-admin@example.test');
            await adminPage.locator('input[wire\\:model="data.password"]').fill('UatTest123!');
            await adminPage.getByRole('button', { name: /sign in/i }).click();
            await expect(adminPage).toHaveURL(/\/admin(?:\/vendor-document-filaments)?\/?$/, { timeout: 15_000 });
            if (!/\/admin\/vendor-document-filaments\/?$/.test(adminPage.url())) {
                const documentsResponse = await adminPage.goto('/admin/vendor-document-filaments');
                expect(documentsResponse?.status()).toBe(200);
            }
        }

        for (const documentLabel of [/national id|بطاقة الهوية/i, /iban proof|إثبات رقم iban/i]) {
            const row = adminPage.locator('tr')
                .filter({ hasText: vendorBusinessName })
                .filter({ hasText: documentLabel })
                .filter({ hasText: 'test_national_id.pdf' })
                .first();
            await expect(row).toBeVisible();

            const viewLink = row.getByRole('link').first();
            await expect(viewLink).toBeVisible();
            await viewLink.click();
            await expect(adminPage).toHaveURL(/\/admin\/vendor-document-filaments\/[^/]+$/);
            await expect(adminPage.locator('body')).toContainText(vendorBusinessName);

            await adminPage.goto('/admin/vendor-document-filaments');
            const pendingRow = adminPage.locator('tr').filter({ hasText: vendorBusinessName }).filter({ hasText: documentLabel }).first();
            const approveButton = pendingRow.getByRole('button', { name: /approve|موافقة/i }).first();
            await expect(approveButton).toBeVisible();
            const mountActionResponsePromise = adminPage.waitForResponse((networkResponse) => {
                const requestBody = networkResponse.request().postData();

                return networkResponse.request().method() === 'POST'
                    && networkResponse.url().includes('/livewire/update')
                    && requestBody !== null
                    && requestBody.includes('mountTableAction');
            });
            await approveButton.click();
            const mountActionResponse = await mountActionResponsePromise;
            const mountActionPayload = await mountActionResponse.json() as { errors?: unknown };
            const mountActionErrors = mountActionPayload.errors !== null && typeof mountActionPayload.errors === 'object'
                ? Object.keys(mountActionPayload.errors).sort()
                : [];
            console.log('DOCUMENT_APPROVE_MODAL_MOUNT', JSON.stringify({
                status: mountActionResponse.status(),
                errors: mountActionErrors,
            }));
            expect(mountActionResponse.status()).toBe(200);
            expect(mountActionErrors).toEqual([]);

            const confirmationCopy = adminPage.getByText('Are you sure you would like to do this?', { exact: true })
                .or(adminPage.getByText('هل أنت متأكد من القيام بهذه العملية؟', { exact: true }));
            const confirmation = adminPage.locator('.fi-modal-window:visible')
                .filter({ has: confirmationCopy })
                .last();
            await expect(confirmation).toBeVisible();
            const approvalResponsePromise = adminPage.waitForResponse((networkResponse) => {
                const requestBody = networkResponse.request().postData();

                return networkResponse.request().method() === 'POST'
                    && networkResponse.url().includes('/livewire/update')
                    && requestBody !== null
                    && requestBody.includes('callMountedTableAction');
            });
            await confirmation.getByRole('button', { name: /^(Confirm|تأكيد)$/, exact: true }).click();
            const approvalResponse = await approvalResponsePromise;
            const approvalPayload = await approvalResponse.json() as { errors?: unknown };
            const approvalErrors = approvalPayload.errors !== null && typeof approvalPayload.errors === 'object'
                ? Object.keys(approvalPayload.errors).sort()
                : [];
            console.log('DOCUMENT_APPROVE_ACTION', JSON.stringify({
                status: approvalResponse.status(),
                errors: approvalErrors,
            }));
            expect(approvalResponse.status()).toBe(200);
            expect(approvalErrors).toEqual([]);
            await expect(pendingRow).toContainText(/approved|موافق عليه/i, { timeout: 15_000 });
        }
    });

    await test.step('save one open business-hours row through the real browser', async () => {
        const response = await vendorPage.goto('/vendor-portal/vendor-business-hours-page');
        expect(response?.status()).toBe(200);

        const open = vendorPage.locator('[id="data.day_1_open"]');
        await expect(open).toBeAttached();
        if (!await open.isChecked()) {
            await open.check();
        }
        await vendorPage.locator('[id="data.day_1_opens_at"]').fill('09:00');
        await vendorPage.locator('[id="data.day_1_closes_at"]').fill('22:00');
        await vendorPage.getByRole('button', { name: /save changes|حفظ التغييرات/i }).click();
        await expect(vendorPage.locator('body')).toContainText(/business hours updated|تم تحديث ساعات العمل/i);
    });

    await test.step('add one coverage area through the real browser', async () => {
        const response = await vendorPage.goto('/vendor-portal/vendor-coverage-areas-page');
        expect(response?.status()).toBe(200);
        await vendorPage.getByRole('button', { name: /add coverage area|إضافة منطقة تغطية/i }).click();

        const dialog = vendorPage.locator('[role="dialog"].fi-modal.fi-modal-open')
            .filter({ has: vendorPage.getByRole('heading', { name: /add coverage area|إضافة منطقة تغطية/i }) })
            .locator('.fi-modal-window');
        await expect(dialog).toBeVisible();
        const cityField = dialog.locator('label[for="mountedActionsData.0.city_id"]')
            .locator('xpath=ancestor::div[contains(@class,"fi-fo-field-wrp")][1]');
        const city = cityField.locator('.choices__inner');
        await expect(cityField).toHaveCount(1);
        await city.click();
        const nasrCityOptionName = /^(?:(?:Cairo|القاهرة) — )?(?:Nasr City|مدينة نصر)$/i;
        const cityList = vendorPage.locator('.choices__list--dropdown.is-active:visible')
            .filter({ has: vendorPage.getByRole('option', { name: nasrCityOptionName, exact: true }) });
        await expect(cityList).toHaveCount(1, { timeout: 15_000 });
        await expect(cityList).toBeVisible({ timeout: 15_000 });
        const nasrCityOption = cityList.getByRole('option', { name: nasrCityOptionName, exact: true });
        await expect(nasrCityOption).toBeVisible({ timeout: 15_000 });
        await nasrCityOption.click();
        await dialog.getByLabel(/delivery fee|رسوم التوصيل/i).fill('0');
        await dialog.getByLabel(/minimum order|الحد الأدنى للطلب/i).fill('0');
        await dialog.getByRole('button', { name: /^(Submit|إرسال)$/i }).click();
        await expect(vendorPage.locator('body')).toContainText(/Nasr City|مدينة نصر/i);
    });

    await test.step('verify the new vendor is visible to Admin', async () => {
        const response = await adminPage.goto('/admin/vendor-approval-queue');
        expect(response?.status()).toBe(200);

        if (/\/admin\/login$/.test(adminPage.url())) {
            await adminPage.locator('input[wire\\:model="data.email"]').fill('uat-admin@example.test');
            await adminPage.locator('input[wire\\:model="data.password"]').fill('UatTest123!');
            await adminPage.getByRole('button', { name: /sign in/i }).click();
            await expect(adminPage).toHaveURL(/\/admin\/vendor-approval-queue$/, { timeout: 15_000 });
        }

        await expect(adminPage.locator('body')).toContainText(vendorBusinessName);
        expect(new URL(adminPage.url()).pathname).toBe('/admin/vendor-approval-queue');
    });

    await test.step('approve the same vendor profile and sale type through the Admin UI', async () => {
        const vendorEntry = adminPage.getByText(vendorBusinessName, { exact: true }).first();
        await expect(vendorEntry).toBeVisible();
        await vendorEntry.click();
        await expect(adminPage).toHaveURL(/\/admin\/vendor-approval-queue\/[^/]+$/);

        const bodyText = await adminPage.locator('body').innerText();
        const approveButton = adminPage.getByRole('button', { name: /approve profile/i });
        expect(await approveButton.isVisible()).toBe(true);

        if (await approveButton.isDisabled()) {
            throw new Error(`Vendor approval remained disabled after onboarding: ${bodyText}`);
        }

        await approveButton.click();
        const confirmation = adminPage.locator('[role="dialog"].fi-modal.fi-modal-open')
            .filter({ has: adminPage.getByRole('heading', { name: 'Approve this vendor?', exact: true }) })
            .locator('.fi-modal-window');
        await expect(confirmation).toHaveCount(1);
        await expect(confirmation).toBeVisible();
        await confirmation.getByRole('button', { name: 'Confirm', exact: true }).click();
        await expect(adminPage).toHaveURL(/\/admin\/vendor-approval-queue\/?$/);

        const profilesResponse = await adminPage.goto('/admin/vendor-profiles');
        expect(profilesResponse?.status()).toBe(200);
        const profileRow = adminPage.locator('tr').filter({ hasText: vendorBusinessName }).first();
        await expect(profileRow).toBeVisible();
        const typeApproval = profileRow.locator('button[wire\\:click*="approveForType"]').first();
        await expect(typeApproval).toBeVisible();
        await typeApproval.click();
        const typeDialog = adminPage.locator('.fi-modal-window:visible')
            .filter({ has: adminPage.getByRole('heading', { name: 'Approve Product Type', exact: true }) })
            .last();
        await expect(typeDialog).toBeVisible();
        await typeDialog.getByLabel(/product type|نوع المنتج/i).selectOption('sale');
        await typeDialog.getByRole('button', { name: /submit|approve|confirm|اعتماد|تأكيد|إرسال/i }).last().click();
        await expect(typeDialog).toBeHidden({ timeout: 15_000 });
        const approvedTypesResponse = await adminPage.goto('/admin/vendor-approved-product-types');
        expect(approvedTypesResponse?.status()).toBe(200);
        const approvedTypeRow = adminPage.locator('tr')
            .filter({ hasText: vendorBusinessName })
            .filter({ hasText: /sale|بيع/i })
            .first();
        await expect(approvedTypeRow).toBeVisible();
    });

    await test.step('create and publish a sale service for the same vendor', async () => {
        const response = await vendorPage.goto('/vendor-portal/vendor-sale-services/create');
        console.log('VENDOR_SERVICE_RESULT', response?.status(), new URL(vendorPage.url()).pathname);
        expect(response?.status()).toBe(200);
        await expect(vendorPage.locator('body')).toContainText(/create sale service|إضافة خدمة بيع/i);

        const serviceName = `Marketplace Sale ${runId}`;
        await vendorPage.locator('[id="data.name.en"]').fill(serviceName);
        await vendorPage.locator('[id="data.short_description.en"]').fill('A browser verified sale service.');
        await vendorPage.locator('form').getByRole('tablist', { name: /Translations|الترجمات/i })
            .getByRole('tab', { name: /Arabic|العربية/i }).click();
        await vendorPage.locator('[id="data.name.ar"]').fill(`بيع ${runId}`);
        await vendorPage.locator('[id="data.short_description.ar"]').fill('خدمة بيع تم التحقق منها بالمتصفح.');

        const categoryField = vendorPage.locator('[data-field-wrapper]')
            .filter({ has: vendorPage.locator('label[for="data.category_id"]') });
        const category = categoryField.locator('.choices[role="combobox"]');
        await expect(categoryField).toHaveCount(1);
        await category.click();
        const categoryOption = category.locator('.choices__list--dropdown [role="option"]')
            .filter({ hasText: /^(General Birthday|عام عيد ميلاد)$/i }).first();
        await expect(categoryOption).toBeVisible({ timeout: 15_000 });
        await categoryOption.click();
        await expect(categoryField.locator('select[id="data.category_id"]')).toHaveValue(/^\d+$/);
        await vendorPage.locator('[id="data.base_price_minor"]').fill('25000');
        await vendorPage.locator('[id="data.saleDetail.stock_quantity"]').fill('3');
        const galleryInput = vendorPage.locator('.fi-fo-file-upload:visible input[type="file"]').last();
        await expect(galleryInput).toBeAttached({ timeout: 30_000 });
        const galleryUploadResponseCountBefore = uploadResponseDiagnostics.length;
        await galleryInput.setInputFiles(path.resolve('after-hydration.png'));
        await expect.poll(() => uploadResponseDiagnostics.length, { timeout: 30_000 })
            .toBeGreaterThan(galleryUploadResponseCountBefore);
        await vendorPage.waitForFunction(() => {
            const livewire = (window as any).Livewire;
            const components = typeof livewire?.all === 'function' ? livewire.all() : [];

            return components.some((component: any) => {
                let gallery: any;

                try {
                    gallery = component?.$wire?.get?.('data.gallery');
                } catch {
                    gallery = undefined;
                }

                return Array.isArray(gallery)
                    ? gallery.length > 0
                    : gallery !== null && gallery !== undefined && gallery !== '' &&
                        (typeof gallery !== 'object' || Object.keys(gallery).length > 0);
            });
        }, undefined, { timeout: 30_000 });
        const createButton = vendorPage.locator('form.fi-form[wire\\:submit="create"] button[type="submit"]')
            .filter({ hasNot: vendorPage.locator('[wire\\:click*="createAnother"]') });
        await expect(createButton).toHaveCount(1);
        await expect(createButton).toBeVisible();
        await expect(createButton).toBeEnabled();
        const createResponsePromise = vendorPage.waitForResponse((networkResponse) =>
            networkResponse.request().method() === 'POST' && networkResponse.url().includes('/livewire/update'));
        await createButton.click();
        const createResponse = await createResponsePromise;
        expect(createResponse.status()).toBe(200);
        await vendorPage.waitForURL(/\/vendor-portal\/vendor-sale-services\/?$/, { timeout: 30_000 });
        const expectedServiceName = await vendorPage.locator('html').getAttribute('dir') === 'rtl'
            ? `بيع ${runId}`
            : serviceName;
        const serviceRows = vendorPage.locator('tr').filter({ hasText: expectedServiceName });
        await expect(serviceRows).toHaveCount(1);
        const serviceRow = serviceRows.first();
        await expect(serviceRow).toBeVisible();
        const vendorLogin = await vendorPage.evaluate(async ({ email, password }) => {
            const response = await fetch('/api/v1/login', {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ login: email, password, device_name: 'browser-e2e-vendor' }),
            });
            return { status: response.status, body: await response.json() as { data?: { token?: string } } };
        }, { email: vendorEmail, password: vendorPassword });
        expect(vendorLogin.status).toBe(200);
        vendorToken = vendorLogin.body.data?.token ?? null;
        expect(vendorToken).toBeTruthy();
        const servicesResponse = await vendorPage.request.get('/api/v1/vendor/services?type=sale&per_page=100', {
            headers: { Accept: 'application/json', Authorization: `Bearer ${vendorToken}` },
        });
        expect(servicesResponse.ok()).toBeTruthy();
        const servicesPayload = await servicesResponse.json() as {
            data?: Array<{ public_id?: string; name?: string; product_type?: string }>;
        };
        servicePublicId = servicesPayload.data?.find((service) =>
            service.product_type === 'sale' && service.name === serviceName
        )?.public_id ?? null;
        expect(servicePublicId).toMatch(/^[0-9A-HJKMNP-TV-Z]{26}$/);

        await vendorPage.locator('tr').filter({ hasText: serviceName }).first()
            .getByRole('button', { name: /submit for review/i }).click();
        const submitConfirmation = vendorPage.locator('.fi-modal-window:visible').last();
        await expect(submitConfirmation).toBeVisible();
        await submitConfirmation.getByRole('button', { name: /confirm|submit/i }).last().click();
        await expect(vendorPage.locator('body')).toContainText(/submitted|pending review|قيد المراجعة/i);

        const pendingResponse = await adminPage.goto('/admin/sale-services/pending-review');
        expect(pendingResponse?.status()).toBe(200);
        const pendingRow = adminPage.locator('tr').filter({ hasText: serviceName }).first();
        await expect(pendingRow).toBeVisible();
        await pendingRow.getByRole('button', { name: /approve.*publish|اعتماد ونشر/i }).click();
        const publishConfirmation = adminPage.locator('.fi-modal-window:visible').last();
        await expect(publishConfirmation).toBeVisible();
        await publishConfirmation.getByRole('button', { name: /approve|publish|confirm|اعتماد|نشر/i }).last().click();
        await expect(adminPage.getByText(/service published successfully|تم نشر الخدمة بنجاح/i)).toBeVisible({ timeout: 15_000 });
        const publishedServiceResponse = await customerPage.goto(`/en/services/${servicePublicId!}`);
        expect(publishedServiceResponse?.status()).toBe(200);
        await expect(customerPage.locator('body')).toContainText(serviceName);
        console.log('SERVICE_PUBLISHED', servicePublicId);
    });

    await test.step('register and verify a new customer in the real storefront', async () => {
        const response = await customerPage.goto('/en/auth/register');
        expect(response?.status()).toBe(200);

        await customerPage.locator('input[name="name"]').fill(`Marketplace Customer ${runId}`);
        await customerPage.locator('input[name="phone_e164"]').fill(`+2011${Date.now().toString().slice(-8)}`);
        await customerPage.locator('input[name="email"]').fill(customerEmail);
        await customerPage.locator('input[name="password"]').fill(customerPassword);
        await customerPage.locator('input[name="password_confirmation"]').fill(customerPassword);
        await customerPage.locator('input[name="accepted_terms"]').check();
        await customerPage.locator('form[action*="/auth/register"] button[type="submit"]').click();

        await expect(customerPage).toHaveURL(/\/en\/auth\/verify$/);
        await expect(customerPage.locator('input[name="code"]')).toBeVisible();
        await customerPage.locator('input[name="code"]').fill('123456');
        await customerPage.getByRole('button', { name: /verify|confirm/i }).click();
        await expect(customerPage).toHaveURL(/\/en\/?$/);

        const login = await customerPage.evaluate(async ({ email, password }) => {
            const response = await fetch('/api/v1/login', {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ login: email, password, device_name: 'browser-e2e' }),
            });

            return { status: response.status, body: await response.json() as { data?: { token?: string } } };
        }, { email: customerEmail, password: customerPassword });
        expect(login.status).toBe(200);
        expect(login.body.data?.token).toBeTruthy();
        const customerToken = login.body.data!.token!;
        await customerPage.evaluate((token) => {
            window.localStorage.setItem('e2e.customer.token', token);
        }, customerToken);
        test.info().annotations.push({
            type: 'PASS',
            description: 'Customer registration, phone verification, and token login completed using the existing storefront and API contracts for the same identity.',
        });

    });

    await test.step('browse the same vendor service and add it through the storefront UI', async () => {
        expect(servicePublicId).toBeTruthy();
        const serviceResponse = await customerPage.goto(`/en/services/${servicePublicId!}`);
        expect(serviceResponse?.status()).toBe(200);
        await expect(customerPage.locator('body')).toContainText(`Marketplace Sale ${runId}`);
        await customerPage.getByRole('link', { name: /add to cart/i }).click();
        await expect(customerPage).toHaveURL(new RegExp(`/en/wizard\\?service=${servicePublicId!}$`));

        await customerPage.locator('[data-planner-occasion-option]').first().click();
        const startsAt = new Date(Date.now() + 24 * 60 * 60 * 1000);
        startsAt.setMinutes(0, 0, 0);
        const endsAt = new Date(startsAt.getTime() + 2 * 60 * 60 * 1000);
        const toLocalInput = (date: Date): string => date.toISOString().slice(0, 16);
        await customerPage.locator('[name="event_starts_at"]').fill(toLocalInput(startsAt));
        await customerPage.locator('[name="event_ends_at"]').fill(toLocalInput(endsAt));
        await customerPage.locator('[data-planner-next]').nth(0).click();

        const governorate = customerPage.locator('[data-planner-governorate]');
        const city = customerPage.locator('[data-planner-city-select]');
        const cairoOption = governorate.locator('option').filter({ hasText: /Cairo|القاهرة/i }).first();
        try {
            await expect(cairoOption).toBeAttached({ timeout: 15_000 });
        } catch (error) {
            const governoratesResponse = await customerPage.evaluate(async () => {
                const response = await fetch('/api/v1/customer/governorates', {
                    headers: { Accept: 'application/json' },
                });

                return { status: response.status, body: (await response.text()).slice(0, 4000) };
            });
            console.log('CUSTOMER_GOVERNORATES_RESULT', JSON.stringify(governoratesResponse));
            expect(governoratesResponse.status, governoratesResponse.body).toBe(200);
            throw error;
        }
        const cairoValue = await cairoOption.getAttribute('value');
        expect(cairoValue).toBeTruthy();
        await governorate.selectOption(cairoValue!);
        const nasrCityOption = city.locator('option').filter({ hasText: /Nasr City|مدينة نصر/i }).first();
        await expect(nasrCityOption).toBeAttached({ timeout: 15_000 });
        const nasrCityValue = await nasrCityOption.getAttribute('value');
        expect(nasrCityValue).toBeTruthy();
        await city.selectOption(nasrCityValue!);
        await expect(customerPage.locator('[data-planner-city]')).toHaveValue(/.+/);
        await customerPage.locator('[data-planner-next]').nth(1).click();

        await customerPage.locator('input[name="guest_count"]').fill('2');
        await customerPage.locator('input[name="celebrant_name"]').fill('E2E Customer');
        await customerPage.locator('input[name="address[address_line]"]').fill('10 E2E Street');
        await customerPage.locator('input[name="address[recipient_name]"]').fill('E2E Customer');
        await customerPage.locator('input[name="address[recipient_phone_e164]"]').fill('+201212345678');
        await customerPage.locator('form[data-planner-form] button[type="submit"]').click();

        await expect(customerPage).toHaveURL(/\/en\/cart$/);
        await expect(customerPage.locator('body')).toContainText(`Marketplace Sale ${runId}`);
    });

    await test.step('review and submit the same customer cart', async () => {
        const submitForm = customerPage.locator('form[action*="/cart/"][action*="/submit"]');
        await expect(submitForm).toHaveCount(1);
        const submitAction = await submitForm.getAttribute('action');
        expect(submitAction).toMatch(/\/en\/cart\/[^/]+\/submit$/);
        bookingPublicId = submitAction!.split('/').at(-2)!;

        const checkoutReview = await customerPage.evaluate(async (publicId) => {
            const token = window.localStorage.getItem('e2e.customer.token');
            const response = await fetch(`/api/v1/customer/bookings/${publicId}/checkout-review`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${token}`,
                },
                body: '{}',
            });
            return { status: response.status, body: await response.json() as { data?: { ready_to_submit?: boolean } } };
        }, bookingPublicId);
        console.log('CHECKOUT_REVIEW_RESULT', checkoutReview.status, checkoutReview.body.data?.ready_to_submit ?? null);
        expect(checkoutReview.status).toBe(200);
        expect(checkoutReview.body.data?.ready_to_submit).toBe(true);

        await submitForm.locator('button[type="submit"]').click();
        await expect(customerPage).toHaveURL(/\/en\/cart$/);
        console.log('BOOKING_SUBMITTED', bookingPublicId);
    });

    await test.step('confirm the booking through the same vendor API contract', async () => {
        expect(vendorToken).toBeTruthy();
        const list = await vendorPage.evaluate(async (token) => {
            const response = await fetch('/api/v1/vendor/booking-vendors', {
                headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
            });
            const body = await response.json() as { data?: Array<{ public_id?: string }> };
            const data = await Promise.all((body.data ?? []).map(async (entry) => {
                if (!entry.public_id) return { ...entry, booking: null };
                const detailResponse = await fetch(`/api/v1/vendor/booking-vendors/${entry.public_id}`, {
                    headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
                });
                const detail = await detailResponse.json() as { data?: { booking?: { public_id?: string } } };
                return { ...entry, booking: detail.data?.booking ?? null };
            }));
            return { status: response.status, body: { data } };
        }, vendorToken!);
        expect(list.status).toBe(200);
        bookingVendorPublicId = list.body.data?.find((entry) => entry.booking?.public_id === bookingPublicId)?.public_id ?? null;
        expect(bookingVendorPublicId).toBeTruthy();

        const accepted = await vendorPage.evaluate(async ({ token, publicId }) => {
            const response = await fetch(`/api/v1/vendor/booking-vendors/${publicId}/accept`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
                body: '{}',
            });
            return { status: response.status, body: await response.json() as { data?: { public_id?: string; sub_status?: string } } };
        }, { token: vendorToken!, publicId: bookingVendorPublicId! });
        console.log('BOOKING_ACCEPT_RESULT', accepted.status, accepted.body.data?.sub_status ?? null);
        expect(accepted.status).toBe(200);
        expect(accepted.body.data?.public_id).toBe(bookingVendorPublicId);
        expect(accepted.body.data?.sub_status).toBe('accepted');
        console.log('BOOKING_CONFIRMED', bookingPublicId);
    });

    await test.step('create one idempotent pending payment for the same customer', async () => {
        expect(bookingPublicId).toBeTruthy();
        const payment = await customerPage.evaluate(async (publicId) => {
            const token = window.localStorage.getItem('e2e.customer.token');
            const response = await fetch(`/api/v1/customer/bookings/${publicId}/payments`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${token}`,
                    'Idempotency-Key': 'full-purchase-payment-0001',
                },
                body: JSON.stringify({ method: 'card' }),
            });
            return { status: response.status, body: await response.json() as { data?: { payment_public_id?: string; status?: string; redirect_url?: string } } };
        }, bookingPublicId!);
        console.log('PAYMENT_RESULT', payment.status, payment.body.data?.status ?? null);
        expect(payment.status).toBe(201);
        expect(payment.body.data?.status).toBe('pending');
        expect(payment.body.data?.redirect_url).toBe(`https://testing.invalid/payments/${bookingPublicId}`);
        expect(payment.body.data?.payment_public_id).toBeTruthy();

        const duplicate = await customerPage.evaluate(async (publicId) => {
            const token = window.localStorage.getItem('e2e.customer.token');
            const response = await fetch(`/api/v1/customer/bookings/${publicId}/payments`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${token}`,
                    'Idempotency-Key': 'full-purchase-payment-0001',
                },
                body: JSON.stringify({ method: 'card' }),
            });
            return { status: response.status, body: await response.json() as { data?: { payment_public_id?: string; status?: string } } };
        }, bookingPublicId!);
        expect(duplicate.status).toBe(201);
        expect(duplicate.body.data?.payment_public_id).toBe(payment.body.data?.payment_public_id);
        expect(duplicate.body.data?.status).toBe('pending');
        console.log('PAYMENT_CREATED', payment.body.data?.payment_public_id, 'pending/unpaid');
    });

    await customerContext.close();
    await vendorContext.close();
});
