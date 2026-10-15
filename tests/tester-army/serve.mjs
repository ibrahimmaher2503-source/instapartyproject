import { spawn, spawnSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { createHash, randomBytes } from 'node:crypto';

const root = path.resolve(import.meta.dirname, '../..');
const port = Number(process.argv[2]);
if (!Number.isInteger(port) || port < 1024 || port > 65535) throw new Error('A local test port is required');
const parent = path.join(root, '.e2e/app');
mkdirSync(parent, { recursive: true });
const runtime = mkdtempSync(path.join(parent, 'run-'));
for (const directory of ['bootstrap/cache', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'storage/app']) {
    mkdirSync(path.join(runtime, directory), { recursive: true });
}
const database = path.join(runtime, 'database.sqlite');
writeFileSync(database, '');
let appRoot = root;
if (process.platform === 'linux' && root.startsWith('/mnt/')) {
    // Laravel provider discovery on the Windows mount can exceed the startup deadline.
    // Archive only code/dependencies/assets, excluding environment files and uploaded user data.
    console.log('Preparing a temporary Linux filesystem copy of the current source.');
    // shortcut: Composer metadata keys this cache; remove the vendor archive after manual vendor edits.
    const dependencyHash = createHash('sha256').update(readFileSync(path.join(root, 'vendor/composer/installed.json'))).digest('hex');
    const vendorArchive = path.join(parent, `vendor-${dependencyHash}.tar`);
    const cachedVendor = existsSync(vendorArchive);
    const archive = path.relative(root, path.join(runtime, 'sources.tar'));
    const packed = spawnSync('tar.exe', ['-cf', archive, '--exclude=bootstrap/cache', '--exclude=public/storage',
        'app', 'bootstrap', 'config', 'lang', 'routes', 'resources', 'public', ...(cachedVendor ? [] : ['vendor']),
        'database/factories', 'database/seeders', 'database/migrations', 'tests/Fakes', 'tests/tester-army', 'artisan', 'composer.json'],
    { cwd: root, encoding: 'utf8', timeout: 600_000 });
    if (packed.status !== 0) throw new Error(packed.stderr || packed.error?.message || 'Source archive failed');
    appRoot = mkdtempSync(path.join(tmpdir(), 'instaparty-e2e-'));
    const unpacked = spawnSync('tar', ['-xf', path.join(root, archive), '-C', appRoot], { encoding: 'utf8', timeout: 120_000 });
    if (unpacked.status !== 0) throw new Error(unpacked.stderr || 'Source unpack failed');
    if (cachedVendor) {
        const dependencies = spawnSync('tar', ['-xf', vendorArchive, '-C', appRoot, 'vendor'], { encoding: 'utf8', timeout: 120_000 });
        if (dependencies.status !== 0) throw new Error(dependencies.stderr || 'Dependency unpack failed');
    } else {
        copyFileSync(path.join(root, archive), vendorArchive);
    }
}
const environment = {
    ...process.env,
    APP_ENV: 'testing', APP_DEBUG: 'false', APP_URL: `http://127.0.0.1:${port}`,
    APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
    DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
    LARAVEL_STORAGE_PATH: path.join(runtime, 'storage'),
    APP_CONFIG_CACHE: path.join(runtime, 'bootstrap/cache/config.php'),
    APP_SERVICES_CACHE: path.join(runtime, 'bootstrap/cache/services.php'),
    APP_PACKAGES_CACHE: path.join(runtime, 'bootstrap/cache/packages.php'),
    APP_ROUTES_CACHE: path.join(runtime, 'bootstrap/cache/routes.php'),
    APP_EVENTS_CACHE: path.join(runtime, 'bootstrap/cache/events.php'),
    VIEW_COMPILED_PATH: path.join(runtime, 'storage/framework/views'),
    CACHE_STORE: 'file', SESSION_DRIVER: 'file', SESSION_SECURE_COOKIE: 'false',
    QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array', SCOUT_DRIVER: 'null',
    TELESCOPE_ENABLED: 'false', PULSE_ENABLED: 'false', NIGHTWATCH_ENABLED: 'false',
    CATALOG_DIGITAL_SERVICES_ENABLED: 'true',
    TESTER_ARMY_PROJECT_ROOT: root,
};
function prepare(args) {
    console.log(`Preparing isolated app: ${args[0] === 'artisan' ? 'database migrations' : 'service fixture'}`);
    const result = spawnSync('php', ['-d', 'variables_order=EGPCS', ...args], { cwd: appRoot, env: environment, encoding: 'utf8', timeout: 120_000 });
    if (result.status !== 0) {
        // No environment variables or keys are printed with a startup failure.
        process.stderr.write(result.stderr || result.stdout || 'PHP fixture preparation failed');
        process.exit(result.status || 1);
    }
}
prepare(['artisan', 'migrate', '--force', '--no-interaction']);
prepare(['tests/tester-army/seed.php']);
console.log('Isolated tester.army database and fixture ready.');
const server = spawn('php', ['-d', 'variables_order=EGPCS', '-S', `127.0.0.1:${port}`, '-t', '.', path.join(appRoot, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.join(appRoot, 'public'), env: environment, stdio: 'inherit' });
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => server.kill(signal));
server.on('exit', code => process.exit(code || 0));
