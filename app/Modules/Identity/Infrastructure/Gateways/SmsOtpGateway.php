<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Gateways;

use App\Modules\Communication\Domain\Contracts\SmsProviderInterface;
use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SmsOtpGateway implements OtpGatewayInterface
{
    private const TTL_SECONDS = 300;

    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly SmsProviderInterface $provider) {}

    public function send(string $phoneE164, string $code): void
    {
        $key = $this->key($phoneE164);

        Cache::lock($key.':lock', 120)->block(5, function () use ($phoneE164, $code, $key): void {
            $isOtp = preg_match('/^\d{6}$/', $code) === 1;
            $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

            try {
                $result = $this->provider->send(
                    ltrim($phoneE164, '+'),
                    __('identity::sms.'.($isOtp ? 'verification' : 'password_reset'), ['code' => $code], $locale),
                    $locale === 'ar' ? 2 : 1,
                );
            } catch (Throwable) {
                throw ValidationException::withMessages(['phone_e164' => __('identity::sms.unavailable')]);
            }

            if ((string) ($result['code'] ?? '') !== '1901') {
                throw ValidationException::withMessages(['phone_e164' => __('identity::sms.unavailable')]);
            }

            // Password reset tokens have their own storage and verification flow.
            if ($isOtp) {
                Cache::put($key, [
                    'hash' => Hash::make($code),
                    'attempts' => 0,
                    'expires_at' => now()->getTimestamp() + self::TTL_SECONDS,
                ], self::TTL_SECONDS);
            }
        });
    }

    public function verify(string $phoneE164, string $code): bool
    {
        $key = $this->key($phoneE164);

        return Cache::lock($key.':lock', 10)->block(5, function () use ($key, $code): bool {
            $challenge = Cache::get($key);

            if (! is_array($challenge) || $challenge['expires_at'] <= now()->getTimestamp()) {
                return false;
            }

            $valid = preg_match('/^\d{6}$/', $code) === 1 && Hash::check($code, $challenge['hash']);

            if ($valid || ++$challenge['attempts'] >= self::MAX_ATTEMPTS) {
                Cache::forget($key);
            } else {
                // Preserve the original expiration when recording a failed guess.
                Cache::put($key, $challenge, $challenge['expires_at'] - now()->getTimestamp());
            }

            return $valid;
        });
    }

    private function key(string $phoneE164): string
    {
        return 'identity:otp:'.hash('sha256', $phoneE164);
    }
}
