import { test } from '@e2e-dev/web';
import { credentials, expect } from 'e2e';

const commercePages = [
  '/vendor-portal/vendor-services-list-page',
  '/vendor-portal/vendor-categories-page',
  '/vendor-portal/vendor-import-sale-services-page',
  '/vendor-portal/vendor-reviews-page',
  '/vendor-portal/vendor-wallet-page',
  '/vendor-portal/vendor-bookings-page',
];

const onboardingPages = [
  '/vendor-portal',
  '/vendor-portal/vendor-profile-page',
  '/vendor-portal/vendor-coverage-areas-page',
  '/vendor-portal/vendor-business-hours-page',
  '/vendor-portal/vendor-documents-page',
  '/vendor-portal/vendor-notification-preferences-page',
  '/vendor-portal/vendor-account-page',
  '/vendor-portal/vendor-approval-status-page',
  '/vendor-portal/vendor-phone-verification-page',
];

test.beforeEach(async ({ app, browser, screen }) => {
  const vendor = credentials.user('vendor');
  await app.open('/vendor-portal/login');
  await browser.locator('[id="data.email"]').fill(vendor.username);
  await browser.locator('[id="data.password"]').fill(vendor.password);
  await browser.locator('form button[type="submit"]').tap();
  await expect(browser).toHaveURL('/vendor-portal', { timeout: 30_000 });

  // A previous test attempt may have changed the fixture number. Restore its
  // verified state through the same local OTP UI the vendor uses.
  await app.open('/vendor-portal/vendor-phone-verification-page');
  const verificationCode = screen.getByLabel('Verification code');
  if (await verificationCode.isVisible()) {
    await screen.getByRole('button', 'Send verification code').tap();
    await expect(screen.getByText('Verification code sent.')).toBeVisible({ timeout: 30_000 });
    await verificationCode.fill('000000');
    await screen.getByRole('button', 'Verify phone number').tap();
    await expect(verificationCode).toBeHidden({ timeout: 30_000 });
  }
});

test('a changed phone keeps onboarding available and restores commerce after OTP, including after login again', { timeout: 300_000 }, async ({ app, browser, screen }) => {
  await app.open('/vendor-portal/vendor-account-page');
  await expect(browser).toHaveURL('/vendor-portal/vendor-account-page');

  await screen.getByRole('button', 'Update Phone').tap();
  const phoneDialog = screen.getByRole('dialog');
  await expect(phoneDialog).toBeVisible();
  await phoneDialog.getByLabel('New Phone Number').fill('abc123');
  await phoneDialog.getByRole('button', 'Save').tap();
  await expect(phoneDialog).toBeVisible();
  await expect(screen.getByText(/invalid/i)).toBeVisible();
  await expect(browser).toHaveURL('/vendor-portal/vendor-account-page');

  const changedPhone = `+2010${Date.now().toString().slice(-8)}`;
  await phoneDialog.getByLabel('New Phone Number').fill(changedPhone);
  await phoneDialog.getByRole('button', 'Save').tap();
  await expect(browser).toHaveURL(/\/vendor-portal\/vendor-phone-verification-page$/);
  await expect(screen.getByLabel('Verification code')).toBeVisible();

  for (const path of onboardingPages) {
    const [response] = await Promise.all([
      browser.waitForResponse(`**${path}*`),
      app.open(path),
    ]);
    expect(response.status).toBe(200);
  }

  for (const path of commercePages) {
    const [response] = await Promise.all([
      browser.waitForResponse(`**${path}*`),
      app.open(path),
    ]);
    expect(response.status).toBe(403);
  }

  await screen.getByRole('button', 'User menu').tap();
  await screen.getByRole('button', 'Sign out').tap();
  await expect(browser).toHaveURL(/\/vendor-portal\/login/);
  const vendor = credentials.user('vendor');
  await browser.locator('[id="data.email"]').fill(vendor.username);
  await browser.locator('[id="data.password"]').fill(vendor.password);
  await browser.locator('form button[type="submit"]').tap();
  await expect(browser).toHaveURL('/vendor-portal', { timeout: 30_000 });

  for (const path of commercePages) {
    const [response] = await Promise.all([
      browser.waitForResponse(`**${path}*`),
      app.open(path),
    ]);
    expect(response.status).toBe(403);
  }

  await app.open('/vendor-portal/vendor-phone-verification-page');
  await screen.getByRole('button', 'Send verification code').tap();
  await expect(screen.getByText('Verification code sent.')).toBeVisible();
  await screen.getByLabel('Verification code').fill('000000');
  await screen.getByRole('button', 'Verify phone number').tap();
  await expect(screen.getByText('Phone number verified successfully.')).toBeVisible();
  await expect(screen.getByLabel('Verification code')).toBeHidden();

  for (const path of commercePages) {
    const [response] = await Promise.all([
      browser.waitForResponse(`**${path}*`),
      app.open(path),
    ]);
    expect(response.status).toBe(200);
  }
});

