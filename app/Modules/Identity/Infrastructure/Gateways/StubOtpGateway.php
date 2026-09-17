<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Gateways;

use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class StubOtpGateway implements OtpGatewayInterface
{
    public function __construct()
    {
        // Staging added (live audit 2026-06-06 §1.3): the any-6-digit bypass
        // leaking into staging made the wrong/expired-OTP UX untestable before
        // release. Fail loudly there too until a real gateway is bound.
        if (app()->environment(['production', 'staging'])) {
            throw new RuntimeException(
                'StubOtpGateway must not be used in '.app()->environment().'. Bind a real SMS gateway implementation of OtpGatewayInterface in IdentityServiceProvider.'
            );
        }
    }

    public function send(string $phoneE164, string $code): void
    {
        Log::info('OTP stub send', ['phone' => $phoneE164, 'code' => $code]);
    }

    public function verify(string $phoneE164, string $code): bool
    {
        return preg_match('/^\d{6}$/', $code) === 1;
    }
}
