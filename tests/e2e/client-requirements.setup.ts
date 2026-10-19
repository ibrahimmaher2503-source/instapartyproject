import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { environment } from './client-requirements.config';

export default function setup() {
    const database = path.resolve('storage/client-requirements.sqlite');
    if (environment.DB_DATABASE !== database || environment.APP_ENV !== 'testing') throw new Error('Disposable database required');
    fs.mkdirSync(environment.VIEW_COMPILED_PATH, { recursive: true });
    fs.closeSync(fs.openSync(database, 'a'));
    execFileSync('php', ['artisan', 'migrate:fresh', '--force'], { env: environment, timeout: 120_000, stdio: 'pipe' });
    execFileSync('php', ['tests/e2e/fixtures/client-requirements.php'], { env: environment, timeout: 120_000, stdio: 'inherit' });
    const fixtures = JSON.parse(fs.readFileSync('storage/app/client-requirements-fixtures.json', 'utf8'));
    if (!fixtures.slugs?.many || !fixtures.vendor) throw new Error('Browser fixtures did not finish');
}
