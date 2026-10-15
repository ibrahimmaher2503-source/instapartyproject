import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test, type Page } from '@playwright/test';

const runId = Date.now().toString();
const olderReference = `QO${runId}O`;
const newerReference = `QO${runId}N`;
const eligibleVendorName = `QA Admin Operations Eligible ${runId}`;
const ineligibleVendorName = `QA Admin Operations Ineligible ${runId}`;
const eligibleVendorEmail = `qa-admin-ops-${runId}-eligible@example.test`;
const ineligibleVendorEmail = `qa-admin-ops-${runId}-ineligible@example.test`;
const olderCustomerEmail = `qa-admin-ops-${runId}-older@example.test`;
const newerCustomerEmail = `qa-admin-ops-${runId}-newer@example.test`;
const olderWithdrawalVendor = `QA Admin Operations Withdrawal Old ${runId}`;
const newerWithdrawalVendor = `QA Admin Operations Withdrawal New ${runId}`;
const olderWithdrawalVendorEmail = `qa-admin-ops-${runId}-withdrawal-old@example.test`;
const newerWithdrawalVendorEmail = `qa-admin-ops-${runId}-withdrawal-new@example.test`;
const olderReportReporter = `QA Admin Operations Report Old ${runId}`;
const newerReportReporter = `QA Admin Operations Report New ${runId}`;
const terminalReportReporter = `QA Admin Operations Report Resolved ${runId}`;
const olderSupportSubject = `QA Admin Operations Support Old ${runId}`;
const newerSupportSubject = `QA Admin Operations Support New ${runId}`;
const terminalSupportSubject = `QA Admin Operations Support Closed ${runId}`;
const extraPermissions = ['view_any_booking', 'view_any_bookings::monitor', 'view_any_report', 'view_report'];
let preexistingDirectPermissions: Set<string> | null = null;

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
    const wrapped = `try { ${execute}\necho "\\nQA_OPS_COMMAND_OK"; } catch (\\Throwable $error) { echo "\\nQA_OPS_COMMAND_ERROR:".$error::class.":".$error->getMessage(); }`;
    const output = execFileSync('php', ['artisan', 'tinker', `--execute=${wrapped}`], {
        env: environment,
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
        timeout: 30_000,
    });
    if (!output.includes('QA_OPS_COMMAND_OK')) {
        throw new Error(output.match(/QA_OPS_COMMAND_ERROR:([^\r\n]+)/)?.[1] ?? 'The admin operations fixture command did not complete.');
    }
    return output.replace(/\s*QA_OPS_COMMAND_OK\s*$/, '');
}

