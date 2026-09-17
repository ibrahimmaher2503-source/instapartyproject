import { defineConfig } from '@playwright/test';
import path from 'node:path';

export default defineConfig({
  testDir: '.',
  testMatch: 'public-desktop-capture.spec.ts',
  globalSetup: path.resolve('tests/e2e/public-desktop-capture.setup.ts'),
  fullyParallel: false,
  workers: 1,
  use: { baseURL: 'http://127.0.0.1:8139', locale: 'en-GB', timezoneId: 'Africa/Cairo' },
  webServer: {
    command: 'php -d display_errors=0 -S 127.0.0.1:8139 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
    cwd: path.resolve('public'),
    env: { ...process.env, APP_ENV: 'testing', APP_DEBUG: 'true', APP_URL: 'http://127.0.0.1:8139', DB_CONNECTION: 'sqlite', DB_DATABASE: path.resolve('storage/e2e.sqlite'), CACHE_STORE: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'log' },
    url: 'http://127.0.0.1:8139/en',
    reuseExistingServer: false,
  },
});
