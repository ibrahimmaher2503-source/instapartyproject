import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, request, test, type APIRequestContext, type Browser, type BrowserContext, type Page, type Request, type Response } from '@playwright/test';

const baseURL = 'http://127.0.0.1:8139';
const runId = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 7)}`;
const fixturePrefix = `qa-payload-${runId}`;
const fixturePassword = 'AuditOnlyLocalUat123!';
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

test.use({ trace: 'off', screenshot: 'off', video: 'off' });

type Marker = { label: string; value: string };
type UserFixture = {
    name: string;
    email: string;
    phone: string;
    publicId: string;
    token: string;
    addressMarker?: string;
    addressCount?: number;
};
type VendorFixture = {
    name: string;
    email: string;
    phone: string;
    publicId: string;
    profilePublicId: string;
    slug: string;
    token: string;
    privateMarkers: string[];
};
type AuditFixture = {
    customers: { a: UserFixture; b: UserFixture };
    vendors: { a: VendorFixture; b: VendorFixture };
    registration: { email: string; phone: string; name: string };
    publicVendorCount: number;
};

type NetworkEntry = {
    method: string;
    path: string;
    resourceType: string;
    status: number;
    decodedBodyBytes: number | null;
    fields: string[];
};
type RequestEntry = { method: string; path: string; resourceType: string; failureText?: string };

function runPhp(code: string): string {
    try {
        return execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], {
            env: environment,
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'pipe'],
            timeout: 30_000,
        });
    } catch {
        throw new Error('Bounded local payload-audit fixture operation failed.');
    }
}

function seedFixtures(): AuditFixture {
    const prefix = JSON.stringify(fixturePrefix);
    const password = JSON.stringify(fixturePassword);
    const output = runPhp(String.raw`
        $prefix=${prefix};
        $password=${password};
        Spatie\Permission\Models\Role::findOrCreate('customer','web');
        Spatie\Permission\Models\Role::findOrCreate('vendor','web');
        $governorate=App\Modules\Geography\Domain\Models\Governorate::query()->where('code','EG-CAI')->firstOrFail();
        $city=App\Modules\Geography\Domain\Models\City::query()->where('governorate_id',$governorate->id)->firstOrFail();
        $customers=[];
        foreach (['a','b'] as $key) {
            $email='qa-payload-'.$prefix.'-customer-'.$key.'@example.test';
            $name='QA Payload Customer '.strtoupper($key).' '.$prefix;
            $user=App\Modules\Identity\Domain\Models\User::factory()->phoneVerified()->asCustomer()->create([
                'name'=>$name,'email'=>$email,'password'=>$password,
            ]);
            $addressMarker='QA_PAYLOAD_ADDRESS_'.strtoupper($key).'_'.$prefix;
            $addressCount=$key==='a' ? 14 : 8;
            for ($index=1; $index<=$addressCount; $index++) {
                App\Modules\Identity\Domain\Models\CustomerAddress::factory()->forUser($user)->create([
                    'city_id'=>$city->id,
                    'label'=>'QA audit '.strtoupper($key).' '.$index,
                    'address_line'=>$addressMarker.'_'.$index,
                    'recipient_name'=>'QA Recipient '.strtoupper($key).' '.$index,
                    'recipient_phone_e164'=>$user->phone_e164,
                ]);
            }
            $customers[$key]=[
                'name'=>$name,'email'=>$email,'phone'=>$user->phone_e164,
                'publicId'=>$user->public_id,'token'=>$user->createToken('qa-payload-audit')->plainTextToken,
                'addressMarker'=>$addressMarker,'addressCount'=>$addressCount,
            ];
        }

        $vendors=[];
        for ($index=1; $index<=15; $index++) {
            $key=$index===1 ? 'a' : ($index===2 ? 'b' : (string) $index);
            $email='qa-payload-'.$prefix.'-vendor-'.$key.'@example.test';
            $name='QA Payload Vendor User '.strtoupper($key).' '.$prefix;
            $user=App\Modules\Identity\Domain\Models\User::factory()->phoneVerified()->asVendor()->create([
                'name'=>$name,'email'=>$email,'password'=>$password,
            ]);
            $businessName='QA Payload Audit '.$prefix.' Vendor '.str_pad((string) $index,2,'0',STR_PAD_LEFT);
            $slug='qa-payload-'.$prefix.'-vendor-'.$key;
            $privateMarkers=[
                'QP-NID-'.$prefix.'-'.$key,
                'QP-CR-'.$prefix.'-'.$key,
                'QP-TAX-'.$prefix.'-'.$key,
                'QP-IBAN-'.$prefix.'-'.$key,
            ];
            $profile=App\Modules\Identity\Domain\Models\VendorProfile::factory()->approved()->create([
                'user_id'=>$user->id,
                'primary_governorate_id'=>$governorate->id,
                'primary_city_id'=>$city->id,
                'business_name'=>['en'=>$businessName,'ar'=>'مورد اختبار '.$index],
                'bio'=>['en'=>'QA public profile '.$prefix,'ar'=>'ملف مورد للاختبار'],
                'slug'=>$slug,
                'national_id'=>$privateMarkers[0],
                'commercial_register_no'=>$privateMarkers[1],
                'tax_id'=>$privateMarkers[2],
                'bank_iban'=>$privateMarkers[3],
            ]);
            if ($key==='a' || $key==='b') {
                $vendors[$key]=[
                    'name'=>$name,'email'=>$email,'phone'=>$user->phone_e164,
                    'publicId'=>$user->public_id,'profilePublicId'=>$profile->public_id,
                    'slug'=>$slug,'token'=>$user->createToken('qa-payload-audit')->plainTextToken,
                    'privateMarkers'=>$privateMarkers,
                ];
            }
        }

        $registrationEmail='qa-payload-'.$prefix.'-registration@example.test';
        $registrationPhone='+2010'.str_pad((string) random_int(0,99999999),8,'0',STR_PAD_LEFT);
        echo json_encode([
            'customers'=>$customers,'vendors'=>$vendors,
            'registration'=>[
                'email'=>$registrationEmail,'phone'=>$registrationPhone,
                'name'=>'QA Payload Registration '.$prefix,
            ],
            'publicVendorCount'=>15,
        ],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    `);

    return JSON.parse(output) as AuditFixture;
}

function cleanupFixtures(): void {
    const prefix = JSON.stringify(fixturePrefix);
    runPhp(String.raw`
        $prefix=${prefix};
        $users=App\Modules\Identity\Domain\Models\User::query()
            ->where('email','like','qa-payload-'.$prefix.'-%@example.test')->get();
        $userIds=$users->modelKeys();
        if ($userIds!==[]) {
            App\Modules\Identity\Domain\Models\CustomerAddress::withTrashed()->whereIn('user_id',$userIds)->forceDelete();
            $profiles=App\Modules\Identity\Domain\Models\VendorProfile::query()->whereIn('user_id',$userIds)->get();
            foreach ($profiles as $profile) {
                App\Modules\Identity\Domain\Models\VendorComplianceEvent::query()->where('vendor_profile_id',$profile->id)->delete();
                $profile->documents()->delete();
                $profile->businessHours()->delete();
                $profile->coverageAreas()->delete();
                $profile->approvedProductTypes()->delete();
                $profile->forceDelete();
            }
            foreach ($users as $user) {
                $user->tokens()->delete();
                $user->syncRoles([]);
                $user->forceDelete();
            }
        }
        $registration=App\Modules\Identity\Domain\Models\User::query()
            ->where('email','qa-payload-'.$prefix.'-registration@example.test')->first();
        if ($registration!==null) {
            $registration->tokens()->delete();
            $registration->syncRoles([]);
            $registration->forceDelete();
        }
    `);
}

test.afterAll(() => {
    cleanupFixtures();
});

function safePath(rawUrl: string): string {
    const pathname = new URL(rawUrl).pathname;

    return pathname
        .replaceAll(runId, ':fixture')
        .replace(/\/[0-9A-HJKMNP-TV-Z]{26}(?=\/|$)/gi, '/:public-id')
        .replace(/\/\d+(?=\/|$)/g, '/:id');
}

function jsonFieldNames(value: unknown, prefix = '', depth = 0, seen = new Set<string>()): string[] {
    if (depth > 7 || seen.size >= 120) return [...seen];

    if (Array.isArray(value)) {
        for (const item of value.slice(0, 3)) jsonFieldNames(item, prefix, depth + 1, seen);

        return [...seen];
    }

    if (value === null || typeof value !== 'object') return [...seen];

    for (const [key, child] of Object.entries(value as Record<string, unknown>)) {
        const field = prefix ? `${prefix}.${key}` : key;
        seen.add(field);
        if (typeof child === 'string' && depth < 5 && /^[\[{]/.test(child.trim())) {
            try {
                jsonFieldNames(JSON.parse(child), field, depth + 1, seen);
            } catch {
                // Encoded HTML and non-JSON strings are intentionally not inspected.
            }
        } else {
            jsonFieldNames(child, field, depth + 1, seen);
        }
    }

    return [...seen];
}

const sensitiveResponseFields = new Set([
    'password', 'password_confirmation', 'password_hash', 'remember_token', 'token', 'tokens',
    'access_token', 'refresh_token', 'secret', 'secret_key', 'client_secret', 'private_key', 'api_key',
    'authorization', 'session_id', 'cookie', 'csrf_token', 'otp', 'verification_code',
    'national_id', 'commercial_register_no', 'tax_id', 'bank_iban', 'bank_account_number',
    'card_number', 'pan', 'cvv', 'cvc',
]);

function networkCapture(page: Page) {
    const requests: RequestEntry[] = [];
    const responses: NetworkEntry[] = [];
    const pending = new Set<Promise<void>>();
    const inFlight = new Set<Request>();
    const failed: RequestEntry[] = [];
    const localOrigin = new URL(baseURL).origin;

    page.on('request', (request: Request) => {
        if (new URL(request.url()).origin !== localOrigin) return;
        inFlight.add(request);
        requests.push({ method: request.method(), path: safePath(request.url()), resourceType: request.resourceType() });
    });
    page.on('requestfailed', (request: Request) => {
        if (new URL(request.url()).origin !== localOrigin) return;
        inFlight.delete(request);
        const rawFailureText = request.failure()?.errorText ?? '';
        const failureText = /^net::ERR_[A-Z0-9_]+$/.test(rawFailureText) ? rawFailureText : 'other';
        failed.push({ method: request.method(), path: safePath(request.url()), resourceType: request.resourceType(), failureText });
    });
    page.on('requestfinished', (request: Request) => {
        if (new URL(request.url()).origin === localOrigin) inFlight.delete(request);
    });
    page.on('response', (response: Response) => {
        if (new URL(response.url()).origin !== localOrigin) return;

        const request = response.request();
        const entry: NetworkEntry = {
            method: request.method(),
            path: safePath(response.url()),
            resourceType: request.resourceType(),
            status: response.status(),
            decodedBodyBytes: null,
            fields: [],
        };
        responses.push(entry);

        const work = (async () => {
            if (!['document', 'xhr', 'fetch'].includes(entry.resourceType)) return;
            const body = await response.body().catch(() => null);
            if (body === null) return;
            entry.decodedBodyBytes = body.byteLength;

            const contentType = (await response.headerValue('content-type'))?.toLowerCase() ?? '';
            if (contentType.includes('json') && body.byteLength <= 2_000_000) {
                try {
                    entry.fields = jsonFieldNames(JSON.parse(body.toString('utf8'))).slice(0, 80);
                } catch {
                    entry.fields = [];
                }
            }
        })();
        pending.add(work);
        void work.finally(() => pending.delete(work));
    });

    return {
        mark: () => ({ requestIndex: requests.length, responseIndex: responses.length, failedIndex: failed.length }),
        pendingRequestCount: () => inFlight.size,
        async settleRequests(timeoutMs = 5_000): Promise<number> {
            const deadline = Date.now() + timeoutMs;
            while (inFlight.size > 0 && Date.now() < deadline) {
                await new Promise<void>((resolve) => setTimeout(resolve, 50));
            }

            return inFlight.size;
        },
        async flush(): Promise<void> {
            const all = Promise.allSettled([...pending]);
            await Promise.race([all, new Promise<void>((resolve) => setTimeout(resolve, 2_500))]);
        },
        summarize(mark: { requestIndex: number; responseIndex: number; failedIndex: number }) {
            const recentRequests = requests.slice(mark.requestIndex);
            const recentResponses = responses.slice(mark.responseIndex);
            const recentFailed = failed.slice(mark.failedIndex);
            const payloadResponses = recentResponses.filter((item) => ['document', 'xhr', 'fetch'].includes(item.resourceType));
            const uniqueFields = [...new Set(payloadResponses.flatMap((item) => item.fields))].sort();

            return {
                sameOriginRequests: recentRequests.length,
                completedResponses: recentResponses.length,
                failedRequests: recentFailed.length,
                failedEndpoints: [...new Set(recentFailed.map((item) => `${item.method} ${item.path} [${item.resourceType}; ${item.failureText ?? 'other'}]`))].sort().slice(0, 60),
                pendingRequestsAtSnapshot: inFlight.size,
                xhrFetchRequests: recentRequests.filter((item) => ['xhr', 'fetch'].includes(item.resourceType)).length,
                decodedPayloadBytes: payloadResponses.reduce((total, item) => total + (item.decodedBodyBytes ?? 0), 0),
                documentBytes: recentResponses.find((item) => item.resourceType === 'document')?.decodedBodyBytes ?? null,
                xhrFetchBytes: payloadResponses.filter((item) => ['xhr', 'fetch'].includes(item.resourceType))
                    .reduce((total, item) => total + (item.decodedBodyBytes ?? 0), 0),
                endpoints: [...new Set(recentRequests.map((item) => `${item.method} ${item.path} [${item.resourceType}]`))].sort().slice(0, 60),
                responseFields: uniqueFields.slice(0, 120),
                statuses: recentResponses.reduce<Record<string, number>>((counts, item) => {
                    const group = `${Math.floor(item.status / 100)}xx`;
                    counts[group] = (counts[group] ?? 0) + 1;

                    return counts;
                }, {}),
            };
        },
    };
}

async function boundedStep<T>(label: string, timeout: number, action: () => Promise<T>): Promise<T> {
    console.log(`QA_PAYLOAD_STEP_START ${label}`);
    try {
        const result = await test.step(label, action, { timeout });
        console.log(`QA_PAYLOAD_STEP_END ${label}`);

        return result;
    } catch (error) {
        console.log(`QA_PAYLOAD_STEP_FAILED ${label}`);
        throw error;
    }
}

async function visitPage(page: Page, capture: ReturnType<typeof networkCapture>, label: string, url: string) {
    return boundedStep(label, 20_000, async () => {
        const mark = capture.mark();
        const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15_000 });
        await page.waitForLoadState('networkidle', { timeout: 5_000 }).catch(() => undefined);
        const pendingRequestsAtSnapshot = await capture.settleRequests();
        await capture.flush();
        expect(pendingRequestsAtSnapshot, `Same-origin requests should settle before leaving ${label}`).toBe(0);
        const pageMetrics = await page.evaluate(() => {
            const html = document.documentElement.outerHTML;
            const pageText = document.body?.innerText ?? '';
            const pagination = document.querySelector('nav[aria-label]');

            return {
                renderedDomBytes: new TextEncoder().encode(html).byteLength,
                visibleTextBytes: new TextEncoder().encode(pageText).byteLength,
                vendorCardRows: document.querySelectorAll('.sf-vendors-page article').length,
                adminTableRows: document.querySelectorAll('.fi-ta-row').length,
                paginationLinks: pagination?.querySelectorAll('a').length ?? 0,
                paginationLabelPresent: Boolean(pagination),
            };
        });

        return {
            label,
            status: response?.status() ?? 0,
            documentBytes: capture.summarize(mark).documentBytes,
            network: capture.summarize(mark),
            pageMetrics: {
                renderedDomBytes: pageMetrics.renderedDomBytes,
                visibleTextBytes: pageMetrics.visibleTextBytes,
                vendorCardRows: pageMetrics.vendorCardRows,
                adminTableRows: pageMetrics.adminTableRows,
                paginationLinks: pageMetrics.paginationLinks,
                paginationLabelPresent: pageMetrics.paginationLabelPresent,
            },
        };
    });
}

async function inspectPageMarkers(page: Page, markers: Marker[]): Promise<Record<string, boolean>> {
    return page.evaluate((checks) => {
        const html = document.documentElement.outerHTML;

        return Object.fromEntries(checks.map(({ label, value }) => [label, html.includes(value)]));
    }, markers);
}

function makeApiContext(token?: string): Promise<APIRequestContext> {
    return request.newContext({
        baseURL,
        extraHTTPHeaders: {
            Accept: 'application/json',
            'Accept-Language': 'en',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        timeout: 15_000,
    });
}

async function apiEvidence(
    context: APIRequestContext,
    label: string,
    method: 'GET' | 'POST' | 'PUT',
    url: string,
    markers: Marker[] = [],
    data?: Record<string, unknown>,
) {
    const response = await context.fetch(url, { method, data, timeout: 15_000 });
    const body = await response.body();
    const text = body.toString('utf8');
    let payload: unknown = null;
    try {
        payload = JSON.parse(text);
    } catch {
        payload = null;
    }
    const fields = jsonFieldNames(payload).sort();
    const payloadRecord = payload !== null && typeof payload === 'object' ? payload as Record<string, unknown> : {};
    const dataValue = payloadRecord.data;
    const dataRecord = dataValue !== null && typeof dataValue === 'object' && !Array.isArray(dataValue)
        ? dataValue as Record<string, unknown>
        : {};
    const metaValue = payloadRecord.meta;
    const meta = metaValue !== null && typeof metaValue === 'object' ? metaValue as Record<string, unknown> : {};
    const paginationKeys = ['current_page', 'per_page', 'total', 'last_page'] as const;
    const pagination = Object.fromEntries(paginationKeys
        .filter((key) => typeof meta[key] === 'number' || typeof dataRecord[key] === 'number')
        .map((key) => [key, meta[key] ?? dataRecord[key]]));
    const rows = Array.isArray(dataValue)
        ? dataValue.length
        : Array.isArray(dataRecord.data) ? dataRecord.data.length : null;
    const tokenValue = dataRecord.token;

    return {
        label,
        status: response.status(),
        decodedBodyBytes: body.byteLength,
        contentType: response.headers()['content-type']?.split(';')[0] ?? null,
        fields: fields.slice(0, 100),
        forbiddenFields: fields.filter((field) => sensitiveResponseFields.has(field.split('.').at(-1)?.toLowerCase() ?? '')).slice(0, 40),
        rows,
        metaKeys: Object.keys(meta).sort(),
        pagination,
        tokenPresent: typeof tokenValue === 'string' && tokenValue.length > 0,
        markerPresence: Object.fromEntries(markers.map(({ label: markerLabel, value }) => [markerLabel, text.includes(value)])),
    };
}

async function loginCustomer(browser: Browser, fixture: UserFixture): Promise<{ context: BrowserContext; page: Page; capture: ReturnType<typeof networkCapture> }> {
    const context = await browser.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
    try {
        const page = await context.newPage();
        const capture = networkCapture(page);
        await page.goto('/en/auth/login', { waitUntil: 'domcontentloaded', timeout: 15_000 });
        await page.locator('input[name="login"]').fill(fixture.email, { timeout: 8_000 });
        await page.locator('input[name="password"]').fill(fixturePassword, { timeout: 8_000 });
        await page.locator('form[data-auth-form] button[type="submit"]').click({ timeout: 8_000 });
        await expect(page).toHaveURL(/\/en\/?$/, { timeout: 15_000 });

        return { context, page, capture };
    } catch (error) {
        await context.close().catch(() => undefined);
        throw error;
    }
}

async function loginVendor(browser: Browser, fixture: VendorFixture): Promise<{ context: BrowserContext; page: Page; capture: ReturnType<typeof networkCapture> }> {
    const context = await browser.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
    try {
        const page = await context.newPage();
        const capture = networkCapture(page);
        await page.goto('/vendor-portal/login', { waitUntil: 'domcontentloaded', timeout: 15_000 });
        await page.locator('[wire\\:model="data.email"]').fill(fixture.email, { timeout: 8_000 });
        await page.locator('[wire\\:model="data.password"]').fill(fixturePassword, { timeout: 8_000 });
        await page.getByRole('button', { name: /sign in|تسجيل الدخول/i }).click({ timeout: 8_000 });
        await expect(page).toHaveURL(/\/vendor-portal\/?$/, { timeout: 15_000 });
        await page.waitForLoadState('domcontentloaded', { timeout: 10_000 });

        return { context, page, capture };
    } catch (error) {
        await context.close().catch(() => undefined);
        throw error;
    }
}

test('captures redacted response privacy, ownership, request sizes, and list pagination across roles', async ({ browser, page }) => {
    test.setTimeout(240_000);
    let fixtures: AuditFixture | undefined;
    const browserContexts: BrowserContext[] = [];
    const apiContexts: APIRequestContext[] = [];

    try {
        fixtures = await boundedStep('seed bounded synthetic customer and vendor fixtures', 35_000, async () => seedFixtures());

        const customerA = fixtures.customers.a;
        const customerB = fixtures.customers.b;
        const vendorA = fixtures.vendors.a;
        const vendorB = fixtures.vendors.b;
        const privateVendorMarkers: Marker[] = [
            ...vendorA.privateMarkers.map((value, index) => ({ label: `vendor-a-private-${index + 1}`, value })),
            ...vendorB.privateMarkers.map((value, index) => ({ label: `vendor-b-private-${index + 1}`, value })),
        ];
        const customerMarkers: Marker[] = [
            { label: 'customer-a-name', value: customerA.name },
            { label: 'customer-a-email', value: customerA.email },
            { label: 'customer-a-phone', value: customerA.phone },
            { label: 'customer-b-name', value: customerB.name },
            { label: 'customer-b-email', value: customerB.email },
            { label: 'customer-b-phone', value: customerB.phone },
        ];
        const vendorIdentityMarkers: Marker[] = [
            { label: 'vendor-a-name', value: vendorA.name },
            { label: 'vendor-a-email', value: vendorA.email },
            { label: 'vendor-a-phone', value: vendorA.phone },
            { label: 'vendor-b-name', value: vendorB.name },
            { label: 'vendor-b-email', value: vendorB.email },
            { label: 'vendor-b-phone', value: vendorB.phone },
        ];
        const audit: Record<string, unknown> = {
            method: 'Playwright browser network capture and APIRequestContext; same-origin browser responses only; response bodies were inspected in memory and not attached or printed',
            privacy: { rawBodiesPersisted: false, credentialsOrTokenValuesPrinted: false, externalOriginsCaptured: false },
        };

        const adminCapture = networkCapture(page);
        const adminUsers = await visitPage(page, adminCapture, 'admin users list', '/admin/users');
        expect(adminUsers.status).toBe(200);
        expect(adminUsers.pageMetrics.adminTableRows).toBeGreaterThan(0);
        const adminUserMarkers = await inspectPageMarkers(page, [
            { label: 'customer-a-raw-password', value: fixturePassword },
            { label: 'customer-a-api-token', value: customerA.token },
            { label: 'vendor-a-api-token', value: vendorA.token },
        ]);
        expect(adminUserMarkers['customer-a-raw-password']).toBe(false);
        expect(adminUserMarkers['customer-a-api-token']).toBe(false);
        expect(adminUserMarkers['vendor-a-api-token']).toBe(false);
        audit.adminUsers = { ...adminUsers, markerPresence: adminUserMarkers };

        const adminVendors = await visitPage(page, adminCapture, 'admin vendor profiles list', '/admin/vendor-profiles');
        expect(adminVendors.status).toBe(200);
        expect(adminVendors.pageMetrics.adminTableRows).toBeGreaterThan(0);
        const adminVendorMarkers = await inspectPageMarkers(page, [
            ...privateVendorMarkers,
            { label: 'customer-a-raw-password', value: fixturePassword },
            { label: 'customer-a-api-token', value: customerA.token },
        ]);
        expect(Object.values(adminVendorMarkers).every((present) => !present)).toBe(true);
        const adminPagination = await boundedStep('measure admin vendor pagination transition', 20_000, async () => {
            const firstRow = await page.locator('.fi-ta-row').first().innerText().catch(() => '');
            const next = page.locator('button[aria-label*="Next page"], button[aria-label*="next page"], button[aria-label*="Next"]');
            const available = (await next.count()) > 0 && await next.first().isEnabled().catch(() => false);
            let transitioned = false;
            let livewire: ReturnType<typeof adminCapture.mark> | null = null;
            if (available) {
                livewire = adminCapture.mark();
                const response = page.waitForResponse((item) => item.url().includes('/livewire/update'), { timeout: 8_000 }).catch(() => null);
                await next.first().click({ timeout: 5_000 });
                await response;
                await adminCapture.flush();
                const nextFirstRow = await page.locator('.fi-ta-row').first().innerText().catch(() => '');
                transitioned = nextFirstRow.length > 0 && nextFirstRow !== firstRow;
            }

            return {
                controlAvailable: available,
                firstPageRows: adminVendors.pageMetrics.adminTableRows,
                secondPageRows: transitioned ? await page.locator('.fi-ta-row').count() : null,
                firstRowChanged: transitioned,
                network: livewire === null ? null : adminCapture.summarize(livewire),
            };
        });
        if (adminPagination.controlAvailable) expect(adminPagination.firstRowChanged).toBe(true);
        audit.adminVendorList = { ...adminVendors, markerPresence: adminVendorMarkers, paginationTransition: adminPagination };

        const publicContext = await browser.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
        browserContexts.push(publicContext);
        const publicPage = await publicContext.newPage();
        const publicCapture = networkCapture(publicPage);
        const publicSearch = encodeURIComponent(`QA Payload Audit ${fixturePrefix}`);
        const publicRegistrationPage = await visitPage(publicPage, publicCapture, 'public customer registration page', '/en/auth/register');
        expect(publicRegistrationPage.status).toBe(200);
        const registrationPageMarkers = await inspectPageMarkers(publicPage, [
            ...customerMarkers,
            ...vendorIdentityMarkers,
            ...privateVendorMarkers,
        ]);
        expect(Object.values(registrationPageMarkers).every((present) => !present)).toBe(true);

        const publicFirstPage = await visitPage(publicPage, publicCapture, 'public vendor list page one', `/en/vendors?q=${publicSearch}&sort=newest`);
        expect(publicFirstPage.status).toBe(200);
        expect(publicFirstPage.pageMetrics.vendorCardRows).toBe(12);
        expect(publicFirstPage.pageMetrics.paginationLabelPresent).toBe(true);
        const publicFirstMarkers = await inspectPageMarkers(publicPage, [
            ...customerMarkers,
            ...privateVendorMarkers,
        ]);
        expect(Object.values(publicFirstMarkers).every((present) => !present)).toBe(true);

        const publicSecondPage = await visitPage(publicPage, publicCapture, 'public vendor list page two', `/en/vendors?q=${publicSearch}&sort=newest&page=2`);
        expect(publicSecondPage.status).toBe(200);
        expect(publicSecondPage.pageMetrics.vendorCardRows).toBe(3);
        expect(publicFirstPage.pageMetrics.vendorCardRows + publicSecondPage.pageMetrics.vendorCardRows).toBe(fixtures.publicVendorCount);
        const publicSecondMarkers = await inspectPageMarkers(publicPage, [
            ...customerMarkers,
            ...privateVendorMarkers,
        ]);
        expect(Object.values(publicSecondMarkers).every((present) => !present)).toBe(true);

        const publicProfile = await visitPage(publicPage, publicCapture, 'public vendor profile', `/en/vendors/${vendorA.slug}`);
        expect(publicProfile.status).toBe(200);
        const publicProfileMarkers = await inspectPageMarkers(publicPage, [
            { label: 'public-vendor-name', value: 'QA Payload Audit' },
            ...customerMarkers,
            ...privateVendorMarkers,
            ...vendorIdentityMarkers,
        ]);
        expect(publicProfileMarkers['public-vendor-name']).toBe(true);
        for (const marker of ['customer-a-name', 'customer-a-email', 'customer-a-phone', 'customer-b-name', 'customer-b-email', 'customer-b-phone',
            'vendor-a-email', 'vendor-a-phone', 'vendor-b-email', 'vendor-b-phone',
            ...privateVendorMarkers.map(({ label }) => label)]) {
            expect(publicProfileMarkers[marker]).toBe(false);
        }
        audit.public = {
            registrationPage: { ...publicRegistrationPage, markerPresence: registrationPageMarkers },
            firstPage: { ...publicFirstPage, markerPresence: publicFirstMarkers },
            secondPage: { ...publicSecondPage, markerPresence: publicSecondMarkers },
            vendorProfile: { ...publicProfile, markerPresence: publicProfileMarkers },
        };

        const registrationApi = await makeApiContext();
        apiContexts.push(registrationApi);
        const registration = await boundedStep('inspect customer registration response', 20_000, () => apiEvidence(
            registrationApi,
            'customer registration',
            'POST',
            '/api/v1/register/customer',
            [
                { label: 'registration-name-present', value: fixtures!.registration.name },
                { label: 'other-customer-name-present', value: customerB.name },
                { label: 'raw-password-present', value: fixturePassword },
            ],
            {
                name: fixtures.registration.name,
                phone_e164: fixtures.registration.phone,
                email: fixtures.registration.email,
                password: fixturePassword,
                password_confirmation: fixturePassword,
                preferred_locale: 'en',
                accepted_terms: '1',
            },
        ));
        expect(registration.status).toBe(201);
        expect(registration.forbiddenFields).not.toContain('data.token');
        expect(registration.forbiddenFields).not.toContain('data.password');
        expect(registration.tokenPresent).toBe(false);
        expect(registration.markerPresence['registration-name-present']).toBe(true);
        expect(registration.markerPresence['other-customer-name-present']).toBe(false);
        expect(registration.markerPresence['raw-password-present']).toBe(false);

        const customerApiA = await makeApiContext(customerA.token);
        const customerApiB = await makeApiContext(customerB.token);
        apiContexts.push(customerApiA, customerApiB);
        const customerProfileA = await boundedStep('inspect customer A profile API response', 20_000, () => apiEvidence(
            customerApiA,
            'customer A profile',
            'GET',
            '/api/v1/customer/profile',
            [
                { label: 'owner-name-present', value: customerA.name },
                { label: 'other-customer-name-present', value: customerB.name },
                { label: 'other-customer-email-present', value: customerB.email },
                { label: 'other-customer-phone-present', value: customerB.phone },
            ],
        ));
        expect(customerProfileA.status).toBe(200);
        expect(customerProfileA.forbiddenFields).toEqual([]);
        expect(customerProfileA.markerPresence['owner-name-present']).toBe(true);
        expect(customerProfileA.markerPresence['other-customer-name-present']).toBe(false);
        expect(customerProfileA.markerPresence['other-customer-email-present']).toBe(false);
        expect(customerProfileA.markerPresence['other-customer-phone-present']).toBe(false);

        const customerProfileB = await boundedStep('inspect customer B profile API response', 20_000, () => apiEvidence(
            customerApiB,
            'customer B profile',
            'GET',
            '/api/v1/customer/profile',
            [
                { label: 'owner-name-present', value: customerB.name },
                { label: 'other-customer-name-present', value: customerA.name },
                { label: 'other-customer-email-present', value: customerA.email },
                { label: 'other-customer-phone-present', value: customerA.phone },
            ],
        ));
        expect(customerProfileB.status).toBe(200);
        expect(customerProfileB.forbiddenFields).toEqual([]);
        expect(customerProfileB.markerPresence['owner-name-present']).toBe(true);
        expect(customerProfileB.markerPresence['other-customer-name-present']).toBe(false);
        expect(customerProfileB.markerPresence['other-customer-email-present']).toBe(false);
        expect(customerProfileB.markerPresence['other-customer-phone-present']).toBe(false);

        const customerAddressA = await boundedStep('inspect customer A address list and ownership', 20_000, () => apiEvidence(
            customerApiA,
            'customer A addresses',
            'GET',
            '/api/v1/customer/addresses',
            [
                { label: 'owner-address-present', value: customerA.addressMarker! },
                { label: 'other-customer-address-present', value: customerB.addressMarker! },
            ],
        ));
        expect(customerAddressA.status).toBe(200);
        expect(customerAddressA.rows).toBe(customerA.addressCount);
        expect(customerAddressA.forbiddenFields).toEqual([]);
        expect(customerAddressA.markerPresence['owner-address-present']).toBe(true);
        expect(customerAddressA.markerPresence['other-customer-address-present']).toBe(false);

        const customerAddressB = await boundedStep('inspect customer B address list and ownership', 20_000, () => apiEvidence(
            customerApiB,
            'customer B addresses',
            'GET',
            '/api/v1/customer/addresses',
            [
                { label: 'owner-address-present', value: customerB.addressMarker! },
                { label: 'other-customer-address-present', value: customerA.addressMarker! },
            ],
        ));
        expect(customerAddressB.status).toBe(200);
        expect(customerAddressB.rows).toBe(customerB.addressCount);
        expect(customerAddressB.forbiddenFields).toEqual([]);
        expect(customerAddressB.markerPresence['owner-address-present']).toBe(true);
        expect(customerAddressB.markerPresence['other-customer-address-present']).toBe(false);

        const customerUpdate = await boundedStep('inspect customer account update response', 20_000, () => apiEvidence(
            customerApiA,
            'customer profile update',
            'PUT',
            '/api/v1/customer/profile',
            [
                { label: 'owner-name-present', value: customerA.name },
                { label: 'other-customer-name-present', value: customerB.name },
                { label: 'raw-password-present', value: fixturePassword },
            ],
            { name: customerA.name },
        ));
        expect(customerUpdate.status).toBe(200);
        expect(customerUpdate.forbiddenFields).toEqual([]);
        expect(customerUpdate.markerPresence['owner-name-present']).toBe(true);
        expect(customerUpdate.markerPresence['other-customer-name-present']).toBe(false);
        expect(customerUpdate.markerPresence['raw-password-present']).toBe(false);

        const customerRoleBoundary = await apiEvidence(customerApiA, 'customer token at vendor endpoint', 'GET', '/api/v1/vendor/me');
        expect(customerRoleBoundary.status).toBe(403);

        const vendorApiA = await makeApiContext(vendorA.token);
        const vendorApiB = await makeApiContext(vendorB.token);
        apiContexts.push(vendorApiA, vendorApiB);
        const vendorMeA = await boundedStep('inspect vendor A identity API response', 20_000, () => apiEvidence(
            vendorApiA,
            'vendor A identity',
            'GET',
            '/api/v1/vendor/me',
            [
                { label: 'owner-name-present', value: vendorA.name },
                { label: 'other-vendor-name-present', value: vendorB.name },
                { label: 'other-vendor-email-present', value: vendorB.email },
                ...vendorA.privateMarkers.map((value, index) => ({ label: `owner-private-field-${index + 1}`, value })),
                ...vendorB.privateMarkers.map((value, index) => ({ label: `other-private-field-${index + 1}`, value })),
            ],
        ));
        expect(vendorMeA.status).toBe(200);
        expect(vendorMeA.forbiddenFields).toEqual([]);
        expect(vendorMeA.markerPresence['owner-name-present']).toBe(true);
        expect(vendorMeA.markerPresence['other-vendor-name-present']).toBe(false);
        expect(vendorMeA.markerPresence['other-vendor-email-present']).toBe(false);
        for (const marker of vendorA.privateMarkers.map((_, index) => `owner-private-field-${index + 1}`)
            .concat(vendorB.privateMarkers.map((_, index) => `other-private-field-${index + 1}`))) {
            expect(vendorMeA.markerPresence[marker]).toBe(false);
        }

        const vendorProfileA = await boundedStep('inspect vendor A profile API response', 20_000, () => apiEvidence(
            vendorApiA,
            'vendor A profile',
            'GET',
            '/api/v1/vendor/profile',
            [
                { label: 'owner-business-name-present', value: 'QA Payload Audit' },
                { label: 'other-vendor-name-present', value: vendorB.name },
                ...vendorA.privateMarkers.map((value, index) => ({ label: `owner-private-field-${index + 1}`, value })),
                ...vendorB.privateMarkers.map((value, index) => ({ label: `other-private-field-${index + 1}`, value })),
            ],
        ));
        expect(vendorProfileA.status).toBe(200);
        expect(vendorProfileA.forbiddenFields).toEqual([]);
        expect(vendorProfileA.markerPresence['owner-business-name-present']).toBe(true);
        expect(vendorProfileA.markerPresence['other-vendor-name-present']).toBe(false);
        for (const marker of vendorA.privateMarkers.map((_, index) => `owner-private-field-${index + 1}`)
            .concat(vendorB.privateMarkers.map((_, index) => `other-private-field-${index + 1}`))) {
            expect(vendorProfileA.markerPresence[marker]).toBe(false);
        }

        const vendorMeB = await boundedStep('inspect vendor B identity API response', 20_000, () => apiEvidence(
            vendorApiB,
            'vendor B identity',
            'GET',
            '/api/v1/vendor/me',
            [
                { label: 'owner-name-present', value: vendorB.name },
                { label: 'other-vendor-name-present', value: vendorA.name },
                { label: 'other-vendor-email-present', value: vendorA.email },
                ...vendorA.privateMarkers.map((value, index) => ({ label: `other-private-field-${index + 1}`, value })),
                ...vendorB.privateMarkers.map((value, index) => ({ label: `owner-private-field-${index + 1}`, value })),
            ],
        ));
        expect(vendorMeB.status).toBe(200);
        expect(vendorMeB.forbiddenFields).toEqual([]);
        expect(vendorMeB.markerPresence['owner-name-present']).toBe(true);
        expect(vendorMeB.markerPresence['other-vendor-name-present']).toBe(false);
        expect(vendorMeB.markerPresence['other-vendor-email-present']).toBe(false);

        const vendorRoleBoundary = await apiEvidence(vendorApiA, 'vendor token at customer endpoint', 'GET', '/api/v1/customer/profile');
        expect(vendorRoleBoundary.status).toBe(403);
        const vendorPublicProfile = await boundedStep('inspect customer-facing vendor profile API response', 20_000, () => apiEvidence(
            customerApiA,
            'public vendor profile API',
            'GET',
            `/api/v1/customer/vendors/${vendorA.profilePublicId}`,
            [
                { label: 'public-business-name-present', value: 'QA Payload Audit' },
                ...vendorA.privateMarkers.map((value, index) => ({ label: `vendor-private-field-${index + 1}`, value })),
            ],
        ));
        expect(vendorPublicProfile.status).toBe(200);
        expect(vendorPublicProfile.forbiddenFields).toEqual([]);
        expect(vendorPublicProfile.markerPresence['public-business-name-present']).toBe(true);
        for (const marker of vendorA.privateMarkers.map((_, index) => `vendor-private-field-${index + 1}`)) {
            expect(vendorPublicProfile.markerPresence[marker]).toBe(false);
        }

        const loginApi = await boundedStep('inspect API login response field names without printing its token', 20_000, () => apiEvidence(
            registrationApi,
            'customer API login',
            'POST',
            '/api/v1/login',
            [
                { label: 'owner-name-present', value: customerA.name },
                { label: 'other-customer-name-present', value: customerB.name },
                { label: 'raw-password-present', value: fixturePassword },
            ],
            { login: customerA.email, password: fixturePassword, device_name: 'qa-payload-audit' },
        ));
        expect(loginApi.status).toBe(200);
        expect(loginApi.tokenPresent).toBe(true);
        expect(loginApi.markerPresence['owner-name-present']).toBe(true);
        expect(loginApi.markerPresence['other-customer-name-present']).toBe(false);
        expect(loginApi.markerPresence['raw-password-present']).toBe(false);
        expect(loginApi.forbiddenFields.filter((field) => !field.endsWith('.token'))).toEqual([]);

        const customerAWeb = await boundedStep('log in customer A through the storefront', 25_000, () => loginCustomer(browser, customerA));
        browserContexts.push(customerAWeb.context);
        const customerProfilePageA = await visitPage(customerAWeb.page, customerAWeb.capture, 'customer A account profile', '/en/account/profile');
        expect(customerProfilePageA.status).toBe(200);
        await expect(customerAWeb.page.locator('input[name="name"]')).toHaveValue(customerA.name);
        const customerAWebMarkers = await inspectPageMarkers(customerAWeb.page, [
            { label: 'other-customer-name', value: customerB.name },
            { label: 'other-customer-email', value: customerB.email },
            { label: 'other-customer-phone', value: customerB.phone },
            ...privateVendorMarkers,
        ]);
        expect(Object.values(customerAWebMarkers).every((present) => !present)).toBe(true);

        const customerBWeb = await boundedStep('log in customer B through the storefront', 25_000, () => loginCustomer(browser, customerB));
        browserContexts.push(customerBWeb.context);
        const customerProfilePageB = await visitPage(customerBWeb.page, customerBWeb.capture, 'customer B account profile', '/en/account/profile');
        expect(customerProfilePageB.status).toBe(200);
        await expect(customerBWeb.page.locator('input[name="name"]')).toHaveValue(customerB.name);
        const customerBWebMarkers = await inspectPageMarkers(customerBWeb.page, [
            { label: 'other-customer-name', value: customerA.name },
            { label: 'other-customer-email', value: customerA.email },
            { label: 'other-customer-phone', value: customerA.phone },
            ...privateVendorMarkers,
        ]);
        expect(Object.values(customerBWebMarkers).every((present) => !present)).toBe(true);
        audit.customer = {
            profilePageA: { ...customerProfilePageA, markerPresence: customerAWebMarkers },
            profilePageB: { ...customerProfilePageB, markerPresence: customerBWebMarkers },
            profileApiA: customerProfileA,
            profileApiB: customerProfileB,
            addressesA: customerAddressA,
            addressesB: customerAddressB,
            updateResponse: customerUpdate,
            registrationResponse: registration,
            loginResponse: { ...loginApi, tokenValueRedacted: true },
            customerToVendorStatus: customerRoleBoundary.status,
        };

        const vendorAWeb = await boundedStep('log in vendor A through the vendor panel', 25_000, () => loginVendor(browser, vendorA));
        browserContexts.push(vendorAWeb.context);
        const vendorProfilePageA = await visitPage(vendorAWeb.page, vendorAWeb.capture, 'vendor A profile panel', '/vendor-portal/vendor-profile-page?locale=en&lang=en');
        expect(vendorProfilePageA.status).toBe(200);
        const vendorAWebMarkers = await inspectPageMarkers(vendorAWeb.page, [
            { label: 'other-vendor-name', value: vendorB.name },
            { label: 'other-vendor-email', value: vendorB.email },
            { label: 'other-vendor-phone', value: vendorB.phone },
            { label: 'customer-a-name', value: customerA.name },
            { label: 'customer-b-name', value: customerB.name },
        ]);
        expect(Object.values(vendorAWebMarkers).every((present) => !present)).toBe(true);

        const vendorBWeb = await boundedStep('log in vendor B through the vendor panel', 25_000, () => loginVendor(browser, vendorB));
        browserContexts.push(vendorBWeb.context);
        const vendorProfilePageB = await visitPage(vendorBWeb.page, vendorBWeb.capture, 'vendor B profile panel', '/vendor-portal/vendor-profile-page?locale=en&lang=en');
        expect(vendorProfilePageB.status).toBe(200);
        const vendorBWebMarkers = await inspectPageMarkers(vendorBWeb.page, [
            { label: 'other-vendor-name', value: vendorA.name },
            { label: 'other-vendor-email', value: vendorA.email },
            { label: 'other-vendor-phone', value: vendorA.phone },
            { label: 'customer-a-name', value: customerA.name },
            { label: 'customer-b-name', value: customerB.name },
        ]);
        expect(Object.values(vendorBWebMarkers).every((present) => !present)).toBe(true);
        audit.vendor = {
            profilePageA: { ...vendorProfilePageA, markerPresence: vendorAWebMarkers },
            profilePageB: { ...vendorProfilePageB, markerPresence: vendorBWebMarkers },
            identityApiA: vendorMeA,
            profileApiA: vendorProfileA,
            identityApiB: vendorMeB,
            customerToVendorStatus: customerRoleBoundary.status,
            vendorToCustomerStatus: vendorRoleBoundary.status,
            publicProfileApi: vendorPublicProfile,
        };

        console.log(`QA_PAYLOAD_AUDIT ${JSON.stringify(audit)}`);
    } finally {
        for (const context of browserContexts) await context.close().catch(() => undefined);
        for (const context of apiContexts) await context.dispose().catch(() => undefined);
    }
});
