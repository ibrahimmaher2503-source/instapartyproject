<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Services\OtpRateLimiter;
use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;

class SendOtpAction
{
    public function __construct(
        private readonly OtpGatewayInterface $gateway,
        private readonly OtpRateLimiter $rateLimiter,
    ) {}

    public function execute(string $phoneE164): void
    {
        if (config('app.debug')) {
            return;
        }

        $this->rateLimiter->recordSendAttempt($phoneE164);

        $code = app()->environment(['local', 'testing'])
            ? '000000'
            : sprintf('%06d', random_int(0, 999999));

        $this->gateway->send($phoneE164, $code);
    }
}