function seedFixtures(): void {
    const directPermissions = runPhp(`echo json_encode(App\\Modules\\Identity\\Domain\\Models\\User::query()
        ->where('email','uat-admin@example.test')->firstOrFail()
        ->getDirectPermissions()->pluck('name')->all());`);
    const permissionJson = directPermissions.trim().split(/\r?\n/).at(-1);
    if (!permissionJson) throw new Error('Could not read the admin direct permissions before fixture setup.');
    preexistingDirectPermissions = new Set<string>(JSON.parse(permissionJson));

    const execute = `$admin=App\\Modules\\Identity\\Domain\\Models\\User::query()->where('email','uat-admin@example.test')->firstOrFail();
        foreach (${JSON.stringify(extraPermissions)} as $name) { $admin->givePermissionTo(Spatie\\Permission\\Models\\Permission::findOrCreate($name,'web')); }
        $olderCustomer=App\\Modules\\Identity\\Domain\\Models\\User::factory()->create(['email'=>'${olderCustomerEmail}']);
        $newerCustomer=App\\Modules\\Identity\\Domain\\Models\\User::factory()->create(['email'=>'${newerCustomerEmail}']);
        $older=App\\Modules\\Booking\\Domain\\Models\\Booking::factory()->create([
            'customer_id'=>$olderCustomer->id,
            'reference_no'=>'${olderReference}',
            'lifecycle_status'=>App\\Modules\\Booking\\Domain\\Enums\\LifecycleStatus::VendorReview->value,
            'submitted_at'=>now('UTC')->subDays(4),
        ]);
        $newer=App\\Modules\\Booking\\Domain\\Models\\Booking::factory()->create([
            'customer_id'=>$newerCustomer->id,
            'reference_no'=>'${newerReference}',
            'lifecycle_status'=>App\\Modules\\Booking\\Domain\\Enums\\LifecycleStatus::CustomerReview->value,
            'submitted_at'=>now('UTC')->subHours(6),
        ]);
        $recordTransition=static function ($booking,$from,$to,$at) {
            $transition=App\\Modules\\Shared\\Domain\\Models\\StateTransition::query()->create([
                'transitionable_type'=>App\\Modules\\Booking\\Domain\\Models\\Booking::class,
                'transitionable_id'=>$booking->id,
                'from_state'=>$from,
                'to_state'=>$to,
                'trigger_kind'=>'system',
            ]);
            $transition->forceFill(['created_at'=>$at])->saveQuietly();
        };
        $recordTransition($older,'draft','vendor_review',$older->submitted_at);
        $recordTransition($newer,'customer_review','vendor_review',now('UTC')->subHours(3));
        $governorate=App\\Modules\\Geography\\Domain\\Models\\Governorate::query()->where('code','EG-CAI')->firstOrFail();
        $city=App\\Modules\\Geography\\Domain\\Models\\City::query()->where('governorate_id',$governorate->id)->firstOrFail();
        $createVendor=static function ($name,$email,$hasHours,$ageHours) use ($governorate,$city) {
            $user=App\\Modules\\Identity\\Domain\\Models\\User::factory()->phoneVerified()->asVendor()->create([
                'name'=>'QA Admin Operations Vendor',
                'email'=>$email,
                'email_verified_at'=>now(),
            ]);
            $profile=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->pending()->create([
                'user_id'=>$user->id,
                'business_name'=>['en'=>$name,'ar'=>$name.' AR'],
                'address_line'=>['en'=>'QA address','ar'=>'عنوان اختبار'],
                'business_type'=>'individual',
                'national_id'=>'29901011234567',
                'primary_governorate_id'=>$governorate->id,
                'primary_city_id'=>$city->id,
                'bank_name'=>'QA Bank',
                'bank_account_holder'=>'QA Admin Operations Vendor',
                'bank_iban'=>'EG380019000500000000263180002',
                'created_at'=>now('UTC')->subHours($ageHours),
                'updated_at'=>now('UTC')->subHours($ageHours),
            ]);
            if ($hasHours) { App\\Modules\\Identity\\Domain\\Models\\VendorBusinessHour::factory()->create(['vendor_profile_id'=>$profile->id]); }
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
        };
        $createVendor('${eligibleVendorName}','${eligibleVendorEmail}',true,72);
        $createVendor('${ineligibleVendorName}','${ineligibleVendorEmail}',false,24);
        $olderWithdrawalVendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create([
            'primary_governorate_id'=>$governorate->id,'primary_city_id'=>$city->id,
            'business_name'=>['en'=>'${olderWithdrawalVendor}','ar'=>'${olderWithdrawalVendor} AR'],
            'created_at'=>now('UTC')->subHours(72),'updated_at'=>now('UTC')->subHours(72),
        ]);
        $olderWithdrawalVendor->user->update(['email'=>'${olderWithdrawalVendorEmail}']);
        $newerWithdrawalVendor=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::factory()->approved()->create([
            'primary_governorate_id'=>$governorate->id,'primary_city_id'=>$city->id,
            'business_name'=>['en'=>'${newerWithdrawalVendor}','ar'=>'${newerWithdrawalVendor} AR'],
            'created_at'=>now('UTC')->subHours(24),'updated_at'=>now('UTC')->subHours(24),
        ]);
        $newerWithdrawalVendor->user->update(['email'=>'${newerWithdrawalVendorEmail}']);
        App\\Modules\\Settlement\\Database\\Factories\\WithdrawalFactory::new()->pending()->create([
            'vendor_profile_id'=>$olderWithdrawalVendor->id,'requested_by_user_id'=>$olderWithdrawalVendor->user_id,
            'pending_lock'=>$olderWithdrawalVendor->id,
            'requested_at'=>now('UTC')->subHours(72),'created_at'=>now('UTC')->subHours(72),
        ]);
        App\\Modules\\Settlement\\Database\\Factories\\WithdrawalFactory::new()->approved()->create([
            'vendor_profile_id'=>$newerWithdrawalVendor->id,'requested_by_user_id'=>$newerWithdrawalVendor->user_id,
            'approved_by_admin_id'=>$admin->id,'requested_at'=>now('UTC')->subHours(24),'created_at'=>now('UTC')->subHours(24),
        ]);
        $makeReport=static function ($name,$status,$ageHours) {
            $reporter=App\\Modules\\Identity\\Domain\\Models\\User::factory()->create(['name'=>$name,'email'=>strtolower(str_replace(' ','-', $name)).'@example.test']);
            $report=App\\Modules\\TrustSafety\\Domain\\Models\\Report::query()->create([
                'reporter_id'=>$reporter->id,'reportable_type'=>App\\Modules\\Identity\\Domain\\Models\\User::class,
                'reportable_id'=>$reporter->id,'reason'=>App\\Modules\\TrustSafety\\Domain\\Enums\\ReportReason::Harassment->value,
                'details'=>'QA Admin Operations queue fixture ${runId}','status'=>$status,
            ]);
            $report->forceFill(['created_at'=>now('UTC')->subHours($ageHours),'updated_at'=>now('UTC')->subHours($ageHours)])->saveQuietly();
        };
        $makeReport('${olderReportReporter}',App\\Modules\\TrustSafety\\Domain\\Enums\\ReportStatus::Open->value,72);
        $makeReport('${newerReportReporter}',App\\Modules\\TrustSafety\\Domain\\Enums\\ReportStatus::UnderReview->value,24);
        $makeReport('${terminalReportReporter}',App\\Modules\\TrustSafety\\Domain\\Enums\\ReportStatus::Resolved->value,240);
        $makeTicket=static function ($subject,$status,$ageHours) {
            $ticket=App\\Modules\\Support\\Domain\\Models\\SupportTicket::query()->create([
                'subject'=>$subject,'body'=>'QA Admin Operations queue fixture ${runId}',
                'email'=>'qa-admin-operations-${runId}@example.test','status'=>$status,
            ]);
            $ticket->forceFill(['created_at'=>now('UTC')->subHours($ageHours),'updated_at'=>now('UTC')->subHours($ageHours)])->saveQuietly();
        };
        $makeTicket('${olderSupportSubject}',App\\Modules\\Support\\Domain\\Enums\\SupportTicketStatus::Open->value,72);
        $makeTicket('${newerSupportSubject}',App\\Modules\\Support\\Domain\\Enums\\SupportTicketStatus::InProgress->value,24);
        $makeTicket('${terminalSupportSubject}',App\\Modules\\Support\\Domain\\Enums\\SupportTicketStatus::Closed->value,240);`;

    runPhp(execute);
}

