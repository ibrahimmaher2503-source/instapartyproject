<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Filament\Auth\AdminEditProfile;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('vendor', 'web');
});

it('provides a bilingual admin self-profile page', function (string $locale): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    app()->setLocale($locale);

    $this->get('/admin/profile')
        ->assertOk()
        ->assertSee(__('account.current_password'));

    Livewire::test(AdminEditProfile::class)
        ->assertFormFieldExists('name')
        ->assertFormFieldExists('email')
        ->assertFormFieldExists('current_password')
        ->assertFormFieldExists('password')
        ->assertFormFieldExists('passwordConfirmation');
})->with(['en', 'ar']);

it('rejects a password change without the current admin password', function (): void {
    $currentPassword = 'CurrentAdmin123!';
    $admin = User::factory()->create(['password' => $currentPassword]);
    $admin->assignRole('admin');
    $originalHash = $admin->password;

    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(AdminEditProfile::class)
        ->fillForm([
            'email' => 'unverified-change@example.test',
            'current_password' => 'WrongPassword123!',
            'password' => 'ReplacementAdmin123!',
            'passwordConfirmation' => 'ReplacementAdmin123!',
        ])
        ->call('save')
        ->assertHasFormErrors(['current_password']);

    expect($admin->fresh()->password)->toBe($originalHash)
        ->and($admin->fresh()->email)->toBe($admin->email);
});

it('rejects a mismatched new password confirmation without changing the account', function (): void {
    $currentPassword = 'CurrentAdmin123!';
    $admin = User::factory()->create(['password' => $currentPassword]);
    $admin->assignRole('admin');
    $originalHash = $admin->password;

    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(AdminEditProfile::class)
        ->fillForm([
            'current_password' => $currentPassword,
            'password' => 'ReplacementAdmin123!',
            'passwordConfirmation' => 'DifferentAdmin123!',
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);

    expect($admin->fresh()->password)->toBe($originalHash);
});

it('preserves the existing password complexity requirements', function (): void {
    $currentPassword = 'CurrentAdmin123!';
    $admin = User::factory()->create(['password' => $currentPassword]);
    $admin->assignRole('admin');
    $originalHash = $admin->password;

    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(AdminEditProfile::class)
        ->fillForm([
            'current_password' => $currentPassword,
            'password' => 'replacementpassword',
            'passwordConfirmation' => 'replacementpassword',
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);

    expect($admin->fresh()->password)->toBe($originalHash);
});

it('updates only the authenticated admin after current-password verification', function (): void {
    $currentPassword = 'CurrentAdmin123!';
    $admin = User::factory()->create(['password' => $currentPassword]);
    $admin->assignRole('admin');

    $otherAdmin = User::factory()->create(['password' => 'OtherAdmin123!']);
    $otherAdmin->assignRole('admin');
    $otherHash = $otherAdmin->password;

    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(AdminEditProfile::class)
        ->fillForm([
            'email' => 'updated-admin@example.test',
            'current_password' => $currentPassword,
            'password' => 'ReplacementAdmin123!',
            'passwordConfirmation' => 'ReplacementAdmin123!',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->fresh()->email)->toBe('updated-admin@example.test')
        ->and(Hash::check('ReplacementAdmin123!', $admin->fresh()->password))->toBeTrue()
        ->and($otherAdmin->fresh()->password)->toBe($otherHash);

    $this->get('/admin/profile')->assertOk();
});

it('denies the admin profile page to a vendor account', function (): void {
    $vendor = User::factory()->create();
    $vendor->assignRole('vendor');

    $this->actingAs($vendor, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/profile')->assertForbidden();
});
