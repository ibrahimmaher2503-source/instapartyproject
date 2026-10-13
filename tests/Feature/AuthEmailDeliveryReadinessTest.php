<?php

declare(strict_types=1);

use App\Modules\Communication\Domain\Contracts\EmailTransportReadiness;
use App\Modules\Identity\Application\Listeners\SendPasswordResetNotification;
use App\Modules\Identity\Application\Listeners\SendVendorRegistrationEmailVerification;
use App\Modules\Identity\Domain\Events\PasswordResetRequested;
use App\Modules\Identity\Domain\Events\VendorRegistered;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Auth\PanelRequestPasswordReset;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('fails vendor registration verification jobs closed when production mail is not ready', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    config(['mail.default' => 'log']);
    Notification::fake();
    $profile = VendorProfile::factory()->create();
    $profile->user->forceFill(['email_verified_at' => null])->save();

    expect(app(EmailTransportReadiness::class)->isConfigured())->toBeFalse();
    expect(fn () => app(SendVendorRegistrationEmailVerification::class)->handle(new VendorRegistered($profile)))
        ->toThrow(RuntimeException::class, 'MAIL_NOT_CONFIGURED');

    Notification::assertNothingSent();
});

it('fails password reset email jobs closed when production mail is not ready', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    config(['mail.default' => 'log']);
    Mail::fake();

    expect(fn () => app(SendPasswordResetNotification::class)->handle(new PasswordResetRequested(
        userId: 1,
        identifier: 'vendor@example.test',
        channel: 'email',
        tokenPlain: 'test-reset-token',
        locale: 'en',
    )))->toThrow(RuntimeException::class, 'MAIL_NOT_CONFIGURED');

    Mail::assertNothingOutgoing();
});

it('uses the guarded request page for vendor password recovery with a custom log mailer', function (): void {
    config([
        'mail.default' => 'custom',
        'mail.mailers.custom.transport' => 'log',
    ]);
    $panel = Filament::getPanel('vendor');
    Filament::setCurrentPanel($panel);

    expect($panel->getRequestPasswordResetRouteAction())->toBe(PanelRequestPasswordReset::class);

    Livewire::test(PanelRequestPasswordReset::class)
        ->fillForm(['email' => 'vendor@example.test'])
        ->call('request')
        ->assertNotified(__('identity::account_management.delivery_failed'));
});

it('bounds the configured SMTP mailer timeout', function (): void {
    app('mail.manager')->purge('smtp');

    expect(Mail::mailer('smtp')->getSymfonyTransport()->getStream()->getTimeout())->toBe(30.0);
});