function cleanupFixtures(): void {
    const capturedPermissions = preexistingDirectPermissions;
    const execute = `$bookings=App\\Modules\\Booking\\Domain\\Models\\Booking::query()->whereIn('reference_no',${JSON.stringify([olderReference, newerReference])})->get();
        App\\Modules\\Shared\\Domain\\Models\\StateTransition::query()
            ->where('transitionable_type',App\\Modules\\Booking\\Domain\\Models\\Booking::class)
            ->whereIn('transitionable_id',$bookings->pluck('id'))
            ->delete();
        foreach ($bookings as $booking) { $booking->forceDelete(); }
        App\\Modules\\Identity\\Domain\\Models\\User::query()->whereIn('email',${JSON.stringify([olderCustomerEmail, newerCustomerEmail])})->forceDelete();
        $profiles=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::query()->whereIn('business_name->en',${JSON.stringify([eligibleVendorName, ineligibleVendorName, olderWithdrawalVendor, newerWithdrawalVendor])})->get();
        App\\Modules\\Settlement\\Domain\\Models\\Withdrawal::query()->whereIn('vendor_profile_id',$profiles->pluck('id'))->delete();
        $reporters=App\\Modules\\Identity\\Domain\\Models\\User::query()->whereIn('name',${JSON.stringify([olderReportReporter, newerReportReporter, terminalReportReporter])})->get();
        App\\Modules\\TrustSafety\\Domain\\Models\\Report::query()->whereIn('reporter_id',$reporters->pluck('id'))->delete();
        foreach ($reporters as $reporter) { $reporter->forceDelete(); }
        App\\Modules\\Support\\Domain\\Models\\SupportTicket::query()->whereIn('subject',${JSON.stringify([olderSupportSubject, newerSupportSubject, terminalSupportSubject])})->delete();
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
        $admin=App\\Modules\\Identity\\Domain\\Models\\User::query()->where('email','uat-admin@example.test')->first();
        if ($admin !== null) {
            foreach (${JSON.stringify(extraPermissions)} as $name) {
                $permission=Spatie\\Permission\\Models\\Permission::query()->where('name',$name)->where('guard_name','web')->first();
                if ($permission !== null && ${capturedPermissions !== null ? 'true' : 'false'}
                    && ! in_array($name, ${JSON.stringify([...(capturedPermissions ?? [])])}, true)) {
                    $admin->revokePermissionTo($permission);
                }
            }
        }
        app(Spatie\\Permission\\PermissionRegistrar::class)->forgetCachedPermissions();`;

    runPhp(execute);
}

