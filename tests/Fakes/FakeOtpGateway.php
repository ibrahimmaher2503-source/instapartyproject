<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;

final class FakeOtpGateway implements OtpGatewayInterface
{
    public int $deliveries = 0;

    public function send(string $phoneE164, string $code): void
    {
        $this->deliveries++;
    }

    public function verify(string $phoneE164, string $code): bool
    {
        return false;
    }
}
