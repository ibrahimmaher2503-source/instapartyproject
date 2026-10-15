import { defineConfig } from '@playwright/test';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { releaseReadinessEnvironment } from './release-readiness.setup';

const port = 8214;
const appUrl = `http://127.0.0.1:${port}`;
const runtime = path.resolve('storage/release-readiness');

export default defineConfig({
    testDir: path.dirname(fileURLToPath(import.meta.url)),
    testMatch: 'release-readiness.spec.ts',
    globalSetup: path.resolve('tests/e2e/release-readiness.setup.ts'),
    fullyParallel: false,
    workers: 1,
    timeout: 90_000,
    expect: { timeout: 10_000 },
    reporter: [['list']],
    outputDir: path.join(runtime, 'playwright'),
    use: {
        baseURL: appUrl,
        locale: 'en-GB',
        timezoneId: 'America/Los_Angeles',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    webServer: {
        command: `php -d opcache.enable_cli=0 -d display_errors=0 -S 127.0.0.1:${port} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
        cwd: path.resolve('public'),
        env: { ...releaseReadinessEnvironment, APP_URL: appUrl },
        url: `${appUrl}/up`,
        reuseExistingServer: false,
        timeout: 120_000,
    },
});