async function confirmBulkApproval(page: Page): Promise<void> {
    const button = page.getByRole('button', { name: 'Approve selected vendor profiles', exact: true });
    await expect(button).toBeVisible();
    await button.click();

    const dialog = page.locator('.fi-modal-window:visible').last();
    await expect(dialog).toContainText('Each selected profile will be checked against the existing eligibility rules.');
    await dialog.getByRole('button', { name: /^(Confirm|تأكيد)$/i }).click();
}

async function assertVendorStatus(name: string, expectedClass: 'ApprovedState' | 'PendingState'): Promise<void> {
    const stateClass = `App\\Modules\\Identity\\Domain\\States\\VendorApprovalStatus\\${expectedClass}`;
    runPhp(`$profile=App\\Modules\\Identity\\Domain\\Models\\VendorProfile::query()->where('business_name->en','${name}')->firstOrFail();
        if (!($profile->approval_status instanceof ${stateClass})) { throw new RuntimeException('Unexpected bulk approval state'); }`);
}

test.beforeAll(() => seedFixtures());
test.afterAll(() => cleanupFixtures());

test('shows operational booking metrics and applies bulk vendor approval safely', async ({ page }) => {
    test.setTimeout(120_000);

    await test.step('show the transition-based booking funnel and coverage', async () => {
        const response = await page.goto('/admin?lang=en');
        expect(response?.status()).toBe(200);

        const funnel = page.locator('section[aria-label="Booking conversion funnel"]');
        await expect(funnel).toBeVisible();
        await expect(funnel).toContainText('Initial submission transition coverage:');
        await expect(funnel).toContainText('Tracked submissions');
        await expect(funnel).toContainText('Confirmed');
    });

    await test.step('show oldest queue age first and download the filtered monitor export', async () => {
        const response = await page.goto('/admin/bookings-monitor?lang=en');
        expect(response?.status()).toBe(200);
        await expect(page.getByText('Queue age', { exact: true })).toBeVisible();

        const rowTexts = await page.getByRole('row').allTextContents();
        const olderIndex = rowTexts.findIndex((text) => text.includes(olderReference));
        const newerIndex = rowTexts.findIndex((text) => text.includes(newerReference));
        expect(olderIndex).toBeGreaterThanOrEqual(0);
        expect(newerIndex).toBeGreaterThan(olderIndex);

        const [download] = await Promise.all([
            page.waitForEvent('download'),
            page.getByRole('button', { name: 'Export filtered bookings', exact: true }).click(),
        ]);
        expect(download.suggestedFilename()).toMatch(/\.xlsx$/i);
    });

    await test.step('approve eligible selections and report the ineligible profile', async () => {
        const response = await page.goto('/admin/vendor-approval-queue?lang=en');
        expect(response?.status()).toBe(200);
        await expect(page.getByText('Queue age', { exact: true })).toBeVisible();
        await expect(page.getByText('SLA deadline', { exact: true })).toBeVisible();
        await expect(page.getByText('SLA status', { exact: true })).toBeVisible();
        await page.getByRole('searchbox', { name: 'Search', exact: true }).fill('QA Admin Operations');

        const eligibleRow = page.getByRole('row').filter({ hasText: eligibleVendorEmail }).first();
        const ineligibleRow = page.getByRole('row').filter({ hasText: ineligibleVendorEmail }).first();
        await expect(eligibleRow).toBeVisible();
        await expect(ineligibleRow).toBeVisible();
        const vendorRows = await page.getByRole('row').allTextContents();
        const olderVendorIndex = vendorRows.findIndex((text) => text.includes(eligibleVendorEmail));
        const newerVendorIndex = vendorRows.findIndex((text) => text.includes(ineligibleVendorEmail));
        expect(olderVendorIndex).toBeGreaterThanOrEqual(0);
        expect(newerVendorIndex).toBeGreaterThan(olderVendorIndex);
        await expect(eligibleRow).toContainText('Overdue');
        await expect(ineligibleRow).toContainText('Within SLA');
        await eligibleRow.getByRole('checkbox').check();
        await ineligibleRow.getByRole('checkbox').check();
        await confirmBulkApproval(page);

        const notification = page.locator('.fi-no').filter({ hasText: 'Selected vendor approval results' }).last();
        await expect(notification).toContainText('1 approved; 1 failed.');
        await expect(notification).toContainText('At least one business-hours entry is required.');
        await assertVendorStatus(eligibleVendorName, 'ApprovedState');
        await assertVendorStatus(ineligibleVendorName, 'PendingState');
    });

    await test.step('show oldest-first withdrawal work with its request-based SLA', async () => {
        const response = await page.goto('/admin/settlement-withdrawals?lang=en');
        expect(response?.status()).toBe(200);
        await expect(page.getByText('Queue age', { exact: true })).toBeVisible();
        await expect(page.getByText('SLA deadline', { exact: true })).toBeVisible();
        await expect(page.getByText('SLA status', { exact: true })).toBeVisible();

        const oldRow = page.getByRole('row').filter({ hasText: olderWithdrawalVendor }).first();
        const newRow = page.getByRole('row').filter({ hasText: newerWithdrawalVendor }).first();
        await expect(oldRow).toBeVisible();
        await expect(newRow).toBeVisible();
        const rows = await page.getByRole('row').allTextContents();
        const oldIndex = rows.findIndex((text) => text.includes(olderWithdrawalVendor));
        const newIndex = rows.findIndex((text) => text.includes(newerWithdrawalVendor));
        expect(oldIndex).toBeGreaterThanOrEqual(0);
        expect(newIndex).toBeGreaterThan(oldIndex);
        await expect(oldRow).toContainText('Overdue');
        await expect(newRow).toContainText('Within SLA');
    });

    await test.step('show oldest-first report and support work with deadline states', async () => {
        const reportsResponse = await page.goto('/admin/reports?lang=en');
        expect(reportsResponse?.status()).toBe(200);
        await expect(page.getByText('Queue age', { exact: true })).toBeVisible();
        await expect(page.getByText('SLA deadline', { exact: true })).toBeVisible();
        await expect(page.getByText('SLA status', { exact: true })).toBeVisible();

        const oldReport = page.getByRole('row').filter({ hasText: olderReportReporter }).first();
        const newReport = page.getByRole('row').filter({ hasText: newerReportReporter }).first();
        const terminalReport = page.getByRole('row').filter({ hasText: terminalReportReporter }).first();
        await expect(oldReport).toContainText('Overdue');
        await expect(newReport).toContainText('Within SLA');
        await expect(terminalReport).toContainText('Not in an active queue state');
        const reportRows = await page.getByRole('row').allTextContents();
        const oldReportIndex = reportRows.findIndex((text) => text.includes(olderReportReporter));
        const newReportIndex = reportRows.findIndex((text) => text.includes(newerReportReporter));
        const terminalReportIndex = reportRows.findIndex((text) => text.includes(terminalReportReporter));
        expect(oldReportIndex).toBeGreaterThanOrEqual(0);
        expect(newReportIndex).toBeGreaterThan(oldReportIndex);
        expect(terminalReportIndex).toBeGreaterThan(newReportIndex);

        const supportResponse = await page.goto('/admin/support-tickets?lang=en');
        expect(supportResponse?.status()).toBe(200);
        await expect(page.getByText('Queue age', { exact: true })).toBeVisible();
        await expect(page.getByText('SLA deadline', { exact: true })).toBeVisible();
        await expect(page.getByText('SLA status', { exact: true })).toBeVisible();

        const oldTicket = page.getByRole('row').filter({ hasText: olderSupportSubject }).first();
        const newTicket = page.getByRole('row').filter({ hasText: newerSupportSubject }).first();
        const terminalTicket = page.getByRole('row').filter({ hasText: terminalSupportSubject }).first();
        await expect(oldTicket).toContainText('Overdue');
        await expect(newTicket).toContainText('Within SLA');
        await expect(terminalTicket).toContainText('Not in an active queue state');
        const supportRows = await page.getByRole('row').allTextContents();
        const oldTicketIndex = supportRows.findIndex((text) => text.includes(olderSupportSubject));
        const newTicketIndex = supportRows.findIndex((text) => text.includes(newerSupportSubject));
        const terminalTicketIndex = supportRows.findIndex((text) => text.includes(terminalSupportSubject));
        expect(oldTicketIndex).toBeGreaterThanOrEqual(0);
        expect(newTicketIndex).toBeGreaterThan(oldTicketIndex);
        expect(terminalTicketIndex).toBeGreaterThan(newTicketIndex);
    });
});
