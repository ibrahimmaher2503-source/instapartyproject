import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

const database = path.resolve('storage/e2e.sqlite');
const testEnvironment = {
    ...process.env,
    APP_ENV: 'testing',
    APP_DEBUG: 'true',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: database,
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'log',
    MAIL_LOG_CHANNEL: 'single',
    LOG_CHANNEL: 'single',
    LOG_LEVEL: 'debug',
};

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: [['html', { outputFolder: 'tests/e2e/.report' }], ['list']],

    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8139',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        locale: 'en-GB',
        timezoneId: 'Africa/Cairo',
    },

    webServer: {
        command: 'php -S 127.0.0.1:8139 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
        cwd: path.resolve('public'),
        url: 'http://127.0.0.1:8139/admin/login',
        env: testEnvironment,
        reuseExistingServer: false,
        timeout: 60_000,
    },

    projects: [
        {
            name: 'setup',
            testMatch: '**/auth/global.setup.ts',
        },
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                storageState: 'tests/e2e/auth/.auth-state.json',
            },
            dependencies: ['setup'],
        },
    ],

    outputDir: 'tests/e2e/.results',
});
