<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\UpdateVendorProfileAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Http\Resources\VendorProfileResource;
use App\Modules\Shared\Application\Services\StorefrontText;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('round trips Arabic vendor names through JSON storage, update, and the API resource', function (): void {
    $profile = VendorProfile::factory()->create([
        'business_name' => ['en' => 'Cairo Events', 'ar' => 'فعاليات القاهرة'],
    ]);

    $this->actingAs($profile->user);
    app(UpdateVendorProfileAction::class)->execute($profile, [
        'business_name' => ['en' => 'Cairo Events Updated', 'ar' => 'فعاليات القاهرة المحدثة'],
    ]);

    $profile = $profile->refresh();
    $rawTranslations = json_decode((string) $profile->getRawOriginal('business_name'), true, 512, JSON_THROW_ON_ERROR);
    app()->setLocale('ar');
    $apiData = (new VendorProfileResource($profile))->toArray(Request::create('/api/v1/vendor/me', 'GET'));

    expect($rawTranslations)->toMatchArray([
        'en' => 'Cairo Events Updated',
        'ar' => 'فعاليات القاهرة المحدثة',
    ])
        ->and($profile->getTranslation('business_name', 'ar', false))->toBe('فعاليات القاهرة المحدثة')
        ->and($apiData['business_name'])->toBe('فعاليات القاهرة المحدثة')
        ->and($apiData['business_name_translations']['ar'])->toBe('فعاليات القاهرة المحدثة');
});

it('uses the authoritative English translation when a legacy Arabic value is only question marks', function (): void {
    $profile = VendorProfile::factory()->create([
        'business_name' => ['en' => 'Cairo Events', 'ar' => '????????'],
    ]);

    app()->setLocale('ar');
    $apiData = (new VendorProfileResource($profile))->toArray(Request::create('/api/v1/vendor/me', 'GET'));

    expect($profile->getTranslation('business_name', 'ar', false))->toBe('????????')
        ->and(app(StorefrontText::class)->translation($profile, 'business_name', 'ar'))->toBe('Cairo Events')
        ->and($profile->getTranslation('business_name', 'en', false))->toBe('Cairo Events')
        ->and($apiData['business_name'])->toBe('Cairo Events');
});

it('uses the English fallback on the admin vendor detail for a corrupted Arabic value', function (): void {
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any_vendor::profile', 'view_vendor::profile'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $profile = VendorProfile::factory()->create([
        'business_name' => ['en' => 'Cairo Events', 'ar' => '????????'],
    ]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/vendor-profiles/'.$profile->public_id)
        ->assertOk()
        ->assertSee('Cairo Events')
        ->assertDontSee('????????');
});
