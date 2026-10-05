<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Filament\Vendor\Pages\VendorLoyaltyProgramPage;
use Filament\Facades\Filament;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('denies even an approved vendor access without deleting the existing program', function (string $locale): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $profile = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    $program = LoyaltyProgram::factory()->create(['vendor_profile_id' => $profile->id]);
    $this->actingAs($user, 'vendor');

    expect(VendorLoyaltyProgramPage::canAccess())->toBeFalse();
    $this->get('/vendor-portal/vendor-loyalty-program-page')->assertForbidden();
    expect(fn () => (new VendorLoyaltyProgramPage)->save())->toThrow(HttpException::class);
    expect($program->fresh())->not->toBeNull();
})->with(['en', 'ar']);
