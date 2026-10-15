import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const runtime = path.resolve('storage/release-readiness');
export const releaseReadinessEnvironment = {
    ...process.env,
    APP_ENV: 'testing',
    APP_KEY: 'base64:VWzDtB90J9Mw/mAe8whXpbJND+ivsyFAszzCe2OFaxg=',
    APP_DEBUG: 'false',
    APP_URL: 'http://127.0.0.1:8214',
    APP_CONFIG_CACHE: 'storage/release-readiness/config.php',
    APP_ROUTES_CACHE: 'storage/release-readiness/routes.php',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.resolve('storage/release-readiness.sqlite'),
    DB_URL: '',
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'file',
    SESSION_FILES: path.join(runtime, 'sessions'),
    SESSION_COOKIE: 'release_readiness_session',
    SESSION_DOMAIN: '',
    SESSION_PATH: '/',
    SESSION_SECURE_COOKIE: 'false',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    BROADCAST_CONNECTION: 'null',
    FILESYSTEM_DISK: 'local',
    MEDIA_DISK_PUBLIC: 'public',
    VIEW_COMPILED_PATH: path.join(runtime, 'views'),
    SCOUT_DRIVER: 'null',
    MEILISEARCH_HOST: 'http://127.0.0.1:9',
    MEILISEARCH_KEY: '',
    SMS_MISR_USERNAME: '',
    SMS_MISR_PASSWORD: '',
    SMS_MISR_SENDER: '',
    AWS_ACCESS_KEY_ID: '',
    AWS_SECRET_ACCESS_KEY: '',
    AWS_BUCKET: '',
    PULSE_ENABLED: 'false',
    TELESCOPE_ENABLED: 'false',
    NIGHTWATCH_ENABLED: 'false',
    CATALOG_DIGITAL_SERVICES_ENABLED: 'true',
    LOG_CHANNEL: 'stderr',
    LOG_LEVEL: 'error',
};

function artisan(...args: string[]): string {
    return execFileSync('php', ['-d', 'opcache.enable_cli=0', 'artisan', ...args], {
        env: releaseReadinessEnvironment,
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
        timeout: 120_000,
    });
}

export default function setup(): void {
    for (const directory of ['sessions', 'views', 'playwright']) {
        mkdirSync(path.join(runtime, directory), { recursive: true });
    }

    artisan('migrate:fresh', '--force');
    artisan('e2e:seed-admin', '--email=release-readiness@example.test', '--password=ReleaseCheck123!');
    artisan('db:seed', '--class=App\\Modules\\Geography\\Database\\Seeders\\EgyptGeographySeeder', '--force');
    artisan('db:seed', '--class=App\\Modules\\Catalog\\Database\\Seeders\\CatalogSeeder', '--force');
    const fixtures = String.raw`
        $role=Spatie\Permission\Models\Role::findByName('super_admin','web');
        foreach (['view_any_occasion','view_occasion','create_occasion','update_occasion','delete_occasion'] as $name) {
            $role->givePermissionTo(Spatie\Permission\Models\Permission::findOrCreate($name,'web'));
        }
        Spatie\Permission\Models\Role::firstOrCreate(['name'=>'vendor','guard_name'=>'web']);
        Spatie\Permission\Models\Role::firstOrCreate(['name'=>'customer','guard_name'=>'web']);
        App\Modules\Identity\Domain\Models\User::factory()->asCustomer()->phoneVerified()->create([
            'email'=>'release-customer@example.test', 'name'=>'Release Readiness Customer',
            'password'=>'ReleaseCheck123!', 'preferred_locale'=>'en',
        ]);
        $occasion=App\Modules\Catalog\Domain\Models\Occasion::query()->where('code','birthday')->firstOrFail();
        $category=App\Modules\Catalog\Domain\Models\Category::query()->where('code','birthday-general')->firstOrFail();
        $governorate=App\Modules\Geography\Domain\Models\Governorate::query()->where('code','EG-CAI')->firstOrFail();
        $city=App\Modules\Geography\Domain\Models\City::query()->where('governorate_id',$governorate->id)->firstOrFail();
        $vendor=App\Modules\Identity\Domain\Models\VendorProfile::factory()->approved()->create([
            'business_name'=>['en'=>'Release Readiness Vendor','ar'=>'بائع اختبار الجاهزية'],
            'primary_governorate_id'=>$governorate->id, 'primary_city_id'=>$city->id,
        ]);
        $service=App\Modules\Catalog\Domain\Models\Service::factory()->rental()->published()->create([
            'vendor_profile_id'=>$vendor->id, 'category_id'=>$category->id,
            'slug'=>'release-readiness-rental',
            'name'=>['en'=>'Release Readiness Rental','ar'=>'تأجير اختبار الجاهزية'],
            'base_price_minor'=>10000, 'base_price_currency'=>'EGP',
        ]);
        $service->rentalDetail()->create([
            'default_rental_duration_hours'=>6, 'requires_electricity'=>false,
            'requires_outdoor_space'=>false, 'security_deposit_minor'=>0,
            'security_deposit_currency'=>'EGP',
        ]);
        $occasion->categories()->syncWithoutDetaching([$category->id=>['sort_order'=>1]]);
        echo "RR_SERVICE:".$service->public_id;
    `;
    const output = artisan('tinker', `--execute=${fixtures}`);
    const servicePublicId = output.match(/RR_SERVICE:([0-9A-HJKMNP-TV-Z]{26})/)?.[1];
    if (!servicePublicId) throw new Error(`Release readiness service fixture failed: ${output.slice(-700).trim()}`);
    writeFileSync(path.join(runtime, 'fixtures.json'), JSON.stringify({ servicePublicId, customerEmail: 'release-customer@example.test' }));
}
