import { execFileSync } from 'node:child_process';
import path from 'node:path';
import fs from 'node:fs/promises';
import { expect, test } from '@playwright/test';

const environment = {
  ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite',
  DB_DATABASE: path.resolve('storage/e2e.sqlite'), CACHE_STORE: 'array', QUEUE_CONNECTION: 'sync',
};
const numericText = (text: string) => text.replace(/[٠-٩]/g, digit => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit))).replace(/٫/g, '.').replace(/٬/g, ',');

test('vendor finance shows owned party figures, complete printable statement and transport invoice in EN and AR', async ({ browser }) => {
  test.setTimeout(180_000);
  const fixtureOutput = execFileSync('php', ['artisan', 'tinker', '--execute', String.raw`
$user = App\Modules\Identity\Domain\Models\User::factory()->phoneVerified()->asVendor()->create(['email'=>'e2e-finance-handoff@example.test','password'=>'VendorUat123!','email_verified_at'=>now()]);
$governorate = App\Modules\Geography\Domain\Models\Governorate::where('code','EG-CAI')->firstOrFail();
$governorate->update(['transport_fee_minor'=>2500]);
$city = App\Modules\Geography\Domain\Models\City::where('governorate_id',$governorate->id)->firstOrFail();
$vendor = App\Modules\Identity\Domain\Models\VendorProfile::factory()->approved()->create(['user_id'=>$user->id,'primary_governorate_id'=>$governorate->id,'primary_city_id'=>$city->id]);
$foreign = App\Modules\Identity\Domain\Models\VendorProfile::factory()->approved()->create(['primary_governorate_id'=>$governorate->id,'primary_city_id'=>$city->id]);
$wallet = App\Modules\Settlement\Domain\Models\Wallet::factory()->create(['owner_type'=>get_class($vendor),'owner_id'=>$vendor->id,'balance_minor'=>8100,'pending_withdrawal_minor'=>0]);
$booking = App\Modules\Booking\Domain\Models\Booking::factory()->create(['reference_no'=>'E2E-FINANCE-PARTY','subtotal_minor'=>10000,'delivery_total_minor'=>4000,'total_minor'=>14000,'transport_base_minor'=>2500,'transport_base_currency'=>'EGP','transport_base_source'=>'governorate:EG-CAI']);
$bookingVendor = App\Modules\Booking\Domain\Models\BookingVendor::factory()->create(['booking_id'=>$booking->id,'vendor_profile_id'=>$vendor->id,'subtotal_minor'=>10000,'delivery_fee_minor'=>1500]);
$service = App\Modules\Catalog\Domain\Models\Service::factory()->sale()->create(['vendor_profile_id'=>$vendor->id,'category_id'=>App\Modules\Catalog\Domain\Models\Category::where('code','sale-cakes')->value('id') ?? App\Modules\Catalog\Domain\Models\Category::firstOrFail()->id]);
$item = App\Modules\Booking\Domain\Models\BookingItem::factory()->sale()->create(['booking_vendor_id'=>$bookingVendor->id,'service_id'=>$service->id,'name_snapshot'=>['en'=>'Finance party service','ar'=>'خدمة الحفلة المالية'],'quantity'=>1,'line_total_minor'=>10000]);
$credit = App\Modules\Settlement\Database\Factories\WalletLedgerEntryFactory::new()->commissionCredit()->create(['wallet_id'=>$wallet->id,'amount_minor'=>8500,'running_balance_minor'=>null,'correlation_id'=>'E2E-COMMISSION','created_at'=>now()->subMinutes(20)]);
App\Modules\Settlement\Domain\Models\Commission::factory()->sale()->create(['booking_item_id'=>$item->id,'vendor_profile_id'=>$vendor->id,'gross_amount_minor'=>10000,'commission_bps'=>1500,'commission_minor'=>1500,'vendor_share_minor'=>8500,'accrual_ledger_entry_id'=>$credit->id,'status'=>'calculated']);
for ($i=1; $i<=11; $i++) { App\Modules\Settlement\Database\Factories\WalletLedgerEntryFactory::new()->commissionCredit()->create(['wallet_id'=>$wallet->id,'amount_minor'=>100,'running_balance_minor'=>null,'correlation_id'=>'E2E-CREDIT-'.$i,'created_at'=>now()->subMinutes(20-$i)]); }
App\Modules\Settlement\Database\Factories\WalletLedgerEntryFactory::new()->withdrawalDebit()->create(['wallet_id'=>$wallet->id,'amount_minor'=>1500,'running_balance_minor'=>null,'correlation_id'=>'E2E-DEBIT','created_at'=>now()]);
$foreignWallet = App\Modules\Settlement\Domain\Models\Wallet::factory()->create(['owner_type'=>get_class($foreign),'owner_id'=>$foreign->id]);
App\Modules\Settlement\Database\Factories\WalletLedgerEntryFactory::new()->commissionCredit()->create(['wallet_id'=>$foreignWallet->id,'correlation_id'=>'FOREIGN-FINANCE-SECRET']);
App\Modules\Settlement\Domain\Models\Withdrawal::factory()->rejected()->create(['vendor_profile_id'=>$vendor->id,'requested_by_user_id'=>$user->id,'requested_amount_minor'=>1500,'bank_transfer_reference'=>'E2E-OWN-WITHDRAWAL']);
App\Modules\Settlement\Domain\Models\Withdrawal::factory()->rejected()->create(['vendor_profile_id'=>$foreign->id,'bank_transfer_reference'=>'FOREIGN-WITHDRAWAL-SECRET']);
$admin = App\Modules\Identity\Domain\Models\User::where('email','uat-admin@example.test')->firstOrFail();
foreach (['view_any_governorate','view_governorate','update_governorate'] as $name) { $admin->givePermissionTo(Spatie\Permission\Models\Permission::findOrCreate($name,'web')); }
app(App\Modules\Booking\Application\Actions\AddTransportationAdjustmentAction::class)->execute($booking, $admin->id, 1000, 'E2E-transport-adjustment', 'E2E-INVOICE');
echo json_encode(['invoice'=>route('admin.bookings.invoice',$booking->public_id),'governorateEdit'=>App\Modules\Geography\Filament\Resources\GovernorateResource::getUrl('edit',['record'=>$governorate],panel:'admin')]);
`.trim().replace(/\r?\n/g, ' ')], { env: environment, timeout: 30_000 }).toString().trim();
  if (!fixtureOutput.startsWith('{')) throw new Error(fixtureOutput);
  const fixtures = JSON.parse(fixtureOutput);
  const financeOut = path.resolve('docs/qa/screenshots/handoff-2026-10-05/finance');
  await fs.mkdir(financeOut, { recursive: true });
  const printOut = path.resolve('tmp/pdfs/handoff-finance');
  await fs.mkdir(printOut, { recursive: true });

  const context = await browser.newContext({ baseURL: 'http://127.0.0.1:8139', storageState: { cookies: [], origins: [] } });
  const page = await context.newPage();
  await page.goto('/vendor-portal/login');
  await page.locator('input[type="email"]').fill('e2e-finance-handoff@example.test');
  await page.locator('input[type="password"]').fill('VendorUat123!');
  await page.getByRole('button', { name: /sign in|تسجيل الدخول/i }).click();
  await expect(page).toHaveURL(/\/vendor-portal\/?$/);

  for (const locale of ['en', 'ar']) {
    await page.setViewportSize(locale === 'ar' ? { width: 390, height: 844 } : { width: 1440, height: 1000 });
    await page.goto('/vendor-portal/vendor-wallet-page');
    if (locale === 'ar') {
      await page.locator('.language-switch-trigger').click();
      await page.locator(String.raw`button[wire\:click*="changeLocale"]`).filter({ hasText: 'العربية' }).click();
      await page.waitForLoadState('networkidle');
    }
    const headers = await page.locator('.fi-ta-table thead th').filter({ hasNot: page.locator('input[type="checkbox"]') }).allTextContents();
    expect(headers.map(header => header.trim()).filter(Boolean).slice(0, 5)).toEqual(locale === 'en'
      ? ['Date', 'Party Number', 'Party Total', 'Commission Percentage', 'Party Net']
      : ['التاريخ', 'رقم الحفلة', 'إجمالي الحفلة', 'نسبة العمولة', 'صافي الحفلة']);
    await page.locator('th').filter({ hasText: locale === 'en' ? /^\s*Date\s*$/ : /^\s*التاريخ\s*$/ }).click();
    const partyRow = page.locator('.fi-ta-row').filter({ hasText: 'E2E-FINANCE-PARTY' });
    await expect(partyRow).toBeVisible();
    const figures = numericText(await partyRow.innerText());
    expect(figures).toContain('115.00');
    expect(figures).toContain('15.00%');
    expect(figures).toContain('85.00');

    await page.goto('/vendor-portal/vendor-withdrawals-page');
    await expect(page.getByText('E2E-OWN-WITHDRAWAL', { exact: true })).toBeVisible();
    await expect(page.getByText('FOREIGN-WITHDRAWAL-SECRET')).toHaveCount(0);
    const financeLinks = await page.locator('.fi-sidebar a[href*="vendor-"]').evaluateAll(links => links.map(link => link.getAttribute('href') ?? '').filter(href => /vendor-(wallet|withdrawals|statement)-page/.test(href)));
    expect(financeLinks.map(href => new URL(href).pathname)).toEqual([
      '/vendor-portal/vendor-wallet-page', '/vendor-portal/vendor-withdrawals-page', '/vendor-portal/vendor-statement-page',
    ]);
    await page.goto('/vendor-portal/vendor-statement-page');
    await expect(page.locator('.fi-ta-row')).toHaveCount(13);
    await expect(page.getByText('FOREIGN-FINANCE-SECRET')).toHaveCount(0);
    expect(numericText(await page.locator('.fi-ta-row').last().innerText())).toContain('81.00');
    await page.evaluate(() => { (window as any).__printCalls = 0; window.print = () => { (window as any).__printCalls++; }; });
    await page.getByRole('button', { name: locale === 'en' ? 'Print statement' : 'طباعة الكشف', exact: true }).click();
    expect(await page.evaluate(() => (window as any).__printCalls)).toBe(1);
    await page.emulateMedia({ media: 'print' });
    await expect(page.locator('.fi-sidebar')).toBeHidden();
    await expect(page.locator('.fi-sidebar-close-overlay')).toBeHidden();
    await expect(page.locator('.fi-ta-row')).toHaveCount(13);
    await page.screenshot({ path: path.join(financeOut, `vendor-statement-print-${locale}.png`), fullPage: true });
    await page.pdf({ path: path.join(printOut, `vendor-statement-${locale}.pdf`), format: 'A4', printBackground: true });
    await page.emulateMedia({ media: 'screen' });
  }
  await context.close();

  const adminContext = await browser.newContext({ baseURL: 'http://127.0.0.1:8139', storageState: { cookies: [], origins: [] } });
  const invoicePage = await adminContext.newPage();
  await invoicePage.goto('/admin/login');
  await invoicePage.locator('input[type="email"]').fill('uat-admin@example.test');
  await invoicePage.locator('input[type="password"]').fill('UatTest123!');
  await invoicePage.getByRole('button', { name: /sign in|تسجيل الدخول/i }).click();
  await expect(invoicePage).toHaveURL(/\/admin\/?$/, { timeout: 15_000 });
  await invoicePage.goto(new URL(fixtures.governorateEdit).pathname);
  const transportFee = invoicePage.locator('input[id="data.transport_fee_minor"]');
  await expect(transportFee).toHaveValue('25.00');
  await transportFee.fill('37.50');
  await Promise.all([
    invoicePage.waitForResponse(response => response.url().includes('/livewire/update') && response.status() === 200),
    invoicePage.getByRole('button', { name: /save changes|حفظ التغييرات/i }).click(),
  ]);
  await invoicePage.reload();
  await expect(transportFee).toHaveValue('37.50');
  for (const locale of ['en', 'ar']) {
    await invoicePage.setViewportSize(locale === 'ar' ? { width: 390, height: 844 } : { width: 1440, height: 1000 });
    const response = await invoicePage.goto(new URL(fixtures.invoice).pathname + `?locale=${locale}&lang=${locale}`);
    expect(response?.status()).toBe(200);
    await expect(invoicePage.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
    await expect(invoicePage.getByText('E2E-transport-adjustment', { exact: false })).toBeVisible();
    const invoice = numericText(await invoicePage.locator('body').innerText());
    expect(invoice).toContain('25.00');
    expect(invoice).toContain('10.00');
    expect(invoice).toContain('150.00');
    await invoicePage.evaluate(() => { (window as any).__printCalls = 0; window.print = () => { (window as any).__printCalls++; }; });
    await invoicePage.locator('button.print').click();
    expect(await invoicePage.evaluate(() => (window as any).__printCalls)).toBe(1);
    await invoicePage.emulateMedia({ media: 'print' });
    await expect(invoicePage.locator('button.print')).toBeHidden();
    await invoicePage.screenshot({ path: path.join(financeOut, `transport-invoice-${locale}.png`), fullPage: true });
    await invoicePage.pdf({ path: path.join(printOut, `transport-invoice-${locale}.pdf`), format: 'A4', printBackground: true });
    await invoicePage.emulateMedia({ media: 'screen' });
  }
  await adminContext.close();
});
