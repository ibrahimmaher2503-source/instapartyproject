import { defineConfig } from '@playwright/test';
import path from 'node:path';

export const environment = {
    ...process.env,
    APP_ENV: 'testing', APP_DEBUG: 'false', APP_URL: 'http://127.0.0.1:8147',
    DB_CONNECTION: 'sqlite', DB_DATABASE: path.resolve('storage/client-requirements.sqlite'), DB_URL: '',
    CACHE_STORE: 'array', SESSION_DRIVER: 'file', SESSION_SECURE_COOKIE: 'false',
    QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array', SCOUT_DRIVER: 'null',
    TELESCOPE_ENABLED: 'false', PULSE_ENABLED: 'false', NIGHTWATCH_ENABLED: 'false',
    CATALOG_DIGITAL_SERVICES_ENABLED: 'true',
    VIEW_COMPILED_PATH: path.resolve('storage/framework/client-requirements-views'),
};

export default defineConfig({
    testDir: '.', testMatch: 'client-requirements.spec.ts', workers: 1, retries: 0, timeout: 120_000,
    globalSetup: './client-requirements.setup.ts',
    use: { baseURL: environment.APP_URL, screenshot: 'only-on-failure', trace: 'retain-on-failure' },
    reporter: [['list'], ['html', { outputFolder: path.resolve('tests/e2e/.report-client-requirements'), open: 'never' }]],
    outputDir: path.resolve('tests/e2e/.results-client-requirements'),
    webServer: {
        command: 'php -S 127.0.0.1:8147 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
        cwd: path.resolve('public'), url: environment.APP_URL + '/up', env: environment,
        reuseExistingServer: false, timeout: 60_000,
    },
});
