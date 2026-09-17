<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Listeners\SendVendorRegistrationEmailVerification;
use App\Modules\Identity\Domain\Events\VendorRegistered;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;
use Filament\Notifications\Auth\VerifyEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

function unverifiedVendorForEmail(): User
{
    $profile = VendorProfile::factory()->create();
    $user = $profile->user;

    $user->forceFill(['email_verified_at' => null])->save();

    return $user->fresh();
}

it('registers the vendor email verification route used by Filament', function (): void {
    $panel = Filament::getPanel('vendor');

    expect(Route::has($panel->generateRouteName('auth.email-verification.verify')))->toBeTrue();
});

it('builds a signed vendor verification notification URL', function (): void {
    Notification::fake();
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $profile = VendorProfile::factory()->create();
    $user = $profile->user->forceFill(['email_verified_at' => null]);
    $user->save();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();

    app(SendVendorRegistrationEmailVerification::class)->handle(new VendorRegistered($profile));
    Notification::assertCount(1);

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user): bool {
        $request = Request::create($notification->url, 'GET');
        $path = (string) parse_url($notification->url, PHP_URL_PATH);
        $expectedSuffix = '/'.(string) $user->getKey().'/'.sha1($user->getEmailForVerification());

        return URL::hasValidSignature($request)
            && str_ends_with($path, $expectedSuffix);
    });
});

it('verifies the same authenticated vendor and redirects back into the vendor portal', function (): void {
    $user = unverifiedVendorForEmail();
    $panel = Filament::getPanel('vendor');
    Filament::setCurrentPanel($panel);
    Event::fake([Verified::class]);
    $this->actingAs($user, 'vendor');

    $response = $this->get($panel->getVerifyEmailUrl($user));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/vendor-portal')
        ->and($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class, fn (Verified $event): bool => $event->user->is($user));
});

it('rejects an invalid signature, email hash, or foreign vendor verification URL', function (): void {
    $user = unverifiedVendorForEmail();
    $foreign = unverifiedVendorForEmail();
    $panel = Filament::getPanel('vendor');
    Filament::setCurrentPanel($panel);
    $this->actingAs($user, 'vendor');
    $route = $panel->generateRouteName('auth.email-verification.verify');

    $invalidHash = URL::temporarySignedRoute($route, now()->addMinutes(10), [
        'id' => $user->getKey(),
        'hash' => 'invalid',
    ]);
    $this->get($invalidHash)->assertForbidden();

    $foreignUrl = $panel->getVerifyEmailUrl($foreign);
    $this->get($foreignUrl)->assertForbidden();

    $this->get($panel->getVerifyEmailUrl($user).'&tampered=1')->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse()
        ->and($foreign->fresh()->hasVerifiedEmail())->toBeFalse();
});