test('coverage city validation clears when selected without saving the form', { timeout: 180_000 }, async ({ app, browser, screen }) => {
  await app.open('/vendor-portal/vendor-coverage-areas-page');
  await expect(browser).toHaveURL('/vendor-portal/vendor-coverage-areas-page');
  await screen.getByRole('button', 'Add Coverage Area').tap();

  const dialog = screen.getByRole('dialog');
  await dialog.getByRole('button', 'Save').tap();
  const cityError = screen.getByText(/city field is required/i);
  await expect(cityError).toBeVisible();

  const cityPicker = dialog.getByRole('combobox', 'City');
  await cityPicker.tap();
  const cityOption = screen.getByRole('option').first();
  const cityLabel = (await cityOption.textContent()) ?? '';
  const cityName = cityLabel.split(' — ').at(-1)?.trim() ?? '';
  expect(cityName).not.toBe('');
  await cityOption.tap();
  await expect(cityError).toBeHidden();
  await expect(dialog).toBeVisible();

  await dialog.getByRole('button', 'Save').tap();
  await expect(dialog).toBeHidden();
  await expect(screen.getByRole('table')).toContainText(cityName);
  await expect(screen.getByText('Coverage areas updated.')).toBeVisible();
});

test('documents reject text disguised as PDF and allow deleting a pending upload', { timeout: 180_000 }, async ({ app, browser, screen }) => {
  await app.open('/vendor-portal/vendor-documents-page');
  await expect(browser).toHaveURL('/vendor-portal/vendor-documents-page');
  await screen.getByRole('button', 'Upload Document').tap();

  const dialog = screen.getByRole('dialog');
  await dialog.getByRole('combobox', 'Document Type').tap();
  await screen.getByRole('option', 'Other').tap();
  await browser.locator('input[type="file"]').setInputFiles('.e2e/risk-program/github-vendor-browser-fake.pdf');
  await dialog.getByRole('button', 'Save').tap();
  await expect(screen.getByText('Only PDF, JPEG, and PNG files are accepted.')).toBeVisible();
  await expect(screen.getByRole('table')).not.toContainText('Pending Review');

  await browser.locator('input[type="file"]').setInputFiles('.e2e/risk-program/github-vendor-browser-valid.pdf');
  await dialog.getByRole('button', 'Save').tap();
  await expect(dialog).toBeHidden();
  await expect(screen.getByRole('table')).toContainText('Pending Review');

  const pendingRow = screen.getByRole('row').filter({ hasText: 'Pending Review' });
  await pendingRow.getByRole('button', 'Delete').tap();
  const confirmation = screen.getByRole('dialog');
  await confirmation.getByRole('button', 'Delete').tap();
  await expect(screen.getByText('Document deleted.')).toBeVisible();
  await expect(screen.getByRole('table')).not.toContainText('Pending Review');
});
