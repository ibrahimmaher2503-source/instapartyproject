<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;
use App\Modules\Identity\Infrastructure\Gateways\SmsOtpGateway;
use App\Modules\Identity\Infrastructure\Gateways\StubOtpGateway;
use App\Modules\Identity\Providers\IdentityServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config()->set('services.sms_misr', [
        'username' => 'test-user', 'password' => 'test-password', 'sender' => 'test-sender',
        'environment' => 2, 'timeout' => 1,
    ]);
    Cache::flush();
    Http::preventStrayRequests();
});

it('sends localized SMS and accepts only the current single-use phone code', function (string $locale, int $language): void {
    Http::fake(['smsmisr.com/api/SMS/' => Http::response(['code' => '1901', 'SMSID' => 'test'])]);
    app()->setLocale($locale);
    $gateway = app(SmsOtpGateway::class);
    $phone = '+201012345678';
    $gateway->send($phone, '123456');
    Http::assertSent(fn ($request): bool => $request['mobile'] === '201012345678'
        && $request['language'] === (string) $language
        && str_contains($request['message'], '123456')
        && (string) $request['environment'] === '2');
    expect($gateway->verify('+201012345679', '123456'))->toBeFalse()
        ->and($gateway->verify($phone, '654321'))->toBeFalse();
    $gateway->send($phone, '234567');
    expect($gateway->verify($phone, '123456'))->toBeFalse()
        ->and($gateway->verify($phone, '234567'))->toBeTrue()
        ->and($gateway->verify($phone, '234567'))->toBeFalse();
})->with([['en', 1], ['ar', 2]]);

it('expires without extending the lifetime after wrong guesses and caps verification attempts', function (): void {
    Http::fake(['smsmisr.com/api/SMS/' => Http::response(['code' => '1901'])]);
    $gateway = app(SmsOtpGateway::class);
    $phone = '+201012345678';
    $gateway->send($phone, '123456');
    $this->travel(4)->minutes();
    expect($gateway->verify($phone, '000000'))->toBeFalse();
    $this->travel(61)->seconds();
    expect($gateway->verify($phone, '123456'))->toBeFalse();
    $gateway->send($phone, '123456');
    for ($i = 0; $i < 5; $i++) {
        expect($gateway->verify($phone, 'bad-code'))->toBeFalse();
    }
    expect($gateway->verify($phone, '123456'))->toBeFalse();
});

it('rejects provider failure without creating a challenge and preserves separate password reset tokens', function (): void {
    $gateway = app(SmsOtpGateway::class);
    $phone = '+201012345678';
    Http::fake(['smsmisr.com/api/SMS/' => Http::sequence()->push(['code' => '1902'])->push(['code' => '1901'])->push(['code' => '1901'])]);
    expect(fn () => $gateway->send($phone, '123456'))->toThrow(ValidationException::class)
        ->and($gateway->verify($phone, '123456'))->toBeFalse();
    $gateway->send($phone, '123456');
    $gateway->send($phone, str_repeat('a', 64));
    expect($gateway->verify($phone, '123456'))->toBeTrue();
    config()->set('services.sms_misr.username', null);
    expect(fn () => $gateway->send($phone, '123456'))->toThrow(ValidationException::class);
});

it('binds real SMS outside local and testing environments', function (string $environment, string $expected): void {
    $original = app()->environment();
    app()->instance('env', $environment);
    try {
        (new IdentityServiceProvider(app()))->register();
        expect(app(OtpGatewayInterface::class))->toBeInstanceOf($expected);
    } finally {
        app()->instance('env', $original);
    }
})->with([
    ['local', StubOtpGateway::class], ['testing', StubOtpGateway::class],
    ['staging', SmsOtpGateway::class], ['production', SmsOtpGateway::class],
]);
