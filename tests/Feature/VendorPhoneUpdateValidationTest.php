<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\UpdateVendorPhoneAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\VendorAccountPage;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('rejects malformed and duplicate vendor phone updates without invalidating the current verification', function (): void {
    $user = User::factory()->phoneVerified()->create(['phone_e164' => '+201011112345']);
    $verified = $user->phone_verified_at;
    User::factory()->create(['phone_e164' => '+201022223456']);
    foreach (['abc123', '+00123', '+201022223456'] as $phone) {
        expect(fn () => app(UpdateVendorPhoneAction::class)->execute($user, $phone))->toThrow(ValidationException::class);
        expect($user->fresh()->phone_e164)->toBe('+201011112345')
            ->and($user->fresh()->phone_verified_at->equalTo($verified))->toBeTrue();
    }
});

it('preserves verification on an unchanged phone and clears it only for a valid new phone', function (): void {
    $user = User::factory()->phoneVerified()->create(['phone_e164' => '+201011112345']);
    app(UpdateVendorPhoneAction::class)->execute($user, '+201011112345');
    expect($user->fresh()->phone_verified_at)->not->toBeNull();
    app(UpdateVendorPhoneAction::class)->execute($user, '+201033334567');
    expect($user->fresh()->phone_e164)->toBe('+201033334567')->and($user->fresh()->phone_verified_at)->toBeNull();
});

it('rejects arbitrary text in the actual vendor account phone form', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $original = $vendor->user->phone_e164;
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($vendor->user, 'vendor');
    Livewire::test(VendorAccountPage::class)->callAction('updatePhone', data: ['new_phone' => 'abc123'])
        ->assertHasActionErrors(['new_phone']);
    expect($vendor->user->fresh()->phone_e164)->toBe($original);
});
