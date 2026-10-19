import { defineConfig } from '@playwright/test';
import path from 'node:path';

export const environment = {
    ...process.env,
    APP_ENV: 'testing', APP_DEBUG: 'false', APP_URL: 'http://127.0.0.1:8156',
    DB_CONNECTION: 'sqlite', DB_DATABASE: path.resolve('storage/vendor-document-types-qa.sqlite'), DB_URL: '',
    CACHE_STORE: 'array', SESSION_DRIVER: 'file', SESSION_SECURE_COOKIE: 'false',
    QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array', SCOUT_DRIVER: 'null',
    TELESCOPE_ENABLED: 'false', PULSE_ENABLED: 'false', NIGHTWATCH_ENABLED: 'false',
    ADMIN_MFA_REQUIRED: 'false', CATALOG_DIGITAL_SERVICES_ENABLED: 'true',
    VIEW_COMPILED_PATH: path.resolve('storage/framework/vendor-document-types-qa-views'),
};

export default defineConfig({
    testDir: '.', testMatch: 'vendor-document-types.spec.ts', workers: 1, retries: 0, timeout: 180_000,
    globalSetup: './vendor-document-types.setup.ts',
    use: { baseURL: environment.APP_URL, screenshot: 'only-on-failure', trace: 'retain-on-failure', actionTimeout: 30_000, navigationTimeout: 45_000 },
    reporter: [['list']], outputDir: path.resolve('tests/e2e/.results-vendor-document-types'),
    webServer: {
        command: 'php -S 127.0.0.1:8156 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
        cwd: path.resolve('public'), url: environment.APP_URL + '/up', env: environment,
        reuseExistingServer: false, timeout: 60_000, stdout: 'ignore', stderr: 'ignore',
    },
});
